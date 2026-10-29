<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'label',
    'is_active',
    'camera',
    'focal_35mm',
    'image_width_px',
    'image_height_px',
    'px_per_mm',
    'reference_label',
    'reference_mm',
    'reference_segment',
    'notes',
])]
class Calibration extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'px_per_mm' => 'float',
            'reference_mm' => 'float',
            'reference_segment' => 'array',
            'focal_35mm' => 'integer',
            'image_width_px' => 'integer',
            'image_height_px' => 'integer',
        ];
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Profil applicable à une photo : même appareil, même focale et même
     * cadrage. Sans ces trois conditions, l'échelle ne serait pas valable.
     *
     * @param  Builder<static>  $query
     */
    public function scopeMatching(Builder $query, Photo $photo): void
    {
        $signature = $photo->sessionSignature();

        $query->where('is_active', true)
            ->where('camera', $signature['camera'])
            ->where('focal_35mm', $signature['focal_35mm'])
            ->where('image_width_px', $signature['image_width_px'])
            ->where('image_height_px', $signature['image_height_px'])
            ->latest();
    }

    public function summary(): string
    {
        return sprintf(
            '%s — %s px/mm (référence : %s)',
            $this->label,
            number_format($this->px_per_mm, 2, ',', ' '),
            $this->reference_label ?: 'non précisée',
        );
    }
}
