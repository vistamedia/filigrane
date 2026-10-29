<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'family'])]
class Tag extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            $tag->slug = Str::slug($tag->name);
        });
    }

    /** @return BelongsToMany<Drawing, $this> */
    public function drawings(): BelongsToMany
    {
        return $this->belongsToMany(Drawing::class);
    }
}
