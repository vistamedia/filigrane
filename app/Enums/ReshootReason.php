<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pourquoi un cliché est à refaire. Le motif dit aussi comment s'y prendre la
 * fois suivante : on ne prépare pas de la même façon une vitre qui reflète et
 * un étage mal éclairé.
 */
enum ReshootReason: string implements HasLabel
{
    case Reflections = 'reflections';
    case Blur = 'blur';
    case Dark = 'dark';
    case Framing = 'framing';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reflections => 'Reflets dans la vitre',
            self::Blur => 'Flou',
            self::Dark => 'Trop sombre',
            self::Framing => 'Œuvre coupée',
            self::Other => 'Autre raison',
        };
    }
}
