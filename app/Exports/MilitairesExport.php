<?php

namespace App\Exports;

use App\Models\Militaire;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MilitairesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $search;
    protected $grade;
    protected $statut;

    public function __construct($search = null, $grade = null, $statut = null)
    {
        $this->search = $search;
        $this->grade = $grade;
        $this->statut = $statut;
    }

    public function query()
    {
        $query = Militaire::query()
            ->with('certificats'); // Précharger les certificats pour éviter N+1

        if ($this->search) {
            $search = preg_replace('/\s+/', ' ', $this->search);
            $searchTerms = explode(' ', trim($search));
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    if (!empty($term)) {
                        $q->where(function($subQ) use ($term) {
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

        if ($this->statut) {
            $query->where('statut', $this->statut);
        }

        // Tri simple pour l'export
        $query->orderBy('nom')->orderBy('prenom');

        return $query;
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'MLE GRM',
            'N° Info',
            'N° Nina',
            'Nom',
            'Prénom',
            'Grade',
            'Catégorie',
            'Unité',
            'Région',
            'Date mutation',
            'Date entrée service',
            'Date naissance',
            'Sexe',
            'Groupe sanguin',
            'Taille',
            'Âge (ans)',
            'Ancienneté (ans)',
            'Statut',
            'Statut sanitaire',
            'Engagement OPS',
            'Position actuelle',
            'Fonction actuelle',
            'Origine formation',
            'Spécialité',
            'Permis conduire',
            'Certificats militaires',
            'Diplômes académiques',
            'Décorations',
            'Observations'
        ];
    }

    public function map($militaire): array
    {
        // Récupération des certificats (niveaux)
        $certificats = $militaire->certificats->pluck('niveau_certificat')->filter()->implode(', ');

        $row = [
            $militaire->matricule,
            $militaire->mle_grm ?? '',
            $militaire->numero_info ?? '',
            $militaire->numero_nina ?? '',
            $militaire->nom,
            $militaire->prenom,
            $militaire->grade_actuel,
            $militaire->categorie ?? '',
            $militaire->unite ?? '',
            $militaire->region ?? '',
            $militaire->date_mutation?->format('d/m/Y') ?? '',
            $militaire->date_entree_service?->format('d/m/Y') ?? '',
            $militaire->date_naissance?->format('d/m/Y') ?? '',
            $militaire->sexe ?? '',
            $militaire->groupe_sanguin ?? '',
            $militaire->taille ?? '',
            round($militaire->age),
            round($militaire->anciennete),
            $militaire->statut,
            $militaire->status_sanitaire ?? '',
            $militaire->engagement_ops ?? '',
            $militaire->position_actuelle ?? '',
            $militaire->fonction_actuelle ?? '',
            $militaire->origine_formation ?? '',
            $militaire->specialite ?? '',
            $militaire->a_permis_conduire ? 'Oui' : 'Non',
            $certificats,
            $militaire->diplomes_academiques ?? '',
            $militaire->decorations ?? '',
            $militaire->observations ?? '',
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
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}