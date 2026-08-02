<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\View\Components\ImpersonationBanner;
use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->profile(isSimple: false)
            ->default()
            ->passwordReset()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            // ->breadcrumbs(false)
            ->userMenuItems([
                'logout' => MenuItem::make()->label('Log out'),
                'profile' => MenuItem::make()->label('Edit profile'),
                // ...
            ])
            // ->colors([
            //     'primary' => 'rgb(103, 76, 196)',
            // ])
            // ->renderHook('panels::body.start', fn(): View => view('filament.hooks.background'))
            // ->topNavigation()
            // ->topNavigation(ImpersonationBanner::make())
            // ->topNavigation(function () {
            //     return ImpersonationBanner::make();
            // })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->databaseNotifications()
            ->databaseNotificationsPolling('5s')
            ->brandLogo(asset('assets/images/Logo_Login.png'))
            ->brandLogoHeight('50px')
            ->navigationGroups([
                'Management',
                'Payments',
                'Settings',
            ]);
    }
}
