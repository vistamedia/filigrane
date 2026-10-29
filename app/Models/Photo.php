<?php

namespace App\Models;

use App\Enums\PhotoKind;
use App\Observers\PhotoObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'drawing_id',
    'kind',
    'caption',
    'original_path',
    'original_name',
    'mime',
    'size_bytes',
    'width_px',
    'height_px',
    'web_path',
    'thumb_path',
    'exif',
    'taken_at',
    'calibration_id',
    'px_per_mm',
    'sheet_box',
    'sort_order',
    'processed_at',
    'processing_error',
])]
#[ObservedBy([PhotoObserver::class])]
class Photo extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => PhotoKind::class,
            'exif' => 'array',
            'sheet_box' => 'array',
            'taken_at' => 'datetime',
            'processed_at' => 'datetime',
            'px_per_mm' => 'float',
            'size_bytes' => 'integer',
            'width_px' => 'integer',
            'height_px' => 'integer',
        ];
    }

    /**
     * Une photographie appartient toujours à sa fiche, y compris lorsque
     * celle-ci est à la corbeille. Sans `withTrashed()`, la relation renverrait
     * null dès que la fiche est supprimée en douceur, et toutes les pages qui
     * l'affichent tomberaient en erreur.
     *
     * @return BelongsTo<Drawing, $this>
     */
    public function drawing(): BelongsTo
    {
        return $this->belongsTo(Drawing::class)->withTrashed();
    }

    /** @return BelongsTo<Calibration, $this> */
    public function calibration(): BelongsTo
    {
        return $this->belongsTo(Calibration::class);
    }

    /**
     * Miniature servie en statique par Apache. Null tant que le traitement n'a
     * pas eu lieu (import en file d'attente).
     */
    public function thumbUrl(): ?string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : null;
    }

    public function webUrl(): ?string
    {
        return $this->web_path ? Storage::disk('public')->url($this->web_path) : null;
    }

    /**
     * L'original ne sort jamais du dossier privé : il passe par une route
     * authentifiée.
     */
    public function originalUrl(): string
    {
        return route('photos.original', $this);
    }

    public function originalExists(): bool
    {
        return Storage::disk('local')->exists($this->original_path);
    }

    /**
     * Signature de la série de prise de vue, utilisée pour retrouver un profil
     * d'échelle compatible (même appareil, même focale, même cadrage).
     */
    public function sessionSignature(): array
    {
        return [
            'camera' => $this->exif['camera'] ?? null,
            'focal_35mm' => $this->exif['focal_35mm'] ?? null,
            'image_width_px' => $this->width_px,
            'image_height_px' => $this->height_px,
        ];
    }

    /**
     * Dimensions de la feuille déduites du cadre détecté et de l'échelle,
     * en millimètres. Null si l'un des deux manque.
     *
     * @return array{width_mm:int, height_mm:int}|null
     */
    public function measuredSheetSize(): ?array
    {
        if (! $this->px_per_mm || ! $this->sheet_box || ! $this->width_px || ! $this->height_px) {
            return null;
        }

        $widthPx = ($this->sheet_box['w'] ?? 0) * $this->width_px;
        $heightPx = ($this->sheet_box['h'] ?? 0) * $this->height_px;

        if ($widthPx <= 0 || $heightPx <= 0) {
            return null;
        }

        return [
            'width_mm' => (int) round($widthPx / $this->px_per_mm),
            'height_mm' => (int) round($heightPx / $this->px_per_mm),
        ];
    }

    /**
     * Supprime les fichiers associés (appelé par l'observateur à la suppression).
     */
    public function deleteFiles(): void
    {
        Storage::disk('local')->delete($this->original_path);

        foreach (array_filter([$this->web_path, $this->thumb_path]) as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
