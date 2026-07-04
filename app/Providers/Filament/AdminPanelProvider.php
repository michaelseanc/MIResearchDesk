<?php

namespace App\Providers\Filament;

use App\Filament\Widgets;
use App\Http\Middleware\SetOrganizationContext;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Monument Independent Research Desk')
            // Uses the uploaded logo once it exists; falls back to the brand name until then.
            ->brandLogo(fn (): ?string => file_exists(public_path('images/mi-research-desk-logo.png'))
                ? asset('images/mi-research-desk-logo.png')
                : null)
            ->brandLogoHeight('9rem')
            // Wider sidebar so the enlarged (wide) logo renders at full size instead of being clipped.
            ->sidebarWidth('22rem')
            ->favicon(fn (): ?string => file_exists(public_path('images/mi-favicon.png'))
                ? asset('images/mi-favicon.png')
                : null)
            ->login()
            ->profile()
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: true, // mandatory 2FA for all staff
            )
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                'Research',
                'Campaign finance',
                'Newsroom',
                'Settings',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                Widgets\NewsroomStats::class,
                Widgets\StoriesInProgress::class,
                Widgets\FollowUpsDue::class,
            ])
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
                SetOrganizationContext::class,
            ])
            // Keep the profile-photo uploader's edit/remove controls off the face until hover.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
                    <style>
                        /*
                         * Hide the profile-photo uploader's controls until hover. Covers both the
                         * standard FilePond action buttons AND the avatar-mode edit pencil
                         * (.filepond--action-edit-item-alt), which is a separate, absolutely-
                         * positioned button. !important is needed because FilePond sets opacity inline.
                         */
                        /* Most controls (incl. the ×) hide cleanly via opacity. */
                        .mi-photo-upload .filepond--file-action-button {
                            opacity: 0 !important;
                            pointer-events: none !important;
                            transition: opacity .15s ease !important;
                        }
                        .mi-photo-upload:hover .filepond--file-action-button,
                        .mi-photo-upload:focus-within .filepond--file-action-button {
                            opacity: 1 !important;
                            pointer-events: auto !important;
                        }
                        /*
                         * FilePond's image-edit plugin re-drives the edit (pencil) button's
                         * opacity/visibility every frame, so those can't hide it. It does NOT
                         * touch display — toggling that is the reliable way to hide it until hover.
                         */
                        .mi-photo-upload .filepond--action-edit-item { display: none !important; }
                        .mi-photo-upload:hover .filepond--action-edit-item,
                        .mi-photo-upload:focus-within .filepond--action-edit-item { display: block !important; }

                        /*
                         * The brand logo was enlarged (brandLogoHeight) for legibility. The sidebar
                         * header (where the logo lives on desktop) has a fixed height, so give it
                         * vertical room to fit the taller logo instead of clipping it.
                         */
                        .fi-sidebar-header {
                            height: auto !important;
                            min-height: 10rem !important;
                        }
                        /* Let the logo use the full (widened) sidebar width without distortion. */
                        .fi-sidebar-header .fi-logo,
                        .fi-sidebar-header .fi-logo img { max-width: 100%; height: auto; }
                        /* On the mobile top bar the logo also appears — keep it from overflowing there. */
                        @media (max-width: 1024px) {
                            .fi-topbar .fi-logo,
                            .fi-topbar .fi-logo img { max-height: 2.75rem; width: auto; }
                        }
                    </style>
                    HTML,
            );
    }
}
