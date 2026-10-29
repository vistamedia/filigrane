<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DrawingStatus: string implements HasColor, HasIcon, HasLabel
{
    case ToDescribe = 'to_describe';
    case InProgress = 'in_progress';
    case Described = 'described';

    public function getLabel(): string
    {
        return match ($this) {
            self::ToDescribe => 'À décrire',
            self::InProgress => 'En cours',
            self::Described => 'Décrit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ToDescribe => 'warning',
            self::InProgress => 'info',
            self::Described => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ToDescribe => 'heroicon-o-inbox',
            self::InProgress => 'heroicon-o-pencil',
            self::Described => 'heroicon-o-check-circle',
        };
    }
}
