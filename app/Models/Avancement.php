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
        'note_rendement',
        'bonus_dernier_emploi',
        'points_diplomes',
        'points_decorations',
        'points_anciennete_grade',
        'jours_punition',
        'malus_punition',
        'points_limite_age',
        'total_points',
        'rang',
        'observations',
    ];

    protected $casts = [
        'annee' => 'integer',
        'note_rendement' => 'float',
        'bonus_dernier_emploi' => 'float',
        'points_diplomes' => 'float',
        'points_decorations' => 'float',
        'points_anciennete_grade' => 'float',
        'jours_punition' => 'integer',
        'malus_punition' => 'float',
        'points_limite_age' => 'float',
        'total_points' => 'float',
        'rang' => 'integer',
    ];

    public function militaire(): BelongsTo
    {
        return $this->belongsTo(Militaire::class);
    }

    /**
     * Recalcule automatiquement le malus des punitions (-0.1 pt par jour) et le total général
     */
    public function recalculerTotal(): float
    {
        $this->malus_punition = round(($this->jours_punition ?? 0) * 0.1, 2);

        $total = ($this->note_rendement ?? 0)
            + ($this->bonus_dernier_emploi ?? 0)
            + ($this->points_diplomes ?? 0)
            + ($this->points_decorations ?? 0)
            + ($this->points_anciennete_grade ?? 0)
            + ($this->points_limite_age ?? 0)
            - $this->malus_punition;

        $this->total_points = round(max(0, $total), 2);
        return $this->total_points;
    }
}
