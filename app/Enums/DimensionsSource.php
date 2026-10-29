<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DimensionsSource: string implements HasColor, HasLabel
{
    /** Calculées depuis la photo via une échelle de référence. */
    case Estimated = 'estimated';

    /** Relevées physiquement au mètre ou au pied à coulisse. */
    case Measured = 'measured';

    /** Saisies à la main, provenance inconnue. */
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Estimated => 'Estimée depuis la photo',
            self::Measured => 'Mesurée',
            self::Manual => 'Saisie à la main',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Estimated => 'warning',
            self::Measured => 'success',
            self::Manual => 'gray',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Estimated => 'estimée',
            self::Measured => 'mesurée',
            self::Manual => 'saisie',
        };
    }
}
