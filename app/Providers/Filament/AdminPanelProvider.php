<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\AccessControl\Presentation\Filament\Resources\PermissionResource;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource;
use Modules\Audit\Presentation\Filament\Resources\AuditLogResource;
use Modules\Guardians\Presentation\Filament\Resources\GuardianLinkFilamentResource;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Modules\Organization\Presentation\Filament\Resources\AcademicCalendarFilamentResource;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;
use Modules\Organization\Presentation\Filament\Resources\OrganizationFilamentResource;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('app.name'))
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->resources([
                \Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource::class,
                \Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource::class,
                \Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource::class,
                \Modules\AccessControl\Presentation\Filament\Resources\PermissionResource::class,
                \Modules\AccessControl\Presentation\Filament\Resources\RoleResource::class,
                \Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource::class,
                \Modules\Audit\Presentation\Filament\Resources\AuditLogResource::class,
                \Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource::class,
                \Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource::class,
                \Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource::class,
                \Modules\Groups\Presentation\Filament\Resources\GroupResource::class,
                \Modules\Guardians\Presentation\Filament\Resources\GuardianLinkFilamentResource::class,
                \Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource::class,
                \Modules\Identity\Presentation\Filament\Resources\Users\UserResource::class,
                \Modules\Organization\Presentation\Filament\Resources\AcademicCalendarFilamentResource::class,
                \Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource::class,
                \Modules\Organization\Presentation\Filament\Resources\OrganizationFilamentResource::class,
                \Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource::class,
                \Modules\Sessions\Presentation\Filament\Resources\SessionResource::class,
                \Modules\Staff\Presentation\Filament\Resources\StaffProfileResource::class,
                \Modules\Students\Presentation\Filament\Resources\StudentProfileResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
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
            ]);
    }
}
