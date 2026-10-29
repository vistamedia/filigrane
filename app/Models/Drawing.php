<?php

namespace App\Models;

use App\Enums\DimensionsSource;
use App\Enums\DrawingStatus;
use App\Observers\DrawingObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'inventory_number',
    'artist',
    'period',
    'description',
    'note',
    'width_mm',
    'height_mm',
    'dimensions_source',
    'status',
    'cover_photo_id',
    'described_at',
])]
#[ObservedBy([DrawingObserver::class])]
class Drawing extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => DrawingStatus::class,
            'dimensions_source' => DimensionsSource::class,
            'described_at' => 'datetime',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
        ];
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<Photo, $this> */
    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * La photo à afficher : celle choisie comme couverture, sinon la première.
     */
    public function displayPhoto(): ?Photo
    {
        return $this->coverPhoto ?? $this->photos()->first();
    }

    /**
     * « 32,5 × 24 cm (estimée) », ou null si les dimensions sont inconnues.
     */
    public function dimensionsLabel(): ?string
    {
        if (! $this->width_mm || ! $this->height_mm) {
            return null;
        }

        $format = fn (int $mm): string => rtrim(rtrim(number_format($mm / 10, 1, ',', ' '), '0'), ',');

        $label = sprintf('%s × %s cm', $format($this->height_mm), $format($this->width_mm));

        return $this->dimensions_source
            ? $label.' ('.$this->dimensions_source->shortLabel().')'
            : $label;
    }

    /**
     * Titre lisible d'une fiche, même quand rien n'est encore renseigné.
     */
    public function title(): string
    {
        $parts = array_filter([$this->artist, $this->period]);

        return $parts === []
            ? $this->inventory_number
            : $this->inventory_number.' — '.implode(', ', $parts);
    }

    /** @param  Builder<static>  $query */
    public function scopeToDescribe(Builder $query): void
    {
        $query->where('status', DrawingStatus::ToDescribe);
    }

    /**
     * Les fiches déjà commentées : celles qui portent vraiment un texte.
     *
     * Le filtre SQL dégrossit — l'éditeur enregistre volontiers « <p></p> »
     * pour un champ qu'on a seulement effleuré — et TexteRiche::estVide()
     * tranche les cas restants côté PHP.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCommented(Builder $query): void
    {
        $query->whereNotNull('description')
            ->whereRaw("TRIM(description) <> ''")
            ->whereRaw("TRIM(description) NOT IN ('<p></p>', '<p><br></p>')");
    }

    /**
     * Les fiches dont au moins un cliché est à refaire : la liste de travail
     * de la prochaine séance de prise de vue.
     *
     * @param  Builder<static>  $query
     */
    public function scopeNeedsReshoot(Builder $query): void
    {
        $query->whereHas('photos', fn (Builder $photos) => $photos->whereNotNull('reshoot_reason'));
    }
}
