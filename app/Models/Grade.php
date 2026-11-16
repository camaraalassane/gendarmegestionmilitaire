<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom_grade',
        'code_grade',
        'type_grade',
        'ordre',
        'retraite_obligatoire',
    ];

    /**
     * Les attributs ajoutés dynamiquement.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'categorie_statutaire',
    ];

    /**
     * Les attributs qui doivent être convertis (castés).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
        'retraite_obligatoire' => 'integer',
    ];

    /**
     * Obtenir la catégorie statutaire correspondante au grade.
     */
    public function getCategorieStatutaireAttribute(): ?string
    {
        return self::determinerCategorie($this->nom_grade, $this->code_grade, $this->type_grade);
    }

    /**
     * Déterminer automatiquement la catégorie statutaire à partir du grade.
     */
    public static function determinerCategorie(?string $nomGrade, ?string $codeGrade = null, ?string $typeGrade = null): ?string
    {
        if (!$nomGrade && !$codeGrade) {
            return null;
        }

        $cleanNom = mb_strtolower(trim($nomGrade ?? ''));
        $cleanCode = mb_strtoupper(trim($codeGrade ?? ''));
        $cleanType = mb_strtolower(trim($typeGrade ?? ''));

        // 1. Officier Général
        if (in_array($cleanCode, ['GBR', 'GDB', 'GDV', 'GDD', 'GCA', 'GAR'])
            || str_contains($cleanNom, 'général')
            || str_contains($cleanNom, 'general')
            || $cleanType === 'officier général') {
            return 'Officier Général';
        }

        // 2. Officier Supérieur
        if (in_array($cleanCode, ['CDT', 'CES', 'LCL', 'COL', 'CLM'])
            || str_contains($cleanNom, 'colonel-major')
            || str_contains($cleanNom, 'colonel major')
            || str_contains($cleanNom, 'commandant')
            || str_contains($cleanNom, 'chef d\'escadron')
            || str_contains($cleanNom, 'lieutenant-colonel')
            || str_contains($cleanNom, 'colonel')
            || $cleanType === 'officier supérieur') {
            return 'Officier Supérieur';
        }

        // 3. Officier Subalterne
        if (in_array($cleanCode, ['SLT', 'LTN', 'CNE', 'ASP', 'EOA', 'EOA2'])
            || str_contains($cleanNom, 'sous-lieutenant')
            || str_contains($cleanNom, 'lieutenant')
            || str_contains($cleanNom, 'capitaine')
            || str_contains($cleanNom, 'aspirant')
            || str_contains($cleanNom, 'eleve officier')
            || str_contains($cleanNom, 'élève officier')
            || $cleanType === 'officier subalterne') {
            return 'Officier Subalterne';
        }

        // 4. Sous-officier Supérieur
        if (in_array($cleanCode, ['ACM', 'ADC', 'ADJ', 'ADM', 'MAJOR'])
            || str_contains($cleanNom, 'adjudant')
            || str_contains($cleanNom, 'major')) {
            return 'Sous-officier Supérieur';
        }

        // 5. Sous-officier Subalterne
        if (in_array($cleanCode, ['SGT', 'SCH', 'MDL', 'MDC', 'ESOA'])
            || str_contains($cleanNom, 'sergent')
            || str_contains($cleanNom, 'maréchal')
            || str_contains($cleanNom, 'marechal')
            || str_contains($cleanNom, 'eleve sous officier')
            || str_contains($cleanNom, 'élève sous officier')
            || $cleanType === 'sous-officier') {
            return 'Sous-officier Subalterne';
        }

        // 6. Militaire du Rang
        if (in_array($cleanCode, ['SDT1', 'SDT2', 'S1', 'S2', 'CPL', 'CPL-CHEF', 'GA', 'EG', 'SAP1', '1°CL'])
            || str_contains($cleanNom, 'soldat')
            || str_contains($cleanNom, 'caporal')
            || str_contains($cleanNom, 'gendarme adjoint')
            || str_contains($cleanNom, 'sapeur')
            || str_contains($cleanNom, '1ère classe')
            || str_contains($cleanNom, '2ème classe')
            || $cleanType === 'militaire du rang') {
            return 'Militaire du Rang';
        }

        return null;
    }

    /**
     * Vérifie si un grade appartient à la catégorie des Officiers
     */
    public static function isOfficier(?string $nomGrade, ?string $codeGrade = null): bool
    {
        $cat = self::determinerCategorie($nomGrade, $codeGrade);
        return in_array($cat, ['Officier Subalterne', 'Officier Supérieur', 'Officier Général'], true);
    }

    /**
     * Vérifie si un grade appartient à la catégorie des Sous-officiers ou Militaires du rang
     */
    public static function isSousOfficierOuMdr(?string $nomGrade, ?string $codeGrade = null): bool
    {
        return !self::isOfficier($nomGrade, $codeGrade);
    }

    /**
     * Cache statique en mémoire pour éviter les requêtes répétitives N+1
     */
    protected static ?array $retraiteCache = null;
    protected static ?\Illuminate\Database\Eloquent\Collection $allGradesCache = null;

    public static function clearCache(): void
    {
        self::$retraiteCache = null;
        self::$allGradesCache = null;
    }

    protected static function booted(): void
    {
        static::saved(fn() => self::clearCache());
        static::deleted(fn() => self::clearCache());
    }

    public static function getRetraiteForGrade(?string $nomGrade): ?int
    {
        if (!$nomGrade || trim($nomGrade) === '') return null;
        $nomGrade = trim($nomGrade);

        if (self::$retraiteCache === null) {
            self::$retraiteCache = [];
            $all = self::all(['nom_grade', 'code_grade', 'type_grade', 'retraite_obligatoire']);
            foreach ($all as $g) {
                if ($g->retraite_obligatoire !== null) {
                    $age = (int) $g->retraite_obligatoire;
                    if ($g->nom_grade) {
                        self::$retraiteCache[trim($g->nom_grade)] = $age;
                        self::$retraiteCache[mb_strtolower(trim($g->nom_grade))] = $age;
                    }
                    if ($g->code_grade) {
                        self::$retraiteCache[trim($g->code_grade)] = $age;
                        self::$retraiteCache[mb_strtolower(trim($g->code_grade))] = $age;
                        self::$retraiteCache[mb_strtoupper(trim($g->code_grade))] = $age;
                    }
                }
            }
        }

        // 1. Recherche exacte
        if (isset(self::$retraiteCache[$nomGrade])) {
            return self::$retraiteCache[$nomGrade];
        }

        // 2. Recherche insensible à la casse
        $lower = mb_strtolower($nomGrade);
        if (isset(self::$retraiteCache[$lower])) {
            return self::$retraiteCache[$lower];
        }

        // 3. Recherche partielle dans le cache (ex: "Soldat 2ème classe" contient "soldat 2")
        foreach (self::$retraiteCache as $key => $age) {
            if ($key !== '' && (str_contains($lower, $key) || str_contains($key, $lower))) {
                return $age;
            }
        }

        // 4. Fallback statutaire par catégorie militaire si le grade n'est pas dans la table
        $categorie = self::determinerCategorie($nomGrade);
        return match ($categorie) {
            'Militaire du Rang' => 50,
            'Sous-officier Subalterne' => 53,
            'Sous-officier Supérieur' => 56,
            'Officier Subalterne' => 60,
            'Officier Supérieur' => 62,
            'Officier Général' => 64,
            default => null,
        };
    }

    public static function getAllCached()
    {
        if (self::$allGradesCache === null) {
            self::$allGradesCache = self::orderBy('ordre')->get();
        }
        return self::$allGradesCache;
    }

    /**
     * Obtenir les militaires associés à ce grade.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function militaires(): HasMany
    {
        return $this->hasMany(Militaire::class, 'grade_id');
    }
}