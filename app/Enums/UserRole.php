<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Curator = 'curator';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Curator => 'Conservateur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Accès complet : comptes, outils de prise de vue, import en masse, corbeille.',
            self::Curator => 'Catalogage : création, description et classement des dessins.',
        };
    }
}
