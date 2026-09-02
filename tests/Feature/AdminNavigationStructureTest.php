<?php

declare(strict_types=1);

use App\Filament\AdminNavigation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;
use Modules\Reporting\Presentation\Filament\Pages\OperationalReports;
use Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Tests\TestCase;

/**
 * حارس بنية التنقّل: خمسة أقسام لا أكثر، ولا عنصر خارجها، والتعشيش قائم.
 *
 * سبب وجوده مقاس لا ذوقي. حتى 2026-09-02 كانت اللوحة تعلن عشر مجموعات في
 * `AdminPanelProvider` بينما كل مورد يُرجع تسمية من ملف ترجمة موديوله. لم
 * تتطابق تسمية واحدة، فصار الترتيب المعلن كودًا ميتًا وانفرط الشريط إلى
 * **٢٤ مجموعة** لـ٥٨ عنصرًا، تسع منها بعنصر واحد.
 *
 * العطب من النوع الذي لا يُسقط أي اختبار قائم: كل صفحة تفتح، وكل صلاحية تعمل،
 * والشريط وحده فوضى. لذلك يُقاس هنا الناتجُ المعروض لا نية الكود.
 */
final class AdminNavigationStructureTest extends TestCase
{
    use RefreshDatabase;

    /** أسماء المجموعات الخمس بترتيب ظهورها. */
    private const SECTION_KEYS = [
        'dashboard.navigation.daily',
        'dashboard.navigation.people',
        'dashboard.navigation.learning',
        'dashboard.navigation.communication',
        'dashboard.navigation.insights',
    ];

    public function test_the_sidebar_renders_the_five_sections_in_order(): void
    {
        $html = $this->adminHtml();

        $positions = [];

        foreach (self::SECTION_KEYS as $key) {
            $label = (string) __($key);
            $position = mb_strpos($html, $label);

            $this->assertNotFalse(
                $position,
                "قسم «{$label}» غير معروض في الشريط الجانبي.",
            );

            $positions[$label] = $position;
        }

        $sorted = $positions;
        asort($sorted);

        $this->assertSame(
            array_keys($positions),
            array_keys($sorted),
            'ترتيب الأقسام المعروض يخالف الترتيب المعلن في AdminNavigation::groups().',
        );
    }

    /**
     * كل تسمية مجموعة قديمة صارت اليوم بلا مالك. ظهور أي منها يعني أن موديولًا
     * أفلت من إعادة التوزيع وأنشأ قسمًا سادسًا.
     */
    public function test_no_legacy_group_label_survives(): void
    {
        $html = $this->adminHtml();

        $legacy = [
            'الأكاديميات', 'الشؤون الأكاديمية', 'القيود والشؤون', 'الأسر والأوصياء',
            'الهوية والحسابات', 'التحكم بالوصول', 'الحضور والانضباط', 'التعلّم والتقييم',
            'الاختبارات والتقييم', 'المحتوى التعليمي', 'الشهادات والشارات',
            'التقارير الأكاديمية', 'الحوكمة والأمان', 'التكاملات', 'الموظفون',
        ];

        $survivors = array_values(array_filter(
            $legacy,
            static fn (string $label): bool => mb_strpos($html, $label) !== false,
        ));

        $this->assertSame(
            [],
            $survivors,
            "تسميات مجموعات ملغاة ما زالت تظهر:\n".implode("\n", $survivors),
        );
    }

    /**
     * أي مورد يُرجع مجموعة خارج الأقسام الخمسة — أو لا يُرجع مجموعة أصلًا —
     * يظهر في الشريط بلا عنوان فوقه. سجل التدقيق كان كذلك بالضبط.
     */
    public function test_every_resource_belongs_to_one_of_the_five_sections(): void
    {
        $allowed = array_map(static fn (string $key): string => (string) __($key), self::SECTION_KEYS);

        $strays = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (!$resource::shouldRegisterNavigation()) {
                continue;
            }

            $group = $resource::getNavigationGroup();

            if (is_string($group) && in_array($group, $allowed, true)) {
                continue;
            }

            $strays[] = $resource.' => '.var_export($group, true);
        }

