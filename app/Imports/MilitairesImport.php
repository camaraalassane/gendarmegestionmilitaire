<?php

namespace App\Imports;

use App\Models\Militaire;
use App\Models\Certificat;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MilitairesImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure, WithChunkReading
{
    use SkipsFailures;

    /**
     * Mapping des colonnes Excel vers les noms de certificats en base
     */
    protected const CERTIFICATE_MAPPING = [
        // Certificats sous-officiers
        'cat1'  => "Certificat d'Aptitude Technique Niveau 1",
        'cat2'  => "Certificat d'Aptitude Technique Niveau 2",
        'cia'   => "Certificat Interarmes (CIA)",
        'ba1'   => "Brevet d'Arme N1",
        'ba2'   => "Brevet d'Arme N2",
        'bmp1'  => "Brevet Militaire Professionnel N1",
        'bmp2'  => "Brevet Militaire Professionnel N2",
        'bs'    => "Brevet Supérieur",
        'ct2'   => "Certificat Technique N2",
        'be'    => "Brevet Élémentaire (BE)",
        'ct1'   => "Certificat Technique N1 (CT1)",
        // Formations officiers
        'apli'  => "Cour d'Application",
        'cfcu'  => "Cour des Capitaines / CFCU / CPO",
        'cpo'   => "Cour des Capitaines / CFCU / CPO",
        'cem'   => "Cour d'État-Major",
        'certificat_etat_major' => "Certificat d'état-major",
        'ecole_guerre' => "École de guerre / Brevet Supérieur de Second Degré",
    ];

    protected string $duplicateAction;
    protected array $summary = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    protected array $duplicates = [];
    protected array $errorDetails = [];
    protected ?Collection $certificatsCache = null;
    protected ?Collection $gradesCache = null;
    protected ?Collection $theatresCache = null;
    protected ?Collection $regionsCache = null;
    protected ?Collection $unitesCache = null;
    protected ?Collection $organismesDetachementCache = null;
    protected ?string $detectedDateFormat = null;

    public function __construct(string $duplicateAction = 'ignore')
    {
        $this->duplicateAction = $duplicateAction;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    protected function getCertificatsCache(): Collection
    {
        if ($this->certificatsCache === null) {
            $cache = collect();
            foreach (Certificat::all() as $item) {
                $cache->put(mb_strtolower(trim($item->nom_certificat)), $item);
                if (!empty($item->niveau_certificat)) {
                    $cache->put(mb_strtolower(trim($item->niveau_certificat)), $item);
                }
            }
            $this->certificatsCache = $cache;
        }
        return $this->certificatsCache;
    }

    protected function getGradesCache(): Collection
    {
        if ($this->gradesCache === null) {
            $map = [];
            foreach (\App\Models\Grade::all(['nom_grade', 'code_grade']) as $g) {
                $nom = trim($g->nom_grade);
                $map[mb_strtolower($nom)] = $nom;
                if ($g->code_grade) {
                    $map[mb_strtolower(trim($g->code_grade))] = $nom;
                }
            }

            // Aliases militaires courants et abréviations des armées
            $aliases = [
                '2°cl' => 'Soldat 2',
                '2° cl' => 'Soldat 2',
                '2cl' => 'Soldat 2',
                '2e cl' => 'Soldat 2',
                '2eme classe' => 'Soldat 2',
                '2e classe' => 'Soldat 2',
                'soldat 2' => 'Soldat 2',
                'soldat 2e classe' => 'Soldat 2',
                'soldat 2eme classe' => 'Soldat 2',
                '1°cl' => 'Soldat 1',
                '1cl' => 'Soldat 1',
                '1ere classe' => 'Soldat 1',
                '1e classe' => 'Soldat 1',
                'cpl' => 'Caporal',
                'cpl-chef' => 'Caporal-chef',
                'cpl chef' => 'Caporal-chef',
                'sgt' => 'Sergent',
                'mdl' => 'Sergent',
                'marechal des logis' => 'Sergent',
                'sch' => 'Sergent-Chef',
                'mdc' => 'Sergent-Chef',
                'marechal des logis-chef' => 'Sergent-Chef',
                'marechal des logis chef' => 'Sergent-Chef',
                'adj' => 'Adjudant',
                'adc' => 'Adjudant-Chef',
                'adjudant chef' => 'Adjudant-Chef',
                'adjudant-chef' => 'Adjudant-Chef',
                'acm' => 'Adjudant-Chef major',
                'adjudant-chef major' => 'Adjudant-Chef major',
                'adjudant chef major' => 'Adjudant-Chef major',
                'slt' => 'Sous-lieutenant',
                'sous lieutenant' => 'Sous-lieutenant',
                'sous-lieutenant' => 'Sous-lieutenant',
                'ltn' => 'Lieutenant',
                'cne' => 'Capitaine',
                'ces' => 'Commandant',
                'cdt' => 'Commandant',
                'chef d escadron' => 'Commandant',
                'chef d\'escadron' => 'Commandant',
                'chef d\'escadrons' => 'Commandant',
                'lcl' => 'Lieutenant-colonel',
                'col' => 'Colonel',
                'clm' => 'Colonel-Major',
                'colonel major' => 'Colonel-Major',
                'gdb' => 'Général de brigade',
                'gbr' => 'Général de brigade',
                'general de brigade' => 'Général de brigade',
                'gdd' => 'Général de division',
                'gdv' => 'Général de division',
                'general de division' => 'Général de division',
            ];

            foreach ($aliases as $k => $v) {
                $map[mb_strtolower($k)] = $v;
            }

            $this->gradesCache = collect($map);
        }
        return $this->gradesCache;
    }

    protected function parseGrade(?string $value): string
    {
        $value = trim((string)$value);
        if (empty($value)) return '';

        $grades = $this->getGradesCache();
        $lowerValue = mb_strtolower($value);

        return $grades->get($lowerValue, $value);
    }

    protected function getTheatresCache(): Collection
    {
        if ($this->theatresCache === null) {
            $this->theatresCache = \App\Models\Theatre::all();
        }
        return $this->theatresCache;
    }

    protected function getRegionsCache(): Collection
    {
        if ($this->regionsCache === null) {
            $this->regionsCache = \App\Models\Region::all();
        }
        return $this->regionsCache;
    }

    protected function getUnitesCache(): Collection
    {
        if ($this->unitesCache === null) {
            $this->unitesCache = \App\Models\Unite::all();
        }
        return $this->unitesCache;
    }

    protected function getOrganismesDetachementCache(): Collection
    {
        if ($this->organismesDetachementCache === null) {
            $this->organismesDetachementCache = \App\Models\OrganismeDetachement::all();
        }
        return $this->organismesDetachementCache;
    }

    public function prepareForValidation($data, $index)
    {
        return $this->normalizeRow(is_array($data) ? $data : (array) $data);
    }

    public function collection(Collection $rows)
    {
        // Détection automatique du format de date (ex: m/d/Y vs d/m/Y)
        if ($this->detectedDateFormat === null) {
            foreach ($rows as $r) {
                $arr = is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r);
                foreach ($arr as $val) {
                    if (is_string($val) && preg_match('#^(\d{1,2})[/-](\d{1,2})[/-]\d{4}#', trim($val), $m)) {
                        $p1 = (int)$m[1];
                        $p2 = (int)$m[2];
                        if ($p2 > 12 && $p1 <= 12) {
                            $this->detectedDateFormat = 'm/d/Y';
                            break 2;
                        } elseif ($p1 > 12 && $p2 <= 12) {
                            $this->detectedDateFormat = 'd/m/Y';
                            break 2;
                        }
                    }
                }
            }
        }

        $normalizedRows = $rows->map(function ($r) {
            $arr = is_array($r) ? $r : $r->toArray();
            return $this->normalizeRow($arr);
        });

        // Récupérer la liste des matricules valides du lot
        $matricules = $normalizedRows->pluck('matricule')
            ->filter(function ($m) {
                return $m !== null && trim((string)$m) !== '';
            })
            ->map(function ($m) {
                return trim((string)$m);
            })
            ->unique()
            ->toArray();

        // Récupération globale des militaires existants pour ce lot (1 seule requête SQL)
        $existingMilitaires = !empty($matricules)
            ? Militaire::whereIn('matricule', $matricules)->get()->keyBy('matricule')
            : collect();

        $pivotRowsToUpsert = [];
        $now = now()->toDateTimeString();

        DB::transaction(function () use ($normalizedRows, $existingMilitaires, &$pivotRowsToUpsert, $now) {
            foreach ($normalizedRows as $index => $row) {
                try {
                    $matricule = trim((string)($row['matricule'] ?? ''));
                    if (empty($matricule)) {
                        continue;
                    }

                    $existing = $existingMilitaires->get($matricule);

                    if ($existing) {
                        if ($this->duplicateAction === 'update') {
                            $militaire = $this->updateMilitaire($existing, $row);
                            $this->summary['updated']++;
                            $this->collectCertificatesForMilitaire($militaire, $row, $pivotRowsToUpsert, $now);
                        } else {
                            $this->summary['skipped']++;
                            $this->duplicates[] = [
                                'matricule' => $matricule,
                                'nom' => $row['nom'] ?? '',
                                'prenom' => $row['prenom'] ?? '',
                                'action' => 'Ignoré'
                            ];
                        }
                        continue;
                    }

                    $militaire = $this->createMilitaire($row);
                    $this->summary['created']++;
                    $this->collectCertificatesForMilitaire($militaire, $row, $pivotRowsToUpsert, $now);

                } catch (\Throwable $e) {
                    $this->summary['errors']++;
                    $this->errorDetails[] = [
                        'row' => $index + 2,
                        'matricule' => $row['matricule'] ?? 'N/A',
                        'message' => $e->getMessage()
                    ];
                    Log::error('Erreur import ligne ' . ($index + 2) . ': ' . $e->getMessage(), [
                        'row' => $row,
                        'exception' => $e->getMessage()
                    ]);
                }
            }

            // Insertion / mise à jour globale ultra-rapide des certificats pivot pour le lot (1 seule requête SQL bulk upsert)
            if (!empty($pivotRowsToUpsert)) {
                // Dédupliquer par (militaire_id, certificat_id) dans le lot
                $uniquePivot = [];
                foreach ($pivotRowsToUpsert as $item) {
                    $key = $item['militaire_id'] . '_' . $item['certificat_id'];
                    $uniquePivot[$key] = $item;
                }

                DB::table('certificat_militaire')->upsert(
                    array_values($uniquePivot),
                    ['militaire_id', 'certificat_id'],
                    ['date_obtention', 'updated_at']
                );
            }
        });

        // Génération des alertes après l'enregistrement des certificats
        try {
            $controller = new \App\Http\Controllers\MilitaireController();
            $militairesToVerify = Militaire::with('certificats')->whereIn('matricule', $matricules)->get();
            foreach ($militairesToVerify as $m) {
                $controller->verifierAlertes($m);
            }
            \App\Models\Militaire::incrementCacheVersion();
        } catch (\Throwable $e) {
            Log::error('Erreur lors de la génération des alertes après import: ' . $e->getMessage());
        }
    }

    /**
     * Parse une valeur de date, supportant :
     * - Les nombres sériels Excel (ex: 45292)
     * - Le format français JJ/MM/AAAA
     * - Le format standard AAAA-MM-JJ
     * - Le format JJ-MM-AAAA
     */
    protected function parseDate($value): ?Carbon
    {
        if (empty($value) || $value === '' || $value === null) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $excelBaseDate = Carbon::create(1899, 12, 30);
                return $excelBaseDate->addDays((int) $value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = trim((string) $value);

        // Extraire uniquement la partie date si elle est suivie de texte (ex: "03/04/1967 à Bamako")
        if (preg_match('#^(\d{1,2}[/-]\d{1,2}[/-]\d{4})#', $value, $matches)) {
            $value = $matches[1];
        }

        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $value, $matches)) {
            $p1 = (int)$matches[1];
            $p2 = (int)$matches[2];
            $year = (int)$matches[3];

            // Si p2 > 12 et p1 <= 12, c'est obligatoirement m/d/Y (format US / Excel export)
            if ($p2 > 12 && $p1 <= 12 && $p2 <= 31) {
                return Carbon::createFromDate($year, $p1, $p2)->startOfDay();
            }
            // Si p1 > 12 et p2 <= 12, c'est obligatoirement d/m/Y (format FR standard)
            if ($p1 > 12 && $p2 <= 12 && $p1 <= 31) {
                return Carbon::createFromDate($year, $p2, $p1)->startOfDay();
            }
            // Si les deux sont <= 12 :
            if ($this->detectedDateFormat === 'm/d/Y') {
                return Carbon::createFromDate($year, $p1, $p2)->startOfDay();
            }
            return Carbon::createFromDate($year, $p2, $p1)->startOfDay();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalise la valeur du sexe
     */
    protected function parseSexe($value): ?string
    {
        if (empty($value)) return null;

        $value = mb_strtolower(trim((string)$value));

        if (in_array($value, ['m', 'masculin', 'homme', 'h', 'male'])) {
            return 'Masculin';
        }
        if (in_array($value, ['f', 'féminin', 'feminin', 'femme', 'female'])) {
            return 'Féminin';
        }

        return null;
    }

    /**
     * Normalise la valeur du statut
     */
    protected function parseStatut($value): string
    {
        if (empty($value)) return 'actif';

        $enum = \App\Enums\StatutMilitaire::depuisTexte((string) $value);
        if ($enum !== null) {
            return $enum->label();
        }

        $value = mb_strtolower(trim((string)$value));

        $mapping = [
            'actif' => 'actif',
            'retraite' => 'retraité',
            'retraité' => 'retraité',
            'deserteur' => 'déserteur',
            'déserteur' => 'déserteur',
            'decede' => 'décédé',
            'décédé' => 'décédé',
            'formation' => 'en stage',
            'stage' => 'en stage',
            'en stage' => 'en stage',
            'mission' => 'en mission',
            'en mission' => 'en mission',
            'detache' => 'détaché',
            'détache' => 'détaché',
            'detachement' => 'détaché',
            'détachement' => 'détaché',
            'detachee' => 'détaché',
            'radie' => 'radié',
            'radié' => 'radié',
            'radiee' => 'radié',
        ];

        return $mapping[$value] ?? (in_array($value, ['actif', 'retraité', 'déserteur', 'décédé', 'détaché', 'en mission', 'en stage', 'radié']) ? $value : 'actif');
    }

    /**
     * Parse une valeur booléenne (0/1, oui/non, vrai/faux, true/false)
     */
    protected function parseBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (bool) (int) $value;

        $value = mb_strtolower(trim((string) $value));

        return in_array($value, ['1', 'oui', 'vrai', 'true', 'yes', 'o', 'v']);
    }

    protected function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $k = mb_strtolower(trim((string)$key));
            $k = str_replace([' ', '-', '°', '°cl', '.', '’', "'"], ['_', '_', '', '', '_', '', ''], $k);
            if (is_string($value) && in_array(mb_strtolower(trim($value)), ['nan', 'null', '#n/a', 'none'])) {
                $value = null;
            }
            $normalized[$k] = $value;
        }

        // Aliases pour matricule
        if (!isset($normalized['matricule']) || trim((string)$normalized['matricule']) === '') {
            $normalized['matricule'] = $normalized['mles'] ?? $normalized['mle'] ?? $normalized['matricules'] ?? null;
        }
        // Aliases pour mle_grm
        if (!isset($normalized['mle_grm']) || trim((string)$normalized['mle_grm']) === '') {
            $normalized['mle_grm'] = $normalized['grm_mle'] ?? $normalized['mle_grm'] ?? null;
        }
        // Aliases pour numero_info
        if (!isset($normalized['numero_info']) || trim((string)$normalized['numero_info']) === '') {
            $normalized['numero_info'] = $normalized['n_info'] ?? $normalized['no_info'] ?? $normalized['info'] ?? null;
        }
        // Aliases pour numero_nina
        if (!isset($normalized['numero_nina']) || trim((string)$normalized['numero_nina']) === '') {
            $normalized['numero_nina'] = $normalized['n_nina'] ?? $normalized['no_nina'] ?? $normalized['nina'] ?? null;
        }
        // Aliases pour nom
        if (!isset($normalized['nom']) || trim((string)$normalized['nom']) === '') {
            $normalized['nom'] = $normalized['noms'] ?? null;
        }
        // Aliases pour prenom
        if (!isset($normalized['prenom']) || trim((string)$normalized['prenom']) === '') {
            $normalized['prenom'] = $normalized['prenoms'] ?? $normalized['premons'] ?? null;
        }
        // Aliases pour date_naissance (ex: DATE DE NAISSANCE, naissance)
        if (!isset($normalized['date_naissance']) || trim((string)$normalized['date_naissance']) === '') {
            $normalized['date_naissance'] = $normalized['date_de_naissance'] ?? $normalized['naissance'] ?? null;
        }
        // Aliases pour date_mutation
        if (!isset($normalized['date_mutation']) || trim((string)$normalized['date_mutation']) === '') {
            $normalized['date_mutation'] = $normalized['date_de_mutation'] ?? null;
        }
        // Aliases pour grade_actuel
        if (!isset($normalized['grade_actuel']) || trim((string)$normalized['grade_actuel']) === '') {
            $normalized['grade_actuel'] = $normalized['grade'] ?? $normalized['grades'] ?? null;
        }
        // Aliases pour unite
        if (!isset($normalized['unite']) || trim((string)$normalized['unite']) === '') {
            $normalized['unite'] = $normalized['unite_grm'] ?? null;
        }
        // Aliases pour region
        if (!isset($normalized['region']) || trim((string)$normalized['region']) === '') {
            $normalized['region'] = $normalized['regions'] ?? null;
        }
        // Aliases pour categorie
        if (!isset($normalized['categorie']) || trim((string)$normalized['categorie']) === '') {
            $normalized['categorie'] = $normalized['categories'] ?? $normalized['cat'] ?? null;
        }
        // Aliases pour status_sanitaire
        if (!isset($normalized['status_sanitaire']) || trim((string)$normalized['status_sanitaire']) === '') {
            $normalized['status_sanitaire'] = $normalized['statut_sanitaire'] ?? null;
        }
        // Aliases pour engagement_ops
        if (!isset($normalized['engagement_ops']) || trim((string)$normalized['engagement_ops']) === '') {
            $normalized['engagement_ops'] = $normalized['ops'] ?? $normalized['engagement'] ?? null;
        }
        // Aliases pour origine_formation
        if (!isset($normalized['origine_formation']) || trim((string)$normalized['origine_formation']) === '') {
            $normalized['origine_formation'] = $normalized['origine_lieu_de_formation'] ?? null;
        }
        // Aliases pour decorations
        if (!isset($normalized['decorations']) || trim((string)$normalized['decorations']) === '') {
            $normalized['decorations'] = $normalized['decoration'] ?? null;
        }
        // Aliases pour observations
        if (!isset($normalized['observations']) || trim((string)$normalized['observations']) === '') {
            $normalized['observations'] = $normalized['obs'] ?? null;
        }
        // Aliases pour theatre
        if (!isset($normalized['theatre']) || trim((string)$normalized['theatre']) === '') {
            $normalized['theatre'] = $normalized['theatres'] ?? $normalized['theatre_operations'] ?? $normalized['theatre_ops'] ?? $normalized['operation'] ?? null;
        }
        // Aliases pour est_blesse_guerre
        if (!isset($normalized['est_blesse_guerre']) || trim((string)$normalized['est_blesse_guerre']) === '') {
            $normalized['est_blesse_guerre'] = $normalized['blesse_guerre'] ?? $normalized['blesse'] ?? null;
        }
        // Aliases pour circonstances_blessure
        if (!isset($normalized['circonstances_blessure']) || trim((string)$normalized['circonstances_blessure']) === '') {
            $normalized['circonstances_blessure'] = $normalized['circonstance_blessure'] ?? $normalized['circonstances'] ?? $normalized['circonstance'] ?? null;
        }
        // Aliases pour date_blessure
        if (!isset($normalized['date_blessure']) || trim((string)$normalized['date_blessure']) === '') {
            $normalized['date_blessure'] = $normalized['date_de_blessure'] ?? null;
        }

        // Aliases pour fonction
        if (!isset($normalized['fonction_actuelle']) || trim((string)$normalized['fonction_actuelle']) === '') {
            $normalized['fonction_actuelle'] = $normalized['fonction'] ?? $normalized['fonctions'] ?? null;
        }
        // Aliases pour position
        if (!isset($normalized['position_actuelle']) || trim((string)$normalized['position_actuelle']) === '') {
            $normalized['position_actuelle'] = $normalized['position'] ?? $normalized['positions'] ?? null;
        }
        // Aliases pour diplomes_academiques
        if (!isset($normalized['diplomes_academiques']) || trim((string)$normalized['diplomes_academiques']) === '') {
            $compil = trim((string)($normalized['diplomes_compil'] ?? ''));
            $source = trim((string)($normalized['diplomes_source'] ?? ''));
            if (str_starts_with($compil, '=')) $compil = '';
            if (str_starts_with($source, '=')) $source = '';
            if (!empty($compil) && !empty($source) && $compil !== $source) {
                $normalized['diplomes_academiques'] = $compil . ' | ' . $source;
            } else {
                $normalized['diplomes_academiques'] = $source ?: ($compil ?: ($normalized['diplomes'] ?? $normalized['diplome'] ?? null));
            }
        }
        // Compléter diplômes avec OPJ / DTG si présent
        $opjVal = trim((string)($normalized['opj_dtg'] ?? $normalized['opj'] ?? ''));
        if (!empty($opjVal) && !in_array(mb_strtolower($opjVal), ['nan', 'null', '-', 'none', '0'])) {
            if (!empty($normalized['diplomes_academiques'])) {
                if (mb_stripos($normalized['diplomes_academiques'], $opjVal) === false) {
                    $normalized['diplomes_academiques'] .= ' | ' . $opjVal;
                }
            } else {
                $normalized['diplomes_academiques'] = $opjVal;
            }
        }

        // Inférence du statut si absent ou actif
        if (!isset($normalized['statut']) || trim((string)$normalized['statut']) === '' || $normalized['statut'] === 'actif') {
            $pos = mb_strtolower(trim((string)($normalized['position_actuelle'] ?? '')));
            $reg = mb_strtolower(trim((string)($normalized['region'] ?? '')));
            $san = mb_strtolower(trim((string)($normalized['status_sanitaire'] ?? '')));

            $isReintegre = str_contains($pos, 'réintégr') || str_contains($pos, 'reintegr');

            if (!$isReintegre && (str_contains($pos, 'desert') || str_contains($pos, 'désert') || str_contains($pos, 'dessertion') || str_contains($san, 'desert') || str_contains($san, 'désert'))) {
                $normalized['statut'] = 'déserteur';
            } elseif (str_contains($pos, 'detache') || str_contains($pos, 'détach') || str_contains($reg, 'detache') || str_contains($reg, 'détach')) {
                $normalized['statut'] = 'détaché';
                if (empty($normalized['position_actuelle'])) {
                    $normalized['position_actuelle'] = 'Détachement';
                }
            } elseif (str_contains($pos, 'décéd') || str_contains($pos, 'deced') || str_contains($pos, 'mort')) {
                $normalized['statut'] = 'décédé';
            } elseif (str_contains($pos, 'retrait') || str_contains($pos, 'radi')) {
                $normalized['statut'] = 'radié';
            } elseif (str_contains($pos, 'stage') || str_contains($pos, 'formation')) {
                $normalized['statut'] = 'en stage';
            } elseif (str_contains($pos, 'mission')) {
                $normalized['statut'] = 'en mission';
            } else {
                $normalized['statut'] = 'actif';
            }
        }

        // Inférence est_blesse_guerre si non spécifié
        if (empty($normalized['est_blesse_guerre'])) {
            $san = mb_strtolower(trim((string)($normalized['status_sanitaire'] ?? '')));
            if (preg_match('/bl[eéè]ss/iu', $san) || (str_contains($san, 'guerre') && !str_contains($san, 'ecole de guerre')) || str_contains($san, 'opex') || str_contains($san, 'amput')) {
                $normalized['est_blesse_guerre'] = 1;
            }
        }
        // Traitement intelligent engagement_ops
        $rawOps = trim((string)($normalized['ops'] ?? ''));
        $rawEng = trim((string)($normalized['engagement'] ?? ''));
        if (in_array(mb_strtolower($rawOps), ['0', '0 ops', 'none', 'null', '-', ''])) $rawOps = '';
        if (in_array(mb_strtolower($rawEng), ['0', 'none', 'null', '-', ''])) $rawEng = '';

        if (!empty($rawOps) && !empty($rawEng)) {
            if (mb_stripos($rawEng, $rawOps) !== false) {
                $normalized['engagement_ops'] = $rawEng;
            } elseif (mb_stripos($rawOps, $rawEng) !== false) {
                $normalized['engagement_ops'] = $rawOps;
            } else {
                $normalized['engagement_ops'] = $rawOps . ', ' . $rawEng;
            }
        } elseif (!empty($rawOps)) {
            $normalized['engagement_ops'] = $rawOps;
        } elseif (!empty($rawEng)) {
            $normalized['engagement_ops'] = $rawEng;
        }

        // Nettoyage status_sanitaire
        if (!empty($normalized['status_sanitaire'])) {
            $sanitaireClean = preg_replace('/\s+/', ' ', trim((string)$normalized['status_sanitaire']));
            $sanitaireLower = mb_strtolower($sanitaireClean);
            if (in_array($sanitaireLower, ['#n/a', 'nan', 'null', 'none', '-', '', 'apte', 'bon', 'ras', 'apte médical', 'apte medical'])) {
                $normalized['status_sanitaire'] = 'Apte';
            } else {
                $normalized['status_sanitaire'] = $sanitaireClean;
                if (str_contains($sanitaireLower, 'bless')) {
                    $normalized['est_blesse_guerre'] = 1;
                }
            }
        } else {
            $normalized['status_sanitaire'] = 'Apte';
        }

        // Nettoyage decorations
        if (!empty($normalized['decorations'])) {
            $normalized['decorations'] = preg_replace('/\s+/', ' ', trim((string)$normalized['decorations']));
            $normalized['decorations'] = trim($normalized['decorations'], " ,\t\n\r\0\x0B");
        }

        // Aliases pour date d'entrée en service (EG = Élève Gendarme, G-A = Gendarme Adjoint)
        if (!isset($normalized['date_entree_service']) || trim((string)$normalized['date_entree_service']) === '') {
            $normalized['date_entree_service'] = $normalized['eg'] ?? $normalized['g_a'] ?? $normalized['date_recrutement'] ?? null;
        }

        // Résolution date dernière promotion depuis les colonnes de grades
        if (!isset($normalized['date_derniere_promotion']) || trim((string)$normalized['date_derniere_promotion']) === '') {
            $gradeKey = mb_strtolower(trim((string)($normalized['grade_actuel'] ?? '')));
            if (!empty($normalized[$gradeKey])) {
                $normalized['date_derniere_promotion'] = $normalized[$gradeKey];
            } else {
                foreach (['acm', 'adc', 'adj', 'mdc', 'mdl', 'eg'] as $gk) {
                    if (!empty($normalized[$gk])) {
                        $normalized['date_derniere_promotion'] = $normalized[$gk];
                        break;
                    }
                }
            }
        }

        // Parsing des colonnes certificats (CIA, BA1, BA2, CTA2, OPJ)
        foreach (['cia', 'ba1', 'ba2', 'cat1', 'cat2', 'cta2', 'ct2', 'bmp1', 'bmp2'] as $certKey) {
            if (!empty($normalized[$certKey])) {
                $certVal = trim((string)$normalized[$certKey]);
                $certLower = mb_strtolower($certVal);
                if (!in_array($certLower, ['0', 'non', 'false', '-', 'none', 'null', ''])) {
                    $targetKey = $certKey === 'cta2' ? 'ct2' : $certKey;
                    $normalized['a_fait_' . $targetKey] = 1;
                    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $certVal, $ym)) {
                        $normalized['date_obtention_' . $targetKey] = $ym[1] . '-01-01';
                    }
                }
            }
        }

        return $normalized;
    }

    protected function normalizeStringForMatch(string $str): string
    {
        $str = mb_strtolower($str);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $str);
            if ($converted !== false) {
                $str = $converted;
            }
        }
        $str = preg_replace('/\b(de|la|du|des|d|le|l)\b/i', '', $str);
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        return trim($str);
    }

    protected function resolveOperationalAndTerritorial(Militaire $militaire, array $row): void
    {
        // 1. Région
        if (!empty($row['region'])) {
            $regRaw = trim((string)$row['region']);
            $regStr = mb_strtolower($regRaw);

            // Direct exact match sur code ou nom
            $reg = $this->getRegionsCache()->first(function ($r) use ($regStr) {
                return mb_strtolower($r->code) === $regStr || mb_strtolower($r->nom) === $regStr;
            });

            // Regex: REGION N°X -> RX
            if (!$reg && preg_match('/region\s*(?:n°|no|numéro|num|#)?\s*(\d+)/i', $regRaw, $m)) {
                $reg = $this->getRegionsCache()->firstWhere('code', 'R' . $m[1]);
            }

            // Structures centrales basées à Bamako (DGGN, CDURM, SIJ, CDECF, GSIGN)
            if (!$reg && in_array(strtoupper($regRaw), ['DGGN', 'CDURM', 'SIJ', 'CDECF', 'GSIGN', 'BAMAKO', 'DISTRICT'])) {
                $reg = $this->getRegionsCache()->firstWhere('code', 'R1');
            }

            // Fallback recherche sous-chaîne
            if (!$reg) {
                $reg = $this->getRegionsCache()->first(function ($r) use ($regStr) {
                    return mb_stripos(mb_strtolower($r->nom), $regStr) !== false;
                });
            }

            if ($reg) {
                $militaire->region_id = $reg->id;
                $militaire->region = $reg->nom;
            } else {
                $militaire->region = $regRaw;
            }
        }

        // 2. Unité
        if (!empty($row['unite'])) {
            $unitRaw = trim((string)$row['unite']);
            $unitStr = mb_strtolower($unitRaw);

            // 1er niveau: Match exact sur nom ou code
            $unite = $this->getUnitesCache()->first(function ($u) use ($unitStr) {
                return mb_strtolower($u->nom) === $unitStr || ($u->code && mb_strtolower($u->code) === $unitStr);
            });

            // 2ème niveau: Match normalisé (sans stop-words "de", "la", ponctuation)
            if (!$unite) {
                $normTarget = $this->normalizeStringForMatch($unitRaw);
                $unite = $this->getUnitesCache()->first(function ($u) use ($normTarget) {
                    return $this->normalizeStringForMatch($u->nom) === $normTarget
                        || ($u->code && $this->normalizeStringForMatch($u->code) === $normTarget);
                });
            }

            // 3ème niveau: Match sous-chaîne priorisant la région du militaire
            if (!$unite) {
                $candidates = $this->getUnitesCache()->filter(function ($u) use ($unitStr) {
                    return mb_stripos(mb_strtolower($u->nom), $unitStr) !== false
                        || mb_stripos($unitStr, mb_strtolower($u->nom)) !== false;
                });

                if ($militaire->region_id) {
                    $unite = $candidates->firstWhere('region_id', $militaire->region_id) ?? $candidates->first();
                } else {
                    $unite = $candidates->first();
                }
            }

            if ($unite) {
                $militaire->unite_id = $unite->id;
                $militaire->unite = $unite->nom;
                if ($unite->region_id) {
                    $militaire->region_id = $unite->region_id;
                    $reg = $this->getRegionsCache()->firstWhere('id', $unite->region_id);
                    if ($reg) $militaire->region = $reg->nom;
                }
            } else {
                $militaire->unite = $unitRaw;
            }
        }

        // 2b. Organisme de détachement (si militaire en détachement)
        if ($militaire->statut === 'détaché' || mb_strtolower(trim((string)($row['region'] ?? ''))) === 'detachement') {
            $orgTarget = $this->normalizeStringForMatch(!empty($row['unite']) ? (string)$row['unite'] : '');
            if (!empty($orgTarget)) {
                $org = $this->getOrganismesDetachementCache()->first(function ($o) use ($orgTarget) {
                    $normO = $this->normalizeStringForMatch($o->nom);
                    return $normO === $orgTarget
                        || mb_stripos($normO, $orgTarget) !== false
                        || mb_stripos($orgTarget, $normO) !== false
                        || ($o->code && mb_strtolower($o->code) === $orgTarget);
                });
                if ($org) {
                    $militaire->organisme_detachement_id = $org->id;
                    $militaire->statut = 'détaché';
                    if (empty($militaire->position_actuelle) || $militaire->position_actuelle === 'À l\'Unité / Au Poste') {
                        $militaire->position_actuelle = 'Détachement';
                    }
                }
            }
        }

        // 3. Théâtre d'opérations
        if (!empty($row['theatre'])) {
            $thStr = mb_strtolower(trim((string)$row['theatre']));
            $theatre = $this->getTheatresCache()->first(function ($t) use ($thStr) {
                return mb_strtolower($t->code) === $thStr
                    || mb_strtolower($t->nom) === $thStr
                    || mb_stripos(mb_strtolower($t->nom), $thStr) !== false;
            });
            if ($theatre) {
                $militaire->theatre_id = $theatre->id;
            }
        }

        // 4. Blessé de guerre
        if (isset($row['est_blesse_guerre'])) {
            $militaire->est_blesse_guerre = $this->parseBool($row['est_blesse_guerre']) ? 1 : 0;
        }
    }

    protected function handleBlessureRegistre(Militaire $militaire, array $row): void
    {
        if ($militaire->est_blesse_guerre) {
            $dateBlessure = $this->parseDate($row['date_blessure'] ?? null) ?? now()->toDateString();
            $circonstances = !empty($row['circonstances_blessure']) 
                ? trim((string)$row['circonstances_blessure']) 
                : 'Blessure subie au combat en opération';

            \App\Models\RegistreOperationnel::updateOrCreate(
                [
                    'militaire_id' => $militaire->id,
                    'type_registre_code' => \App\Models\RegistreOperationnel::TYPE_BLESSE,
                ],
                [
                    'matricule' => $militaire->matricule,
                    'mle_grm' => $militaire->mle_grm,
                    'nom' => $militaire->nom,
                    'prenom' => $militaire->prenom,
                    'grade_ordre' => $militaire->grade_ordre ?? 0,
                    'grade_texte' => $militaire->grade_actuel,
                    'region_id' => $militaire->region_id,
                    'unite_id' => $militaire->unite_id,
                    'date_evenement' => $dateBlessure,
                    'circonstances' => $circonstances,
                ]
            );
        }
    }

    protected function createMilitaire(array $rawRow): Militaire
    {
        $row = $this->normalizeRow($rawRow);

        $militaire = new Militaire();
        $militaire->matricule = trim((string)$row['matricule']);
        $militaire->mle_grm = !empty($row['mle_grm']) ? trim((string)$row['mle_grm']) : null;
        $militaire->numero_info = !empty($row['numero_info']) ? trim((string)$row['numero_info']) : null;
        $militaire->numero_nina = !empty($row['numero_nina']) ? trim((string)$row['numero_nina']) : null;
        $militaire->nom = trim((string)($row['nom'] ?? ''));
        $militaire->prenom = trim((string)($row['prenom'] ?? ''));
        $militaire->date_naissance = $this->parseDate($row['date_naissance'] ?? null);
        $militaire->lieu_naissance = !empty($row['lieu_naissance']) ? trim((string)$row['lieu_naissance']) : null;
        $militaire->date_entree_service = $this->parseDate($row['date_entree_service'] ?? null);
        $militaire->grade_actuel = $this->parseGrade($row['grade_actuel'] ?? null);
        $militaire->categorie = !empty($row['categorie']) ? trim((string)$row['categorie']) : null;
        $militaire->date_derniere_promotion = $this->parseDate($row['date_derniere_promotion'] ?? null);
        $militaire->specialite = !empty($row['specialite']) ? trim((string)$row['specialite']) : null;
        $militaire->origine_formation = !empty($row['origine_formation']) ? trim((string)$row['origine_formation']) : null;
        $militaire->statut = $this->parseStatut($row['statut'] ?? null);
        $militaire->telephone = !empty($row['telephone']) ? trim((string)$row['telephone']) : null;
        $militaire->sexe = $this->parseSexe($row['sexe'] ?? null);
        $militaire->groupe_sanguin = !empty($row['groupe_sanguin']) ? trim((string)$row['groupe_sanguin']) : null;
        $militaire->taille = !empty($row['taille']) ? trim((string)$row['taille']) : null;
        $militaire->status_sanitaire = !empty($row['status_sanitaire']) ? trim((string)$row['status_sanitaire']) : null;
        $militaire->engagement_ops = !empty($row['engagement_ops']) ? trim((string)$row['engagement_ops']) : null;
        $militaire->personne_a_contacter = !empty($row['personne_a_contacter']) ? trim((string)$row['personne_a_contacter']) : null;
        $militaire->telephone_personne_contacter = !empty($row['telephone_personne_contacter']) ? trim((string)$row['telephone_personne_contacter']) : null;
        $militaire->position_actuelle = !empty($row['position_actuelle']) ? trim((string)$row['position_actuelle']) : null;
        $militaire->unite = !empty($row['unite']) ? trim((string)$row['unite']) : null;
        $militaire->region = !empty($row['region']) ? trim((string)$row['region']) : null;
        $militaire->date_mutation = $this->parseDate($row['date_mutation'] ?? null);
        $militaire->fonction_passee = !empty($row['fonction_passee']) ? trim((string)$row['fonction_passee']) : null;
        $militaire->fonction_actuelle = !empty($row['fonction_actuelle']) ? trim((string)$row['fonction_actuelle']) : null;
        $militaire->a_permis_conduire = $this->parseBool($row['a_permis_conduire'] ?? false);
        $militaire->antecedent_judiciaire = $this->parseBool($row['antecedent_judiciaire'] ?? false);
        $militaire->antecedent_disciplinaire = $this->parseBool($row['antecedent_disciplinaire'] ?? false);
        $militaire->decorations = !empty($row['decorations']) ? trim((string)$row['decorations']) : null;
        $militaire->diplomes_academiques = !empty($row['diplomes_academiques']) ? trim((string)$row['diplomes_academiques']) : null;
        $militaire->observations = !empty($row['observations']) ? trim((string)$row['observations']) : null;
        $militaire->date_deces = $this->parseDate($row['date_deces'] ?? null);
        $militaire->circonstances_deces = !empty($row['circonstances_deces']) ? trim((string)$row['circonstances_deces']) : null;
        $militaire->motif_radiation = !empty($row['motif_radiation']) ? trim((string)$row['motif_radiation']) : null;
        $militaire->reference_decision = !empty($row['reference_decision']) ? trim((string)$row['reference_decision']) : null;
        $militaire->unite_transfert = !empty($row['unite_transfert']) ? trim((string)$row['unite_transfert']) : null;
        $militaire->motif_transfert = !empty($row['motif_transfert']) ? trim((string)$row['motif_transfert']) : null;
        $this->resolveOperationalAndTerritorial($militaire, $row);
        $militaire->save();
        $this->handleBlessureRegistre($militaire, $row);

        return $militaire;
    }

    protected function updateMilitaire(Militaire $militaire, array $rawRow): Militaire
    {
        $row = $this->normalizeRow($rawRow);

        if (!empty($row['mle_grm'])) $militaire->mle_grm = trim((string)$row['mle_grm']);
        if (!empty($row['numero_info'])) $militaire->numero_info = trim((string)$row['numero_info']);
        if (!empty($row['numero_nina'])) $militaire->numero_nina = trim((string)$row['numero_nina']);
        if (!empty($row['nom'])) $militaire->nom = trim((string)$row['nom']);
        if (!empty($row['prenom'])) $militaire->prenom = trim((string)$row['prenom']);

        $dateNaissance = $this->parseDate($row['date_naissance'] ?? null);
        if ($dateNaissance) $militaire->date_naissance = $dateNaissance;
        if (!empty($row['lieu_naissance'])) $militaire->lieu_naissance = trim((string)$row['lieu_naissance']);

        $dateEntree = $this->parseDate($row['date_entree_service'] ?? null);
        if ($dateEntree) $militaire->date_entree_service = $dateEntree;

        if (!empty($row['grade_actuel'])) $militaire->grade_actuel = $this->parseGrade($row['grade_actuel']);
        if (!empty($row['categorie'])) $militaire->categorie = trim((string)$row['categorie']);

        $datePromo = $this->parseDate($row['date_derniere_promotion'] ?? null);
        if ($datePromo) $militaire->date_derniere_promotion = $datePromo;

        if (!empty($row['specialite'])) $militaire->specialite = trim((string)$row['specialite']);
        if (!empty($row['origine_formation'])) $militaire->origine_formation = trim((string)$row['origine_formation']);
        if (!empty($row['statut'])) $militaire->statut = $this->parseStatut($row['statut']);
        if (!empty($row['telephone'])) $militaire->telephone = trim((string)$row['telephone']);
        if (!empty($row['sexe'])) $militaire->sexe = $this->parseSexe($row['sexe']);
        if (!empty($row['groupe_sanguin'])) $militaire->groupe_sanguin = trim((string)$row['groupe_sanguin']);
        if (!empty($row['taille'])) $militaire->taille = trim((string)$row['taille']);
        if (!empty($row['status_sanitaire'])) $militaire->status_sanitaire = trim((string)$row['status_sanitaire']);
        if (!empty($row['engagement_ops'])) $militaire->engagement_ops = trim((string)$row['engagement_ops']);
        if (!empty($row['personne_a_contacter'])) $militaire->personne_a_contacter = trim((string)$row['personne_a_contacter']);
        if (!empty($row['telephone_personne_contacter'])) $militaire->telephone_personne_contacter = trim((string)$row['telephone_personne_contacter']);
        if (!empty($row['position_actuelle'])) $militaire->position_actuelle = trim((string)$row['position_actuelle']);
        if (!empty($row['unite'])) $militaire->unite = trim((string)$row['unite']);
        if (!empty($row['region'])) $militaire->region = trim((string)$row['region']);

        $dateMutation = $this->parseDate($row['date_mutation'] ?? null);
        if ($dateMutation) $militaire->date_mutation = $dateMutation;

        if (!empty($row['fonction_passee'])) $militaire->fonction_passee = trim((string)$row['fonction_passee']);
        if (!empty($row['fonction_actuelle'])) $militaire->fonction_actuelle = trim((string)$row['fonction_actuelle']);

        if (isset($row['a_permis_conduire'])) $militaire->a_permis_conduire = $this->parseBool($row['a_permis_conduire']);
        if (isset($row['antecedent_judiciaire'])) $militaire->antecedent_judiciaire = $this->parseBool($row['antecedent_judiciaire']);
        if (isset($row['antecedent_disciplinaire'])) $militaire->antecedent_disciplinaire = $this->parseBool($row['antecedent_disciplinaire']);

        if (!empty($row['decorations'])) $militaire->decorations = trim((string)$row['decorations']);
        if (!empty($row['diplomes_academiques'])) $militaire->diplomes_academiques = trim((string)$row['diplomes_academiques']);
        if (!empty($row['observations'])) $militaire->observations = trim((string)$row['observations']);

        $dateDeces = $this->parseDate($row['date_deces'] ?? null);
        if ($dateDeces) $militaire->date_deces = $dateDeces;
        if (!empty($row['circonstances_deces'])) $militaire->circonstances_deces = trim((string)$row['circonstances_deces']);
        if (!empty($row['motif_radiation'])) $militaire->motif_radiation = trim((string)$row['motif_radiation']);
        if (!empty($row['reference_decision'])) $militaire->reference_decision = trim((string)$row['reference_decision']);
        if (!empty($row['unite_transfert'])) $militaire->unite_transfert = trim((string)$row['unite_transfert']);
        if (!empty($row['motif_transfert'])) $militaire->motif_transfert = trim((string)$row['motif_transfert']);

        $this->resolveOperationalAndTerritorial($militaire, $row);

        if ($militaire->isDirty()) {
            $militaire->save();
        }
        $this->handleBlessureRegistre($militaire, $row);

        return $militaire;
    }

    /**
     * Collecter les données de certificats pour une insertion groupée (bulk upsert)
     */
    protected function collectCertificatesForMilitaire(Militaire $militaire, array $row, array &$pivotRows, string $now): void
    {
        $certificatsCache = $this->getCertificatsCache();

        foreach (self::CERTIFICATE_MAPPING as $key => $nomCertificat) {
            $colFait = 'a_fait_' . $key;
            $colDate = 'date_obtention_' . $key;

            if (!isset($row[$colFait]) || !$this->parseBool($row[$colFait])) {
                continue;
            }

            $certificat = $certificatsCache->get(mb_strtolower(trim($nomCertificat)))
                ?? $certificatsCache->get(mb_strtolower(trim($key)));
            if (!$certificat) {
                continue;
            }

            $dateObtention = $this->parseDate($row[$colDate] ?? null);

            $pivotRows[] = [
                'militaire_id' => $militaire->id,
                'certificat_id' => $certificat->id,
                'date_obtention' => $dateObtention ? $dateObtention->toDateString() : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    public function rules(): array
    {
        return [
            'matricule' => 'required',
            'nom' => 'nullable',
            'prenom' => 'nullable',
            'date_naissance' => 'nullable',
            'lieu_naissance' => 'nullable',
            'date_entree_service' => 'nullable',
            'grade_actuel' => 'nullable',
            'date_derniere_promotion' => 'nullable',
            'specialite' => 'nullable',
            'statut' => 'nullable',
            'sexe' => 'nullable',
            'telephone' => 'nullable',
            'groupe_sanguin' => 'nullable',
            'taille' => 'nullable',
            'personne_a_contacter' => 'nullable',
            'telephone_personne_contacter' => 'nullable',
            'position_actuelle' => 'nullable',
            'fonction_passee' => 'nullable',
            'fonction_actuelle' => 'nullable',
            'a_permis_conduire' => 'nullable',
            'antecedent_judiciaire' => 'nullable',
            'antecedent_disciplinaire' => 'nullable',
            // Certificats sous-officiers
            'a_fait_cat1' => 'nullable',
            'date_obtention_cat1' => 'nullable',
            'a_fait_cat2' => 'nullable',
            'date_obtention_cat2' => 'nullable',
            'a_fait_cia' => 'nullable',
            'date_obtention_cia' => 'nullable',
            'a_fait_ba1' => 'nullable',
            'date_obtention_ba1' => 'nullable',
            'a_fait_ba2' => 'nullable',
            'date_obtention_ba2' => 'nullable',
            'a_fait_bmp1' => 'nullable',
            'date_obtention_bmp1' => 'nullable',
            'a_fait_bmp2' => 'nullable',
            'date_obtention_bmp2' => 'nullable',
            'a_fait_bs' => 'nullable',
            'date_obtention_bs' => 'nullable',
            'a_fait_ct2' => 'nullable',
            'date_obtention_ct2' => 'nullable',
            // Formations officiers
            'a_fait_apli' => 'nullable',
            'date_obtention_apli' => 'nullable',
            'a_fait_cfcu' => 'nullable',
            'date_obtention_cfcu' => 'nullable',
            'a_fait_cpo' => 'nullable',
            'date_obtention_cpo' => 'nullable',
            'a_fait_cem' => 'nullable',
            'date_obtention_cem' => 'nullable',
            'a_fait_certificat_etat_major' => 'nullable',
            'date_obtention_certificat_etat_major' => 'nullable',
            'a_fait_ecole_guerre' => 'nullable',
            'date_obtention_ecole_guerre' => 'nullable',
        ];
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function getDuplicates(): array
    {
        return $this->duplicates;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    public function getImportedCount(): int
    {
        return $this->summary['created'];
    }

    public function getSkippedCount(): int
    {
        return $this->summary['skipped'];
    }

    /**
     * Retourne les en-têtes de colonnes pour le modèle d'import
     */
    public static function getTemplateHeaders(): array
    {
        return [
            // Informations personnelles
            'matricule',
            'mle_grm',
            'nom',
            'prenom',
            'date_naissance',
            'lieu_naissance',
            'sexe',
            'telephone',
            'groupe_sanguin',
            'taille',
            'personne_a_contacter',
            'telephone_personne_contacter',
            // Informations professionnelles
            'date_entree_service',
            'grade_actuel',
            'date_derniere_promotion',
            'specialite',
            'statut',
            // Maillage territorial & Déploiement opérationnel
            'region',
            'unite',
            'theatre',
            'position_actuelle',
            'fonction_passee',
            'fonction_actuelle',
            // Statut sanitaire de guerre
            'est_blesse_guerre',
            'circonstances_blessure',
            'date_blessure',
            // Permis et justice
            'a_permis_conduire',
            'antecedent_judiciaire',
            'antecedent_disciplinaire',
            // Certificats sous-officiers
            'a_fait_cat1',
            'date_obtention_cat1',
            'a_fait_cat2',
            'date_obtention_cat2',
            'a_fait_cia',
            'date_obtention_cia',
            'a_fait_ba1',
            'date_obtention_ba1',
            'a_fait_ba2',
            'date_obtention_ba2',
            'a_fait_bmp1',
            'date_obtention_bmp1',
            'a_fait_bmp2',
            'date_obtention_bmp2',
            'a_fait_bs',
            'date_obtention_bs',
            'a_fait_ct2',
            'date_obtention_ct2',
            // Formations officiers
            'a_fait_apli',
            'date_obtention_apli',
            'a_fait_cfcu',
            'date_obtention_cfcu',
            'a_fait_cpo',
            'date_obtention_cpo',
            'a_fait_cem',
            'date_obtention_cem',
            'a_fait_certificat_etat_major',
            'date_obtention_certificat_etat_major',
            'a_fait_ecole_guerre',
            'date_obtention_ecole_guerre',
        ];
    }

    /**
     * Retourne un exemple de données pour le modèle d'import
     */
    public static function getTemplateExampleRow(): array
    {
        return [
            // Informations personnelles
            'MAT-001',
            '12345',
            'DUPONT',
            'Jean',
            '15/03/1990',
            'Bamako',
            'Masculin',
            '77000000',
            'A+',
            '1.78 m',
            'Marie Dupont',
            '77111111',
            // Informations professionnelles
            '01/09/2010',
            'Sergent',
            '15/06/2020',
            'Infanterie',
            'actif',
            // Maillage territorial & Déploiement opérationnel
            'Région de Mopti',
            'Brigade Territoriale de Sévaré',
            'TH-CTR',
            'En activité',
            'Chef de poste',
            'Chef de patrouille',
            // Statut sanitaire de guerre
            '1',
            'Blessé par éclat lors d\'une embuscade',
            '15/04/2024',
            // Permis et justice
            '1',
            '0',
            '0',
            // Certificats sous-officiers
            '1', '15/03/2015',  // cat1
            '1', '20/06/2017',  // cat2
            '0', '',            // cia
            '1', '10/01/2018',  // ba1
            '0', '',            // ba2
            '1', '05/09/2019',  // bmp1
            '0', '',            // bmp2
            '0', '',            // bs
            '0', '',            // ct2
            // Formations officiers
            '0', '',            // apli
            '0', '',            // cfcu
            '0', '',            // cpo
            '0', '',            // cem
            '0', '',            // certificat_etat_major
            '0', '',            // ecole_guerre
        ];
    }
}
