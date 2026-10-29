<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PhotoKind: string implements HasLabel
{
    case Recto = 'recto';
    case Verso = 'verso';
    case Detail = 'detail';
    case Frame = 'frame';
    case Document = 'document';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recto => 'Recto',
            self::Verso => 'Verso',
            self::Detail => 'Détail',
            self::Frame => 'Encadrement',
            self::Document => 'Document joint',
        };
    }
}