        $this->assertSame(
            [],
            $strays,
            "موارد خارج الأقسام الخمسة:\n".implode("\n", $strays),
        );
    }

    /**
     * التعشيش هو ما يختصر ٥٨ صفًا إلى ٢٤. لو سقط عاد الشريط قائمة مسطّحة طويلة
     * دون أن يفشل أي اختبار آخر.
     *
     * الخانة الساكنة `$navigationParentItem` معلنة في كل ابن على حدة؛ لولا ذلك
     * لتشاركها كل الموارد عبر صنف Filament الأب فيدهس آخرُ إسناد ما قبله —
     * وهذا ما جعل «قيود الحضور» تُعشَّش تحت «فترات المستحقات» أول مرة.
     */
    public function test_child_items_point_at_their_intended_parent(): void
    {
        /*
         * الطلب الحقيقي هو المقياس، لا استدعاء `AdminNavigation::configure()`
         * يدويًا. النسخة الأولى من هذا الاختبار كانت تستدعيه بنفسها فنجحت بينما
         * الشريط في المتصفح مسطّح تمامًا: الضبط كان معلّقًا على `bootUsing` الذي
         * يسبق حسم الجلسة، فلا يُطبَّق شيء. الاختبار الذي يهيّئ ما يقيسه لا يقيس.
         */
        $this->adminHtml();

        $expected = [
            SessionParticipantResource::class => SessionResource::class,
            AttendanceFilamentResource::class => SessionResource::class,
            RecordingResource::class => SessionResource::class,
            PostponementRequestResource::class => ScheduleResource::class,
            EnrollmentResource::class => StudentProfileResource::class,
            MonthlyReportResource::class => OperationalReports::class,
            RoleResource::class => UserResource::class,
        ];

        $wrong = [];

        foreach ($expected as $child => $parent) {
            $actual = $child::getNavigationParentItem();
            $wanted = $parent::getNavigationLabel();

            if ($actual === $wanted) {
                continue;
            }

            $wrong[] = $child.' => '.var_export($actual, true).' (المتوقع: '.$wanted.')';
        }

        $this->assertSame($wrong, [], "تعشيش خاطئ:\n".implode("\n", $wrong));
    }

    /**
     * Filament يرمي استثناءً عند عرض عنصر أب بلا أيقونة، فتسقط اللوحة كلها.
     * الاختبار يمسك ذلك قبل المتصفح.
     */
    public function test_every_parent_item_has_an_icon(): void
    {
        $parents = [
            SessionResource::class,
            ScheduleResource::class,
            StudentProfileResource::class,
            UserResource::class,
            OperationalReports::class,
        ];

        foreach ($parents as $parent) {
            $this->assertNotNull(
                $parent::getNavigationIcon(),
                $parent.' أبٌ بلا أيقونة — Filament يرفض عرضه.',
            );
        }
    }

    /**
     * صفحة الدخول تُقلع اللوحة بلا مستخدم. ضبط التنقّل يسأل `canAccess()` التي
     * تصل إلى سياسات تفترض مستخدمًا مسجَّلًا، فكان الإقلاع يُسقط صفحة الدخول
     * بـ500 — والعميل يصطدم بها قبل أن يرى شيئًا. بقية الاختبارات لا تمسك هذا
     * لأنها كلها تعمل باسم مستخدم.
     */
    public function test_the_login_page_renders_for_a_guest(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    private function adminHtml(): string
    {
        Gate::before(static fn (): bool => true);

        $content = $this->actingAs($this->panelUser())->get('/admin')->getContent();

        return is_string($content) ? $content : '';
    }

    private function panelUser(): User
    {
        $organizationId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'nav-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->inOrganization($organizationId)->create([
            'email' => 'panel.navigation@example.test',
        ]);
    }
}
