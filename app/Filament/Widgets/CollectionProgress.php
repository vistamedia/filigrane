<?php

namespace App\Filament\Widgets;

use App\Enums\DrawingStatus;
use App\Models\Drawing;
use App\Models\Photo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * L'avancement du catalogage, en un coup d'œil : combien de dessins sont
 * entrés, combien restent à décrire, où en est le fonds.
 */
class CollectionProgress extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Drawing::count();
        $aDecrire = Drawing::where('status', DrawingStatus::ToDescribe)->count();
        $decrits = Drawing::where('status', DrawingStatus::Described)->count();
        $part = $total > 0 ? round($decrits / $total * 100) : 0;

        return [
            Stat::make('Dessins au catalogue', number_format($total, 0, ',', ' '))
                ->description(Photo::count().' photographies')
                ->descriptionIcon('heroicon-o-photo')
                ->color('gray'),

            Stat::make('À décrire', number_format($aDecrire, 0, ',', ' '))
                ->description($aDecrire > 0 ? 'Le travail du jour' : 'Rien en attente')
                ->descriptionIcon('heroicon-o-inbox')
                ->color($aDecrire > 0 ? 'warning' : 'success'),

            Stat::make('Décrits', number_format($decrits, 0, ',', ' '))
                ->description($part.' % du fonds')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
