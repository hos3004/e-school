<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\PlatformOverview;
use App\Filament\Widgets\SessionsTrend;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource;
use Modules\AcademicReports\Presentation\Filament\Resources\SessionReportResource;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;
use Modules\AccessControl\Presentation\Filament\Resources\PermissionResource;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentAttemptResource;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource;
use Modules\Audit\Presentation\Filament\Resources\AuditLogResource;
use Modules\Certificates\Presentation\Filament\Resources\BadgeAwardFilamentResource;
use Modules\Certificates\Presentation\Filament\Resources\BadgeFilamentResource;
use Modules\Certificates\Presentation\Filament\Resources\CertificateFilamentResource;
use Modules\Certificates\Presentation\Filament\Resources\CertificateTemplateFilamentResource;
use Modules\Content\Presentation\Filament\Resources\CourseMaterialResource;
use Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource;
use Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;
use Modules\Guardians\Presentation\Filament\Resources\GuardianLinkFilamentResource;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationConnectionResource;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationProviderResource;
use Modules\Integrations\Presentation\Filament\Resources\IntegrationWebhookDeliveryResource;
use Modules\Messaging\Presentation\Filament\Resources\ClassWallPostResource;
use Modules\Messaging\Presentation\Filament\Resources\ConversationResource;
use Modules\Messaging\Presentation\Filament\Resources\MessageResource;
use Modules\Messaging\Presentation\Filament\Resources\WhatsappInboundResource;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;
use Modules\Notifications\Presentation\Filament\Resources\NotificationPreferenceResource;
use Modules\Organization\Presentation\Filament\Resources\AcademicCalendarFilamentResource;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;
use Modules\Organization\Presentation\Filament\Resources\OrganizationFilamentResource;
use Modules\Payroll\Presentation\Filament\Resources\PayrollEntryResource;
use Modules\Payroll\Presentation\Filament\Resources\PayrollPeriodResource;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;
use Modules\Reporting\Presentation\Filament\Resources\OrganizationSnapshotResource;
use Modules\Reporting\Presentation\Filament\Resources\ReportEventLogResource;
use Modules\Reporting\Presentation\Filament\Resources\StudentDashboardResource;
use Modules\Reporting\Presentation\Filament\Resources\TeacherDashboardResource;
use Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName(config('app.name'))
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
            ])
            // خط عربي مقروء — الافتراضي لا يدعم العربية جيدًا
            ->font('IBM Plex Sans Arabic')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                'الأكاديمي',
                'الطلاب وأولياء الأمور',
                'الطاقم',
                'التشغيل',
                'التعلّم',
                'الانضباط',
                'التواصل',
                'المال',
                'التقارير',
                'النظام',
            ])
            ->resources([
                MonthlyReportResource::class,
                SessionReportResource::class,
                CourseFilamentResource::class,
                LevelFilamentResource::class,
                ProgramFilamentResource::class,
                PermissionResource::class,
                RoleResource::class,
                AssessmentAttemptResource::class,
                AssessmentResource::class,
                AssignmentFilamentResource::class,
                AttendanceFilamentResource::class,
                AuditLogResource::class,
                BadgeAwardFilamentResource::class,
                BadgeFilamentResource::class,
                CertificateFilamentResource::class,
                CertificateTemplateFilamentResource::class,
                CourseMaterialResource::class,
                DisciplineActionFilamentResource::class,
                ReactivationRequestFilamentResource::class,
                ViolationEventFilamentResource::class,
                EnrollmentResource::class,
                GroupResource::class,
                GuardianLinkFilamentResource::class,
                GuardianProfileFilamentResource::class,
                UserResource::class,
                IntegrationConnectionResource::class,
                IntegrationProviderResource::class,
                IntegrationWebhookDeliveryResource::class,
                ClassWallPostResource::class,
                ConversationResource::class,
                MessageResource::class,
                WhatsappInboundResource::class,
                NotificationOutboxResource::class,
                NotificationPreferenceResource::class,
                AcademicCalendarFilamentResource::class,
                HolidayFilamentResource::class,
                OrganizationFilamentResource::class,
                PayrollEntryResource::class,
                PayrollPeriodResource::class,
                RecordingResource::class,
                OrganizationSnapshotResource::class,
                ReportEventLogResource::class,
                StudentDashboardResource::class,
                TeacherDashboardResource::class,
                PostponementRequestResource::class,
                SessionParticipantResource::class,
                SessionResource::class,
                StaffProfileResource::class,
                RegistrationApplicationResource::class,
                StudentProfileResource::class,
            ])
            // في هذه التركيبة يبدأ Livewire محرّك Alpine قبل تنفيذ سكربتات
            // Filament، فتفوت مستمعاتها حدث alpine:init ولا تُسجَّل مكوّناتها
            // (filamentSchema · filamentActionModals …) فتظهر الويدجتات والنماذج
            // فارغة. نعيد إطلاق الحدث بعد اكتمال التحميل ثم نعيد تهيئة الشجرة.
            // Alpine.data يستبدل بالاسم فلا ازدواج في التسجيل.
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.hooks.alpine-boot'),
            )
            ->widgets([
                PlatformOverview::class,
                NeedsAttention::class,
                SessionsTrend::class,
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
