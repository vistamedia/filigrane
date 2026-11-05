<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Où se trouve un dessin : les cartons et les pièces de la maison, dans
 * l'ordre où on les parcourt. « Autre » s'accompagne d'une précision libre.
 */
enum Location: string implements HasLabel
{
    case Box1 = 'box_1';
    case Box2 = 'box_2';
    case Box3 = 'box_3';
    case LiseBedroom = 'lise_bedroom';
    case MaxBedroom = 'max_bedroom';
    case LivingRoom = 'living_room';
    case DiningRoom = 'dining_room';
    case LargeStudy = 'large_study';
    case SmallStudy = 'small_study';
    case Hallway = 'hallway';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Box1 => 'Carton 1',
            self::Box2 => 'Carton 2',
            self::Box3 => 'Carton 3',
            self::LiseBedroom => 'Chambre Lise',
            self::MaxBedroom => 'Chambre Max',
            self::LivingRoom => 'Grand salon',
            self::DiningRoom => 'Salle à manger',
            self::LargeStudy => 'Grand bureau',
            self::SmallStudy => 'Petit bureau',
            self::Hallway => 'Couloir',
            self::Other => 'Autre',
        };
    }

    /**
     * Ce qu'on affiche : le nom de l'emplacement, ou la précision saisie pour
     * « Autre » — « grenier » se lit mieux que « Autre ».
     */
    public static function libelle(self|string|null $emplacement, ?string $precision = null): ?string
    {
        $emplacement = is_string($emplacement) ? self::tryFrom($emplacement) : $emplacement;

        if ($emplacement === self::Other && filled($precision)) {
            return trim($precision);
        }

        return $emplacement?->getLabel();
    }
}
