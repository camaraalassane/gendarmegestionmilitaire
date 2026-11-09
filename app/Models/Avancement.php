<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avancement extends Model
{
    use HasFactory;

    protected $fillable = [
        'militaire_id',
        'annee',
        'grade_actuel',
        'grade_cible',
        'fonction_actuelle',
        'points_fonction_actuelle',
        'note_rendement',
        'bonus_dernier_emploi',
        'points_diplomes',
        'points_decorations',
        'points_anciennete_grade',
        'jours_punition',
        'malus_punition',
        'annees_restantes_retraite',
        'malus_limite_age',
        'points_limite_age',
        'total_points',
        'rang',
        'observations',
    ];

    protected $casts = [
        'annee' => 'integer',
        'points_fonction_actuelle' => 'float',
        'note_rendement' => 'float',
        'bonus_dernier_emploi' => 'float',
        'points_diplomes' => 'float',
        'points_decorations' => 'float',
        'points_anciennete_grade' => 'float',
        'jours_punition' => 'integer',
        'malus_punition' => 'float',
        'annees_restantes_retraite' => 'integer',
        'malus_limite_age' => 'float',
        'points_limite_age' => 'float',
        'total_points' => 'float',
        'rang' => 'integer',
    ];

    public function militaire(): BelongsTo
    {
        return $this->belongsTo(Militaire::class);
    }

    /**
     * Recalcule automatiquement :
     * - Malus punitions : jours x -0.1 pt
     * - Malus limite d'âge : si reste <= 1 an = -2 pts, si reste 2 ans = -1 pt, sinon 0
     * - Total général
     */
    public function recalculerTotal(): float
    {
        $this->malus_punition = round(($this->jours_punition ?? 0) * 0.1, 2);

        // Règle limite d'âge : <= 1 an = -2 pts, 2 ans = -1 pt
        if ($this->annees_restantes_retraite !== null) {
            if ($this->annees_restantes_retraite <= 1) {
                $this->malus_limite_age = 2.0;
            } elseif ($this->annees_restantes_retraite == 2) {
                $this->malus_limite_age = 1.0;
            } else {
                $this->malus_limite_age = 0.0;
            }
        }

        $total = ($this->note_rendement ?? 0)
            + ($this->points_fonction_actuelle ?? 0)
            + ($this->bonus_dernier_emploi ?? 0)
            + ($this->points_diplomes ?? 0)
            + ($this->points_decorations ?? 0)
            + ($this->points_anciennete_grade ?? 0)
            - ($this->malus_limite_age ?? 0)
            - $this->malus_punition;

        $this->total_points = round(max(0, $total), 2);
        return $this->total_points;
    }
}
