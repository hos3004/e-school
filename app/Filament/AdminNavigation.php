<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Navigation\NavigationGroup;
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
use Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;
use Modules\Notifications\Presentation\Filament\Resources\NotificationPreferenceResource;
use Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource;
use Modules\Organization\Presentation\Filament\Resources\AcademicCalendarFilamentResource;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;
use Modules\Organization\Presentation\Filament\Resources\OrganizationFilamentResource;
use Modules\Payroll\Presentation\Filament\Resources\PayrollEntryResource;
use Modules\Payroll\Presentation\Filament\Resources\PayrollPeriodResource;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;
use Modules\Reporting\Presentation\Filament\Pages\OperationalReports;
use Modules\Reporting\Presentation\Filament\Resources\OrganizationSnapshotResource;
use Modules\Reporting\Presentation\Filament\Resources\ReportEventLogResource;
use Modules\Reporting\Presentation\Filament\Resources\StudentDashboardResource;
use Modules\Reporting\Presentation\Filament\Resources\TeacherDashboardResource;
use Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Modules\Staff\Presentation\Filament\Pages\TeachersDirectory;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource;
use Modules\Students\Presentation\Filament\Resources\RegistrationFormResource;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Modules\VirtualClassroom\Presentation\Filament\Pages\ClassroomConnectionSettings;

/**
 * بنية تنقّل لوحة الإدارة — خمسة أقسام ومستويان.
 *
 * القسم الذي ينتمي إليه كل مورد يأتي من `getNavigationGroup()` الخاص به، وقيمته
 * محفوظة في ملف ترجمة الموديول. أمّا **الترتيب والتداخل** فمكانهما هنا وحده:
 * لو وُزّعا على ستين ملفًا لعادت الأرقام تتضارب كما كانت قبل 2026-09-02، حين
 * أنتجت الموارد ٢٤ مجموعة بينما كانت اللوحة تعلن عشرًا.
 *
 * الترقيم بكتل مئوية: 1xx اليوم الدراسي · 2xx الأشخاص · 3xx التعلّم ·
 * 4xx التواصل · 5xx التقارير والإدارة. آحاد العقد للأبناء تحت أبيهم.
 */
final class AdminNavigation
{
    /**
     * الأقسام الخمسة بترتيب ظهورها.
     *
     * التسمية تُمرَّر **كإغلاق** لا كنص: `navigationGroups()` تُستدعى مرة واحدة
     * عند تسجيل المزوّد، بينما `getNavigationGroup()` في كل مورد تُستدعى داخل
     * الطلب بعد ضبط لغة المستخدم. تثبيت النص هنا يجعل الطرفين يترجمان بلغتين
     * مختلفتين فلا تتطابق المجموعات — وهو بعينه ما جعل الترتيب المعلن سابقًا
     * كودًا ميتًا. الإغلاق يؤجّل الترجمة إلى لحظة العرض فيتطابق الطرفان.
     *
     * @return list<NavigationGroup>
     */
    public static function groups(): array
    {
        return [
            NavigationGroup::make(static fn (): string => (string) __('dashboard.navigation.daily'))
                ->icon('heroicon-o-calendar-days'),
            NavigationGroup::make(static fn (): string => (string) __('dashboard.navigation.people'))
                ->icon('heroicon-o-users'),
            NavigationGroup::make(static fn (): string => (string) __('dashboard.navigation.learning'))
                ->icon('heroicon-o-academic-cap'),
            NavigationGroup::make(static fn (): string => (string) __('dashboard.navigation.communication'))
                ->icon('heroicon-o-chat-bubble-left-right'),
            NavigationGroup::make(static fn (): string => (string) __('dashboard.navigation.insights'))
                ->icon('heroicon-o-chart-bar-square'),
        ];
    }

