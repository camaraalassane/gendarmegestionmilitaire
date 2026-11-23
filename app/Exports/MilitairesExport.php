<?php

namespace App\Exports;

use App\Models\Militaire;
use App\Models\Grade;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use App\Exports\Concerns\ProtegeFormules;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MilitairesExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithStyles, WithCustomValueBinder
{
    use ProtegeFormules;
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
            ->select([
                'id', 'matricule', 'numero_nina', 'nom', 'prenom', 'sexe',
                'date_naissance', 'lieu_naissance', 'telephone', 'groupe_sanguin', 'taille',
                'personne_a_contacter', 'telephone_personne_contacter',
                'grade_actuel', 'categorie', 'date_entree_service', 'date_derniere_promotion',
                'origine_formation', 'specialite', 'statut', 'position_actuelle',
                'fonction_actuelle', 'fonction_passee',
                'unite', 'region', 'date_mutation',
                'status_sanitaire', 'engagement_ops', 'a_permis_conduire', 'antecedent_judiciaire', 'antecedent_disciplinaire',
                'decorations', 'diplomes_academiques',
                'observations', 'date_deces', 'circonstances_deces', 'motif_radiation',
                'reference_decision', 'unite_transfert', 'motif_transfert'
            ])
            ->with([
                'certificats' => fn($q) => $q->select('certificats.id', 'nom_certificat', 'niveau_certificat'),
                'affectations' => fn($q) => $q->select('id', 'militaire_id', 'unite', 'region', 'date_mutation')
            ]); // Préchargement ciblé pour éviter le problème N+1 et alléger la mémoire

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
            if (is_numeric($this->grade)) {
                $query->where('grade_id', (int) $this->grade);
            } else {
                $gradeId = Grade::where('nom_grade', $this->grade)->orWhere('code_grade', $this->grade)->value('id');
                if ($gradeId) {
                    $query->where('grade_id', $gradeId);
                } else {
                    $query->where('grade_actuel', $this->grade);
                }
            }
        }

        if ($this->statut === 'all') {
            // Tous les militaires (sans restriction de statut)
        } elseif ($this->statut === 'retraites_n') {
            $query->whereIn('id', $this->idsN ?: [0]);
        } elseif ($this->statut === 'retraites_n1') {
            $query->whereIn('id', $this->idsN1 ?: [0]);
        } elseif ($this->statut === 'actif') {
            $query->whereBetween('statut_code', [1, 4]);
        } elseif ($this->statut) {
            $code = \App\Enums\StatutMilitaire::depuisTexte($this->statut)?->value;
            if ($code !== null) {
                $query->where('statut_code', $code);
            } else {
                $query->where('statut', $this->statut);
            }
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

            // Affectation & Maillage Territorial
            'Unité / Corps (Actuelle)',
            'Région militaire (Actuelle)',
            'Date dernière mutation',
            'Affectation & Maillage Territorial',

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
        static $refYear = null;
        static $todayMD = null;
        if ($refYear === null) {
            $refYear = (int) date('Y');
            $todayMD = (int) date('md');
        }

        // Helper ultra-rapide pour formater une date sans instancier d'objet Carbon
        $formatDateRaw = function ($rawDate): string {
            if (!$rawDate) return '';
            $s = (string) $rawDate;
            if (strlen($s) >= 10 && $s[4] === '-' && $s[7] === '-') {
                return substr($s, 8, 2) . '/' . substr($s, 5, 2) . '/' . substr($s, 0, 4);
            }
            return $s;
        };

        // Extraction brute des dates sans instanciation d'objets Carbon Eloquent
        $rawNaissance = $militaire->getRawOriginal('date_naissance');
        $rawEntree = $militaire->getRawOriginal('date_entree_service');
        $rawPromo = $militaire->getRawOriginal('date_derniere_promotion');
        $rawMutation = $militaire->getRawOriginal('date_mutation');
        $rawDeces = $militaire->getRawOriginal('date_deces');

        $dNaissance = $formatDateRaw($rawNaissance);
        $dEntree = $formatDateRaw($rawEntree);
        $dPromo = $formatDateRaw($rawPromo);
        $dDeces = $formatDateRaw($rawDeces);

        // Calcul âge direct (0 ms)
        $age = 0;
        if ($rawNaissance && strlen($rawNaissance) >= 10) {
            $y = (int) substr($rawNaissance, 0, 4);
            $md = ((int) substr($rawNaissance, 5, 2)) * 100 + (int) substr($rawNaissance, 8, 2);
            $age = $refYear - $y - ($todayMD < $md ? 1 : 0);
        }

        // Calcul ancienneté service direct au 31 décembre (0 ms)
        $anc = 0;
        if ($rawEntree && strlen($rawEntree) >= 4) {
            $anc = max(0, $refYear - (int) substr($rawEntree, 0, 4));
        }

        // Calcul ancienneté grade direct (0 ms)
        $ancGrade = ($rawPromo && strlen($rawPromo) >= 4) ? max(0, $refYear - (int) substr($rawPromo, 0, 4)) : $anc;

        // Certificats militaires complets avec nom, niveau et date d'obtention (optimisé)
        $certParts = [];
        $niveaux = [];
        if ($militaire->relationLoaded('certificats') && $militaire->certificats) {
            foreach ($militaire->certificats as $c) {
                $nom = $c->nom_certificat ?? $c->niveau_certificat ?? 'Certificat';
                $niveau = ($c->niveau_certificat && $c->niveau_certificat !== $nom) ? " [{$c->niveau_certificat}]" : '';
                $pDate = $c->pivot?->date_obtention;
                $dateStr = $pDate ? ' (' . $formatDateRaw($pDate) . ')' : '';
                $entry = trim($nom . $niveau . $dateStr);
                if ($entry !== '') {
                    $certParts[] = $entry;
                }
                if (!empty($c->niveau_certificat)) {
                    $niveaux[] = $c->niveau_certificat;
                }
            }
        }
        $certificatsComplets = implode(' ; ', $certParts);
        $niveauxCertificats = implode(', ', array_unique($niveaux));

        // Formatage listes JSON ou texte pour décorations et diplômes (rapide, sans collect())
        $formatList = function ($val) {
            if (!$val) return '';
            $trimmed = trim((string)$val);
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $filtered = [];
                    foreach ($decoded as $item) {
                        $itemTrimmed = trim((string)$item);
                        if ($itemTrimmed !== '') {
                            $filtered[] = $itemTrimmed;
                        }
                    }
                    $c = count($filtered);
                    if ($c > 1) {
                        $parts = [];
                        foreach ($filtered as $i => $item) {
                            $parts[] = ($i + 1) . '. ' . $item;
                        }
                        return implode(' ; ', $parts);
                    }
                    return $c === 1 ? $filtered[0] : '';
                }
            }
            return $trimmed;
        };

        // Affectations & Maillage Territorial (Gestion optimisée de l'historique sans collect)
        $affs = $militaire->relationLoaded('affectations') ? $militaire->affectations : null;
        if ($affs && $affs->isNotEmpty()) {
            $affList = [];
            foreach ($affs as $a) {
                $affList[] = [
                    'unite' => trim((string)$a->unite),
                    'region' => trim((string)$a->region),
                    'date_mutation' => $formatDateRaw($a->date_mutation),
                ];
            }
        } elseif ($militaire->unite || $militaire->region || $rawMutation) {
            $affList = [[
                'unite' => trim((string)$militaire->unite),
                'region' => trim((string)$militaire->region),
                'date_mutation' => $formatDateRaw($rawMutation),
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
        $dateMutationActuelle = $affList[0]['date_mutation'] ?? $formatDateRaw($rawMutation);

        // Affectation & Maillage Territorial (Une seule colonne propre sans répétition)
        $numAff = count($affList);
        if ($numAff === 0) {
            $maillageTerritorial = '';
        } elseif ($numAff === 1) {
            $maillageTerritorial = $formatAff($affList[0]);
        } else {
            // S'il y a plusieurs affectations : liste numérotée avec saut de ligne dans la cellule
            $lines = [];
            foreach ($affList as $idx => $item) {
                $suffix = ($idx === 0) ? ' [Actuelle]' : '';
                $lines[] = ($idx + 1) . '. ' . $formatAff($item) . $suffix;
            }
            $maillageTerritorial = implode("\n", $lines);
        }

        $row = [
            // Identifiants & État Civil
            $militaire->matricule,
            $militaire->numero_nina ?? '',
            $militaire->nom,
            $militaire->prenom,
            $militaire->sexe ?? '',
            $dNaissance,
            $militaire->lieu_naissance ?? '',
            $age,
            $militaire->telephone ?? '',
            $militaire->groupe_sanguin ?? '',
            $militaire->taille ?? '',
            $militaire->personne_a_contacter ?? '',
            $militaire->telephone_personne_contacter ?? '',

            // Grade, Statut & Carrière
            $militaire->grade_actuel,
            $militaire->categorie ?? '',
            $dEntree,
            $anc,
            $dPromo,
            $ancGrade,
            $militaire->origine_formation ?? '',
            $militaire->specialite ?? '',
            $militaire->statut,
            $militaire->position_actuelle ?? '',
            $militaire->fonction_actuelle ?? '',
            $militaire->fonction_passee ?? '',

            // Affectation & Maillage Territorial
            $uniteActuelle,
            $regionActuelle,
            $dateMutationActuelle,
            $maillageTerritorial,

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
            $dDeces,
            $militaire->circonstances_deces ?? '',
            $militaire->motif_radiation ?? '',
            $militaire->reference_decision ?? '',
            $militaire->unite_transfert ?? '',
            $militaire->motif_transfert ?? '',
        ];

        // Neutralise les injections de formules CSV/Excel (CWE-1236)
        foreach ($row as &$val) {
            if (is_string($val) && $val !== '') {
                $firstChar = $val[0];
                if ($firstChar === '=' || $firstChar === '+' || $firstChar === '-' || $firstChar === '@' || $firstChar === "\t" || $firstChar === "\r") {
                    $val = "'" . $val;
                }
            }
        }
        return $row;
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

        // Définition de la largeur par défaut sur toutes les colonnes (évite le calcul lourd ShouldAutoSize)
        $sheet->getDefaultColumnDimension()->setWidth(18);

        // Largeurs optimisées pour colonnes spécifiques
        $sheet->getColumnDimension('A')->setWidth(14); // Matricule
        $sheet->getColumnDimension('C')->setWidth(18); // Nom
        $sheet->getColumnDimension('D')->setWidth(18); // Prénom
        $sheet->getColumnDimension('N')->setWidth(20); // Grade actuel
        $sheet->getColumnDimension('Z')->setWidth(25); // Unité
        $sheet->getColumnDimension('AA')->setWidth(20); // Région
        $sheet->getColumnDimension('AC')->setWidth(35); // Maillage Territorial
        $sheet->getColumnDimension('AJ')->setWidth(30); // Certificats
        $sheet->getColumnDimension('AL')->setWidth(30); // Décorations
        $sheet->getColumnDimension('AM')->setWidth(30); // Diplômes
        $sheet->getColumnDimension('AN')->setWidth(30); // Observations

        return [];
    }
}
