<?php

namespace App\Exports;

use App\Models\Militaire;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MilitairesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $search;
    protected $grade;
    protected $statut;
    protected $idsN;
    protected $idsN1;

    public function __construct($search = null, $grade = null, $statut = null, $idsN = [], $idsN1 = [])
    {
        $this->search = $search;
        $this->grade = $grade;
        $this->statut = $statut;
        $this->idsN = $idsN;
        $this->idsN1 = $idsN1;
    }

    public function query()
    {
        $query = Militaire::query()
            ->with(['certificats', 'affectations']); // Préchargement pour éviter le problème N+1

        if ($this->search) {
            $search = preg_replace('/\s+/', ' ', $this->search);
            $searchTerms = explode(' ', trim($search));
            $query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    if (!empty($term)) {
                        $q->where(function ($subQ) use ($term) {
                            $subQ->where('nom', 'LIKE', "%{$term}%")
                                 ->orWhere('prenom', 'LIKE', "%{$term}%")
                                 ->orWhere('matricule', 'LIKE', "%{$term}%");
                        });
                    }
                }
            });
        }

        if ($this->grade) {
            \Log::info('✅ Export - application du filtre grade : ' . $this->grade);
            $query->where('grade_actuel', $this->grade);
        }

        if ($this->statut === 'all') {
            // Tous les militaires (sans restriction de statut)
        } elseif ($this->statut === 'retraites_n') {
            $query->whereIn('id', $this->idsN ?: [0]);
        } elseif ($this->statut === 'retraites_n1') {
            $query->whereIn('id', $this->idsN1 ?: [0]);
        } elseif ($this->statut) {
            $query->where('statut', $this->statut);
        }

        // Tri pour l'export
        $query->orderBy('nom')->orderBy('prenom');

        return $query;
    }

    public function headings(): array
    {
        return [
            // Identifiants & État Civil
            'Matricule',
            'N° NINA',
            'Nom',
            'Prénom',
            'Sexe',
            'Date de naissance',
            'Lieu de naissance',
            'Âge (ans)',
            'Téléphone',
            'Groupe sanguin',
            'Taille (stature)',
            'Personne à contacter (Urgence)',
            'Téléphone contact urgence',

            // Grade, Statut & Carrière
            'Grade actuel',
            'Catégorie statutaire',
            'Date entrée en service',
            'Ancienneté service (ans)',
            'Date dernière promotion',
            'Ancienneté grade (ans)',
            'Origine / École formation',
            'Spécialité',
            'Statut administratif',
            'Position administrative',
            'Fonction actuelle',
            'Fonction passée',

            // Affectation & Maillage Territorial (Détails complets et historique)
            'Unité / Corps (Actuelle)',
            'Région militaire (Actuelle)',
            'Date dernière mutation',
            'Affectation 1 (Actuelle)',
            'Affectation 2 (Précédente)',
            'Affectation 3',
            'Affectation & Maillage Territorial (Historique complet)',

            // Santé, Aptitude & Opérations
            'Statut sanitaire / Aptitude',
            'Engagement OPS / Théâtres opérations',
            'Permis de conduire',
            'Antécédent judiciaire',
            'Antécédent disciplinaire',

            // Formations, Décorations & Diplômes
            'Certificats militaires (Détails complets)',
            'Niveaux certificats',
            'Décorations & Médailles (Toutes)',
            'Diplômes académiques (Tous)',

            // Circonstances spécifiques & Observations
            'Observations',
            'Date décès',
            'Circonstances décès',
            'Motif radiation',
            'Référence décision',
            'Unité transfert',
            'Motif transfert',
        ];
    }

    public function map($militaire): array
    {
        // Certificats militaires complets avec nom, niveau et date d'obtention
        $certificatsComplets = $militaire->certificats->map(function ($c) {
            $nom = $c->nom_certificat ?? $c->niveau_certificat ?? 'Certificat';
            $niveau = ($c->niveau_certificat && $c->niveau_certificat !== $nom) ? " [{$c->niveau_certificat}]" : '';
            $date = !empty($c->pivot->date_obtention)
                ? ' (' . Carbon::parse($c->pivot->date_obtention)->format('d/m/Y') . ')'
                : '';
            return trim($nom . $niveau . $date);
        })->filter()->implode(' ; ');

        $niveauxCertificats = $militaire->certificats->pluck('niveau_certificat')->filter()->implode(', ');

        // Formatage listes JSON ou texte pour décorations et diplômes
        $formatList = function ($val) {
            if (!$val) return '';
            $trimmed = trim((string)$val);
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $filtered = array_values(array_filter(array_map('trim', $decoded)));
                    if (count($filtered) > 1) {
                        return collect($filtered)->map(fn($item, $i) => ($i + 1) . '. ' . $item)->implode(' ; ');
                    }
                    return implode(', ', $filtered);
                }
            }
            return $trimmed;
        };

        // Affectations & Maillage Territorial (Gestion complète de l'historique)
        $affs = $militaire->affectations;
        if ($affs && $affs->isNotEmpty()) {
            $affList = $affs->map(function ($a) {
                return [
                    'unite' => trim((string)$a->unite),
                    'region' => trim((string)$a->region),
                    'date_mutation' => $a->date_mutation ? Carbon::parse($a->date_mutation)->format('d/m/Y') : '',
                ];
            })->values()->toArray();
        } elseif ($militaire->unite || $militaire->region || $militaire->date_mutation) {
            $affList = [[
                'unite' => trim((string)$militaire->unite),
                'region' => trim((string)$militaire->region),
                'date_mutation' => $militaire->date_mutation?->format('d/m/Y') ?? '',
            ]];
        } else {
            $affList = [];
        }

        // Formateur pour une affectation individuelle
        $formatAff = function ($affItem, $label = '') {
            if (!$affItem || (empty($affItem['unite']) && empty($affItem['region']) && empty($affItem['date_mutation']))) {
                return '';
            }
            $parts = [];
            if (!empty($affItem['unite'])) {
                $parts[] = $affItem['unite'];
            }
            if (!empty($affItem['region'])) {
                $parts[] = 'Région: ' . $affItem['region'];
            }
            if (!empty($affItem['date_mutation'])) {
                $parts[] = 'Mutation: ' . $affItem['date_mutation'];
            }
            $res = implode(' — ', $parts);
            return $label ? "{$label}: {$res}" : $res;
        };

        $uniteActuelle = $affList[0]['unite'] ?? ($militaire->unite ?? '');
        $regionActuelle = $affList[0]['region'] ?? ($militaire->region ?? '');
        $dateMutationActuelle = $affList[0]['date_mutation'] ?? ($militaire->date_mutation?->format('d/m/Y') ?? '');

        $affectation1 = isset($affList[0]) ? $formatAff($affList[0]) : '';
        $affectation2 = isset($affList[1]) ? $formatAff($affList[1]) : '';
        $affectation3 = isset($affList[2]) ? $formatAff($affList[2]) : '';

        // Historique complet et numéroté de TOUTES les affectations (Maillage territorial)
        $historiqueAff = !empty($affList)
            ? collect($affList)->map(function ($item, $idx) use ($formatAff) {
                $suffix = ($idx === 0) ? ' [Actuelle]' : '';
                return ($idx + 1) . '. ' . $formatAff($item) . $suffix;
            })->implode(' | ')
            : '';

        $row = [
            // Identifiants & État Civil
            $militaire->matricule,
            $militaire->numero_nina ?? '',
            $militaire->nom,
            $militaire->prenom,
            $militaire->sexe ?? '',
            $militaire->date_naissance?->format('d/m/Y') ?? '',
            $militaire->lieu_naissance ?? '',
            round($militaire->age),
            $militaire->telephone ?? '',
            $militaire->groupe_sanguin ?? '',
            $militaire->taille ?? '',
            $militaire->personne_a_contacter ?? '',
            $militaire->telephone_personne_contacter ?? '',

            // Grade, Statut & Carrière
            $militaire->grade_actuel,
            $militaire->categorie ?? '',
            $militaire->date_entree_service?->format('d/m/Y') ?? '',
            round($militaire->anciennete),
            $militaire->date_derniere_promotion?->format('d/m/Y') ?? '',
            round($militaire->anciennete_grade ?? 0),
            $militaire->origine_formation ?? '',
            $militaire->specialite ?? '',
            $militaire->statut,
            $militaire->position_actuelle ?? '',
            $militaire->fonction_actuelle ?? '',
            $militaire->fonction_passee ?? '',

            // Affectation & Maillage Territorial (Détails complets et historique)
            $uniteActuelle,
            $regionActuelle,
            $dateMutationActuelle,
            $affectation1,
            $affectation2,
            $affectation3,
            $historiqueAff,

            // Santé, Aptitude & Opérations
            $militaire->status_sanitaire ?? '',
            $militaire->engagement_ops ?? '',
            $militaire->a_permis_conduire ? 'Oui' : 'Non',
            $militaire->antecedent_judiciaire ? 'Oui' : 'Non',
            $militaire->antecedent_disciplinaire ? 'Oui' : 'Non',

            // Formations, Décorations & Diplômes
            $certificatsComplets,
            $niveauxCertificats,
            $formatList($militaire->decorations),
            $formatList($militaire->diplomes_academiques),

            // Circonstances spécifiques & Observations
            $militaire->observations ?? '',
            $militaire->date_deces?->format('d/m/Y') ?? '',
            $militaire->circonstances_deces ?? '',
            $militaire->motif_radiation ?? '',
            $militaire->reference_decision ?? '',
            $militaire->unite_transfert ?? '',
            $militaire->motif_transfert ?? '',
        ];

        return array_map([$this, 'sanitizeFormula'], $row);
    }

    /**
     * Neutralise les injections de formules CSV/Excel (CWE-1236)
     */
    private function sanitizeFormula($value): mixed
    {
        if (is_string($value) && $value !== '') {
            $firstChar = $value[0];
            if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"])) {
                return "'" . $value;
            }
        }
        return $value;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('1')->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle('1')->getFill()->getStartColor()->setARGB('FF0369A1'); // sky-700
        $sheet->getStyle('1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }
}