    /**
     * يُستدعى على حدث `ServingFilament` (انظر `AdminPanelProvider::boot`)، أي
     * بعد `StartSession` وقبل بناء الشريط: المستخدم واللغة محسومان، وتسميات
     * الأب تُقرأ صحيحة. استدعاؤه أبكر من ذلك يجعله بلا أثر.
     */
    public static function configure(): void
    {
        /*
         * صفحة الدخول تُقلع اللوحة بلا مستخدم، والتعشيش هنا يسأل `canAccess()`
         * التي تصل إلى سياسات تفترض مستخدمًا مسجَّلًا — فترمي
         * «Call to a member function can() on null» وتُسقط صفحة الدخول نفسها.
         * ولا شريط جانبي يُرسم قبل الدخول أصلًا، فالخروج المبكر بلا أثر.
         */
        if (!auth()->check()) {
            return;
        }

        self::orderDailyOperations();
        self::orderPeople();
        self::orderLearning();
        self::orderCommunication();
        self::orderInsightsAndAdministration();
    }

    /** ١ — اليوم الدراسي: ما يفتحه المنسّق كل صباح. */
    private static function orderDailyOperations(): void
    {
        SessionResource::navigationSort(110);
        SessionParticipantResource::navigationSort(111);
        AttendanceFilamentResource::navigationSort(112);
        RecordingResource::navigationSort(113);
        ScheduleResource::navigationSort(120);
        PostponementRequestResource::navigationSort(121);
        SessionReportResource::navigationSort(130);

        if (SessionResource::canAccess()) {
            $sessions = SessionResource::getNavigationLabel();
            SessionParticipantResource::navigationParentItem($sessions);
            AttendanceFilamentResource::navigationParentItem($sessions);
            RecordingResource::navigationParentItem($sessions);
        }

        if (ScheduleResource::canAccess()) {
            PostponementRequestResource::navigationParentItem(
                ScheduleResource::getNavigationLabel(),
            );
        }
    }

    /** ٢ — الأشخاص: الطالب وأسرته والمعلم، ثم ما يترتب على سلوكه. */
    private static function orderPeople(): void
    {
        StudentProfileResource::navigationSort(210);
        EnrollmentResource::navigationSort(211);
        RegistrationApplicationResource::navigationSort(212);
        RegistrationFormResource::navigationSort(213);
        GuardianProfileFilamentResource::navigationSort(220);
        GuardianLinkFilamentResource::navigationSort(221);
        StaffProfileResource::navigationSort(230);
        TeachersDirectory::navigationSort(231);
        GroupResource::navigationSort(240);
        ViolationEventFilamentResource::navigationSort(250);
        DisciplineActionFilamentResource::navigationSort(251);
        ReactivationRequestFilamentResource::navigationSort(252);

        if (StudentProfileResource::canAccess()) {
            $students = StudentProfileResource::getNavigationLabel();
            EnrollmentResource::navigationParentItem($students);
            RegistrationApplicationResource::navigationParentItem($students);
            RegistrationFormResource::navigationParentItem($students);
        }

        if (GuardianProfileFilamentResource::canAccess()) {
            GuardianLinkFilamentResource::navigationParentItem(
                GuardianProfileFilamentResource::getNavigationLabel(),
            );
        }

        if (StaffProfileResource::canAccess()) {
            TeachersDirectory::navigationParentItem(
                StaffProfileResource::getNavigationLabel(),
            );
        }
    }

    /** ٣ — التعلّم: المنهج ثم ما يُقاس به. */
    private static function orderLearning(): void
    {
        ProgramFilamentResource::navigationSort(310);
        LevelFilamentResource::navigationSort(311);
        CourseFilamentResource::navigationSort(312);
        CourseMaterialResource::navigationSort(320);
        AssignmentFilamentResource::navigationSort(330);
        AssessmentResource::navigationSort(340);
        AssessmentAttemptResource::navigationSort(341);
        CertificateFilamentResource::navigationSort(350);
        CertificateTemplateFilamentResource::navigationSort(351);
        BadgeFilamentResource::navigationSort(352);
        BadgeAwardFilamentResource::navigationSort(353);

        if (ProgramFilamentResource::canAccess()) {
            $programs = ProgramFilamentResource::getNavigationLabel();
            LevelFilamentResource::navigationParentItem($programs);
            CourseFilamentResource::navigationParentItem($programs);
        }

        if (AssessmentResource::canAccess() && AssessmentResource::shouldRegisterNavigation()) {
            AssessmentAttemptResource::navigationParentItem(
                AssessmentResource::getNavigationLabel(),
            );
        }

        if (CertificateFilamentResource::canAccess()) {
            $certificates = CertificateFilamentResource::getNavigationLabel();
            CertificateTemplateFilamentResource::navigationParentItem($certificates);
            BadgeFilamentResource::navigationParentItem($certificates);
            BadgeAwardFilamentResource::navigationParentItem($certificates);
        }
    }

