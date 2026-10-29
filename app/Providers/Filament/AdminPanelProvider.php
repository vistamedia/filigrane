<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\CollectionProgress;
use App\Filament\Widgets\LatestDrawings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // Le panneau occupe la racine du domaine : l'application installée
            // sur l'écran d'accueil s'ouvre directement sur la collection.
            ->path('')
            ->login()
            ->profile(isSimple: false)
            ->brandName('Filigrane')
            ->brandLogo(fn (): Htmlable => new HtmlString(Blade::render('<x-filigrane-logo class="h-9" />')))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('icones/favicon.svg'))
            ->colors([
                // Sanguine : la craie rouge des dessins anciens. Un seul accent
                // chaud, pour que les reproductions en noir et blanc dominent.
                //
                // La gamme est écrite à la main plutôt que dérivée de la teinte
                // par Filament : la dérivation automatique sature la couleur et
                // donne un saumon vif, très loin de la craie. La nuance 600,
                // celle des boutons, atteint 6,8:1 sur blanc — au-delà du seuil
                // AA, ce qui compte pour une lectrice âgée.
                'primary' => [
                    50 => '#FDF7F4',
                    100 => '#F8EBE5',
                    200 => '#EFD5C9',
                    300 => '#E0B5A2',
                    400 => '#CA8E75',
                    500 => '#A3553E',
                    600 => '#8E462F',
                    700 => '#743827',
                    800 => '#5D2E21',
                    900 => '#4C271D',
                    950 => '#2A1410',
                ],
                // Gris chauds : la teinte du papier ancien plutôt que l'acier.
                'gray' => Color::Stone,
                // Le rouge franc reste réservé aux actions destructrices : il ne
                // doit jamais se confondre avec la couleur de la marque.
                'danger' => Color::Red,
                'info' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Collection'),
                NavigationGroup::make('Prise de vue'),
                NavigationGroup::make('Administration'),
            ])
            ->navigationItems([
                NavigationItem::make('À propos')
                    ->url(fn (): string => route('a-propos'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->group('Administration')
                    ->sort(99),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                CollectionProgress::class,
                LatestDrawings::class,
            ])
            // Déclaration de l'application installable (manifeste, icônes iOS,
            // enregistrement du service worker).
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('pwa.head')->render(),
            )
            // Sous le formulaire de connexion : la présentation de
            // l'application, seule page lisible sans compte.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.lien-a-propos')->render(),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