    /** ٤ — التواصل: ما يصل إلى الطالب وولي الأمر. */
    private static function orderCommunication(): void
    {
        ConversationResource::navigationSort(410);
        MessageResource::navigationSort(411);
        WhatsappInboundResource::navigationSort(412);
        ClassWallPostResource::navigationSort(420);
        NotificationOutboxResource::navigationSort(430);
        NotificationTemplateResource::navigationSort(431);
        NotificationCategorySettingResource::navigationSort(432);
        NotificationPreferenceResource::navigationSort(433);
        PopupCampaignResource::navigationSort(440);

        if (ConversationResource::canAccess()) {
            $conversations = ConversationResource::getNavigationLabel();
            MessageResource::navigationParentItem($conversations);
            WhatsappInboundResource::navigationParentItem($conversations);
        }

        if (NotificationOutboxResource::canAccess()) {
            $outbox = NotificationOutboxResource::getNavigationLabel();
            NotificationTemplateResource::navigationParentItem($outbox);
            NotificationCategorySettingResource::navigationParentItem($outbox);
            NotificationPreferenceResource::navigationParentItem($outbox);
        }
    }

    /** ٥ — التقارير والإدارة: القراءة أولًا ثم الضبط الذي يندر تغييره. */
    private static function orderInsightsAndAdministration(): void
    {
        OperationalReports::navigationSort(510);
        MonthlyReportResource::navigationSort(511);
        StudentDashboardResource::navigationSort(512);
        TeacherDashboardResource::navigationSort(513);
        OrganizationSnapshotResource::navigationSort(514);
        ReportEventLogResource::navigationSort(515);
        OrganizationFilamentResource::navigationSort(530);
        AcademicCalendarFilamentResource::navigationSort(531);
        HolidayFilamentResource::navigationSort(532);
        UserResource::navigationSort(540);
        RoleResource::navigationSort(541);
        PermissionResource::navigationSort(542);
        IntegrationProviderResource::navigationSort(550);
        IntegrationConnectionResource::navigationSort(551);
        IntegrationWebhookDeliveryResource::navigationSort(552);
        ClassroomConnectionSettings::navigationSort(560);
        AuditLogResource::navigationSort(570);

        if (OperationalReports::canAccess()) {
            $reports = OperationalReports::getNavigationLabel();
            MonthlyReportResource::navigationParentItem($reports);
            StudentDashboardResource::navigationParentItem($reports);
            TeacherDashboardResource::navigationParentItem($reports);
            OrganizationSnapshotResource::navigationParentItem($reports);
            ReportEventLogResource::navigationParentItem($reports);
        }

        if (OrganizationFilamentResource::canAccess()) {
            $organization = OrganizationFilamentResource::getNavigationLabel();
            AcademicCalendarFilamentResource::navigationParentItem($organization);
            HolidayFilamentResource::navigationParentItem($organization);
        }

        if (UserResource::canAccess()) {
            $users = UserResource::getNavigationLabel();
            RoleResource::navigationParentItem($users);
            PermissionResource::navigationParentItem($users);
        }

        if (IntegrationProviderResource::canAccess()) {
            $integrations = IntegrationProviderResource::getNavigationLabel();
            IntegrationConnectionResource::navigationParentItem($integrations);
            IntegrationWebhookDeliveryResource::navigationParentItem($integrations);
        }

        if (!(bool) config('features.payroll')) {
            return;
        }

        PayrollPeriodResource::navigationSort(520);
        PayrollEntryResource::navigationSort(521);

        if (PayrollPeriodResource::canAccess()) {
            PayrollEntryResource::navigationParentItem(
                PayrollPeriodResource::getNavigationLabel(),
            );
        }
    }
}
