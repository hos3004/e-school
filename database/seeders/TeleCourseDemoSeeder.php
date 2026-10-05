<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Actions\AssignStudentToGroupAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AcademicReports\Application\Actions\ApproveMonthlyReportAction;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Application\Actions\CreateProgramAction;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Application\Actions\AttachProgramAction;
use Modules\Groups\Application\Actions\CreateGroupAction;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\MaterializeScheduleAction;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\EndSessionAction;
use Modules\Sessions\Application\Actions\StartSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Application\Actions\AssignTeacherQualificationsAction;
use Modules\Staff\Application\Actions\CreateTeacherOnboardingAction;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Application\Actions\CreateRegistrationApplicationAction;
use Modules\Students\Application\Actions\CreateStudentOnboardingAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Domain\Models\StudentProfile;
use Throwable;

/**
 * بيانات عرض واقعية لأكاديمية «تيلي كورس».
 *
 * الفرق عن `DemoDataSeeder`: هذه ليست بيانات عشوائية. الأسماء والأعمار والبلدان
 * والأكواد والمواعيد مكتوبة يدويًا ومتّسقة فيما بينها ومع الموقع التسويقي —
 * ثلاثة برامج هي نفسها المعروضة للزائر، ومعلمون متخصصون في برامجهم، وطلاب
 * أعمارهم تناسب برامجهم.
 *
 * وكل خطوة تمرّ عبر **الـ Action نفسه الذي يستدعيه زر اللوحة**: الطالب يُنشأ
 * بطلب تسجيل يُقدَّم ويُقبل، والتسكين عبر المنسق المركزي، والحصة الماضية تمرّ
 * ببدء وحضور وإنهاء واعتماد. لو انكسر مسار حقيقي انكسرت البذرة معه بدل أن
 * تخفيه ببيانات مصطنعة.
 *
 * المدى الزمني مقصود: ستة أسابيع ماضية تعطي التقارير والمستحقات مادةً، وستة
 * قادمة تملأ الجدول. بيانات اليوم فقط تترك التقارير فارغة مهما كثرت.
 */
final class TeleCourseDemoSeeder extends Seeder
{
    private const PASSWORD = 'Demo!2026Pass';

    private const TIMEZONE = 'Europe/Istanbul';

    /** أسابيع ماضية تُولَّد حصصها وتُعتمد. */
    private const PAST_WEEKS = 6;

    private string $organizationId;

    private string $adminId;

    /** @var array<string, string> iso2 => country_id */
    private array $countries = [];

    /** @var array<string, string> "iso2:اسم المنطقة" => region_id */
    private array $regions = [];

    public function run(): void
    {
        $this->command?->info('تيلي كورس — بناء بيانات العرض الواقعية…');

        $this->loadGeography();
        $organization = $this->organization();
        $this->organizationId = (string) $organization->getKey();
        $admin = $this->admin();
        $this->adminId = (string) $admin->getKey();

        $catalog = $this->catalog();
        $teachers = $this->teachers($catalog);
        $students = $this->students($catalog);
        $groups = $this->groups($catalog, $teachers, $students);

        $this->schedules($groups);
        $delivered = $this->pastSessions($groups);
        $this->sessionReports($delivered);
        $this->monthlyReports($groups);
        $this->pendingApplications($catalog);

        $this->summary();
    }

    // ── الجغرافيا ──────────────────────────────────────────────────────────

    private function loadGeography(): void
    {
        foreach (DB::table('countries')->get(['id', 'iso2']) as $row) {
            $this->countries[(string) $row->iso2] = (string) $row->id;
        }

        $rows = DB::table('regions')
            ->join('countries', 'countries.id', '=', 'regions.country_id')
            ->get(['regions.id', 'regions.name', 'countries.iso2']);

        foreach ($rows as $row) {
            $name = json_decode((string) $row->name, true);
            $ar = is_array($name) ? (string) ($name['ar'] ?? '') : '';
            $this->regions[$row->iso2.':'.$ar] = (string) $row->id;
        }
    }

    private function countryId(string $iso2): string
    {
        return $this->countries[$iso2] ?? throw new \RuntimeException("دولة غير موجودة: {$iso2}");
    }

    /** يقبل اسم المنطقة بالعربية، ويسقط إلى أول منطقة في الدولة عند الاختلاف. */
    private function regionId(string $iso2, string $arabicName): string
    {
        if (isset($this->regions[$iso2.':'.$arabicName])) {
            return $this->regions[$iso2.':'.$arabicName];
        }

        $fallback = DB::table('regions')
            ->where('country_id', $this->countryId($iso2))
            ->orderBy('sort_order')
            ->value('id');

        return (string) $fallback;
    }

    // ── المؤسسة والمدير ────────────────────────────────────────────────────

    private function organization(): Organization
    {
        $organization = Organization::query()->first();

        if (!$organization instanceof Organization) {
            $organization = new Organization;
        }

        $organization->fill([
            'name' => ['ar' => 'أكاديمية تيلي كورس', 'en' => 'Tele Course Academy'],
            'slug' => 'telecourse',
            'default_timezone' => self::TIMEZONE,
            'default_currency' => self::currency(),
            'default_locale' => 'ar',
            'supported_locales' => ['ar', 'en'],
            'week_starts_on' => 'saturday',
        ]);
        $organization->save();

        return $organization;
    }

    private function admin(): User
    {
        // getenv لا env: بعد config:cache تُرجع env() فارغًا، وكلمة مرور المدير
        // أسوأ ما يُترك للصدفة.
        $password = (string) (getenv('DEMO_ADMIN_PASSWORD') ?: self::PASSWORD);

        $admin = User::query()->firstOrNew(['email' => 'admin@telecourse.org']);
        $admin->fill([
            'organization_id' => $this->organizationId,
            'name' => 'إدارة أكاديمية تيلي كورس',
            'username' => 'admin',
            'password' => bcrypt($password),
            'locale' => 'ar',
            'timezone' => self::TIMEZONE,
            'status' => 'active',
            'email_verified_at' => now('UTC'),
        ]);
        $admin->save();

        app(RoleAssignmentGateway::class)->assignIfMissing(
            roleName: 'platform_admin',
            modelType: app(UserQueryService::class)->modelType(),
            modelId: (string) $admin->getKey(),
            organizationId: $this->organizationId,
            actorId: (string) $admin->getKey(),
        );

        $this->command?->info("  المدير: admin@telecourse.org / {$password}");

        return $admin;
    }

    // ── الكتالوج: 3 برامج · 3 مستويات · 3 كورسات ───────────────────────────

    /**
     * عملة المستحقات يفرضها `config('payroll.currency')`؛ أي مبلغ بغيرها
     * يرفضه `RecordPayrollEntryAction` فتبقى الحصص بلا أثر مالي.
     */
    private static function currency(): string
    {
        return (string) config('payroll.currency', 'EGP');
    }

    /** سعر الحصة بالوحدة الكبرى، متناسبًا مع العملة المفعّلة. */
    private static function rate(string $tier): string
    {
        $table = [
            'EGP' => ['quran' => '450.00', 'coding' => '700.00', 'data' => '900.00'],
            'USD' => ['quran' => '9.00', 'coding' => '14.00', 'data' => '18.00'],
            'EUR' => ['quran' => '8.00', 'coding' => '13.00', 'data' => '16.00'],
        ];

        $currency = self::currency();

        return $table[$currency][$tier] ?? $table['EGP'][$tier];
    }

    /** بداية الفصل: نفس اليوم الذي بدأت فيه المجموعات، فلا تتناقض التواريخ. */
    private static function termStart(int $plusWeeks = 0): string
    {
        return CarbonImmutable::now('UTC')
            ->subWeeks(self::PAST_WEEKS)
            ->addWeeks($plusWeeks)
            ->toDateString();
    }

    /**
     * @return array<string, array{program: Program, level: Level, course: Course, rate: string}>
     */
    private function catalog(): array
    {
        $definitions = [
            'quran' => [
                'program' => [
                    'code' => 'P-QURAN',
                    'name' => ['ar' => 'تحفيظ القرآن والتجويد', 'en' => 'Quran Memorisation & Tajweed'],
                    'description' => [
                        'ar' => 'حلقات صغيرة لحفظ القرآن الكريم مع تصحيح التلاوة وتطبيق أحكام التجويد، بمتابعة أسبوعية لولي الأمر.',
                        'en' => 'Small circles for Quran memorisation with recitation correction and applied tajweed, with weekly guardian follow-up.',
                    ],
                    'program_type' => ProgramType::Ongoing->value,
                    'default_session_minutes' => 45,
                    'target_gender' => TargetGender::All->value,
                    'age_from' => 7,
                    'age_to' => 12,
                    'language' => 'ar',
                    'objectives' => [
                        'ar' => ['حفظ جزء عمّ حفظًا متقنًا', 'إتقان مخارج الحروف', 'تطبيق أحكام النون الساكنة والتنوين'],
                    ],
                ],
                'level' => ['code' => 'L-QURAN-1', 'name' => ['ar' => 'المستوى التمهيدي', 'en' => 'Foundation']],
                'course' => [
                    'code' => 'C-QURAN-101',
                    'name' => ['ar' => 'حفظ جزء عمّ مع التجويد التطبيقي', 'en' => 'Juz Amma with Applied Tajweed'],
                    'description' => [
                        'ar' => 'ثمانية وأربعون حصة على مدى ستة أشهر: حفظ متدرّج لسور جزء عمّ، ومراجعة أسبوعية، وتصحيح فردي للتلاوة في كل حصة.',
                        'en' => 'Forty-eight sessions over six months: graded memorisation of Juz Amma with weekly revision and individual recitation correction.',
                    ],
                    'total_sessions' => 48,
                    'session_mode' => SessionMode::Group->value,
                    'age_from' => 7,
                    'age_to' => 12,
                    'target_gender' => TargetGender::All->value,
                    'default_duration_minutes' => 45,
                    'sessions_per_week' => 2,
                ],
                'rate' => self::rate('quran'),
            ],
            'coding' => [
                'program' => [
                    'code' => 'P-CODE',
                    'name' => ['ar' => 'البرمجة والذكاء الاصطناعي للناشئة', 'en' => 'Coding & AI for Teens'],
                    'description' => [
                        'ar' => 'مسار عملي يبدأ من التفكير المنطقي وينتهي بمشروع برمجي كامل يعرضه الطالب، مع أدوات الذكاء الاصطناعي في الاستخدام المسؤول.',
                        'en' => 'A hands-on track from logical thinking to a complete project the student presents, including responsible use of AI tools.',
                    ],
                    'program_type' => ProgramType::FixedDuration->value,
                    'duration_weeks' => 16,
                    'start_date' => self::termStart(),
                    'end_date' => self::termStart(16),
                    'default_session_minutes' => 60,
                    'target_gender' => TargetGender::All->value,
                    'age_from' => 12,
                    'age_to' => 17,
                    'language' => 'ar',
                    'objectives' => [
                        'ar' => ['كتابة برنامج بايثون من الصفر', 'فهم الحلقات والشروط والدوال', 'بناء مشروع نهائي وعرضه'],
                    ],
                ],
                'level' => ['code' => 'L-CODE-1', 'name' => ['ar' => 'المستوى الأول', 'en' => 'Level One']],
                'course' => [
                    'code' => 'C-CODE-201',
                    'name' => ['ar' => 'أساسيات بايثون ومشروع أول', 'en' => 'Python Foundations & First Project'],
                    'description' => [
                        'ar' => 'اثنان وثلاثون حصة: المتغيرات والشروط والحلقات والدوال والملفات، ثم مشروع تطبيقي يختاره الطالب ويعرضه في الحصة الأخيرة.',
                        'en' => 'Thirty-two sessions covering variables, conditionals, loops, functions and files, ending with a student-chosen project.',
                    ],
                    'total_sessions' => 32,
                    'session_mode' => SessionMode::Group->value,
                    'age_from' => 12,
                    'age_to' => 17,
                    'target_gender' => TargetGender::All->value,
                    'default_duration_minutes' => 60,
                    'sessions_per_week' => 2,
                ],
                'rate' => self::rate('coding'),
            ],
            'data' => [
                'program' => [
                    'code' => 'P-SKILL',
                    'name' => ['ar' => 'المهارات المهنية للكبار', 'en' => 'Professional Skills for Adults'],
                    'description' => [
                        'ar' => 'برامج قصيرة تُكسب المتعلّم مهارة قابلة للتوظيف خلال ثلاثة أشهر، بتمارين من بيئة عمل حقيقية.',
                        'en' => 'Short programmes that build an employable skill within three months through real workplace exercises.',
                    ],
                    'program_type' => ProgramType::FixedDuration->value,
                    'duration_weeks' => 12,
                    'start_date' => self::termStart(),
                    'end_date' => self::termStart(12),
                    'default_session_minutes' => 90,
                    'target_gender' => TargetGender::All->value,
                    'age_from' => 18,
                    'age_to' => 60,
                    'language' => 'ar',
                    'objectives' => [
                        'ar' => ['تنظيف البيانات وتجهيزها', 'بناء لوحة مؤشرات تفاعلية', 'عرض النتائج على غير المختصين'],
                    ],
                ],
                'level' => ['code' => 'L-SKILL-1', 'name' => ['ar' => 'المسار التطبيقي', 'en' => 'Applied Track']],
                'course' => [
                    'code' => 'C-DATA-301',
                    'name' => ['ar' => 'تحليل البيانات ولوحات المؤشرات', 'en' => 'Data Analysis & Dashboards'],
                    'description' => [
                        'ar' => 'أربع وعشرون حصة: من جدول بيانات خام إلى لوحة مؤشرات تُعرض على الإدارة، مع تمارين أسبوعية من قطاعات حقيقية.',
                        'en' => 'Twenty-four sessions from raw spreadsheet to a management-ready dashboard, with weekly exercises from real sectors.',
                    ],
                    'total_sessions' => 24,
                    'session_mode' => SessionMode::Group->value,
                    'age_from' => 18,
                    'age_to' => 60,
                    'target_gender' => TargetGender::All->value,
                    'default_duration_minutes' => 90,
                    'sessions_per_week' => 2,
                ],
                'rate' => self::rate('data'),
            ],
        ];

        $definitions['quran_individual'] = [
            'program' => $definitions['quran']['program'],
            'level' => $definitions['quran']['level'],
            'course' => [
                'code' => 'C-QURAN-IND',
                'name' => ['ar' => 'القرآن الفردي', 'en' => 'Individual Quran'],
                'description' => [
                    'ar' => 'حصص فردية لطالب واحد مع معلم مؤهل، بموعد يُختار من إتاحة المعلم ومدد 25 أو 35 أو 55 دقيقة.',
                    'en' => 'One-to-one Quran sessions with a qualified teacher, booked from approved availability for 25, 35, or 55 minutes.',
                ],
                'total_sessions' => 48,
                'session_mode' => SessionMode::Individual->value,
                'age_from' => 13,
                'age_to' => 60,
                'target_gender' => TargetGender::All->value,
                'default_duration_minutes' => 35,
                'sessions_per_week' => 2,
            ],
            'rate' => self::rate('quran'),
        ];

        $catalog = [];

        foreach ($definitions as $key => $definition) {
            $program = Program::query()->where('code', $definition['program']['code'])->first();

            if (!$program instanceof Program) {
                $program = app(CreateProgramAction::class)->execute(
                    [
                        'organization_id' => $this->organizationId,
                        'currency' => self::currency(),
                        'is_active' => true,
                        'sort_order' => count($catalog) + 1,
                        ...$definition['program'],
                    ],
                    $this->adminId,
                    'إنشاء برنامج ضمن كتالوج العرض',
                );
            }

            $level = Level::query()->where('code', $definition['level']['code'])->first()
                ?? Level::query()->create([
                    'program_id' => $program->getKey(),
                    'sort_order' => 1,
                    ...$definition['level'],
                ]);

            $course = Course::query()->where('code', $definition['course']['code'])->first();

            if (!$course instanceof Course) {
                $course = app(CreateCourseAction::class)->execute(
                    [
                        'organization_id' => $this->organizationId,
                        'level_id' => (string) $level->getKey(),
                        'is_active' => true,
                        ...$definition['course'],
                    ],
                    $this->adminId,
                    'إنشاء كورس ضمن كتالوج العرض',
                );
            }

            $catalog[$key] = [
                'program' => $program,
                'level' => $level,
                'course' => $course,
                'rate' => $definition['rate'],
            ];
        }

        return $catalog;
    }

    // ── المعلمون ───────────────────────────────────────────────────────────

    /**
     * @param array<string, array{program: Program, level: Level, course: Course, rate: string}> $catalog
     * @return array<string, list<StaffProfile>> مفتاح الكتالوج => معلموه
     */
    private function teachers(array $catalog): array
    {
        $definitions = [
            ['quran', 'TC-1001', 'عبدالرحمن محمود الشاذلي', 'abdulrahman.shazly', 'male', 'EG', 'القاهرة', '1986-03-14', 'إجازة في رواية حفص عن عاصم، ومعلّم تحفيظ منذ اثني عشر عامًا.', ['تجويد', 'قراءات', 'تحفيظ الصغار']],
            ['quran', 'TC-1002', 'هدى سالم المقبالية', 'huda.almaqbali', 'female', 'OM', 'مسقط', '1990-07-02', 'معلّمة تحفيظ للفتيات، متخصصة في تصحيح التلاوة للمبتدئات.', ['تجويد', 'تحفيظ الفتيات']],
            ['coding', 'TC-1003', 'ياسر عبدالغني حمدان', 'yasser.hamdan', 'male', 'EG', 'الجيزة', '1988-11-21', 'مهندس برمجيات ومدرّب بايثون للناشئة، بنى أكثر من ستين مشروع طلابي.', ['بايثون', 'تفكير حاسوبي', 'مشاريع الناشئة']],
            ['coding', 'TC-1004', 'لينا خالد الحسيني', 'lina.alhusseini', 'female', 'JO', 'العاصمة', '1993-05-09', 'مطوّرة واجهات ومدرّبة، تركّز على تعليم البرمجة بالمشاريع لا بالتلقين.', ['بايثون', 'واجهات', 'الذكاء الاصطناعي التطبيقي']],
            ['data', 'TC-1005', 'مصطفى عمر التازي', 'mustapha.tazi', 'male', 'MA', 'الرباط', '1984-01-30', 'محلل بيانات في قطاع التجزئة، يدرّب على تحويل الأرقام إلى قرار.', ['تحليل بيانات', 'لوحات مؤشرات', 'إكسل متقدم']],
            ['data', 'TC-1006', 'سُمية عبدالله الغامدي', 'sumayyah.alghamdi', 'female', 'SA', 'الرياض', '1991-09-17', 'مختصة ذكاء أعمال، تعمل على تبسيط التقارير للإدارات غير التقنية.', ['ذكاء الأعمال', 'تحليل بيانات', 'عرض النتائج']],
        ];

        $teachers = ['quran' => [], 'coding' => [], 'data' => []];

        foreach ($definitions as [$key, $code, $name, $username, $gender, $iso2, $region, $dob, $bio, $specializations]) {
            $existing = StaffProfile::query()->where('staff_code', $code)->first();

            if ($existing instanceof StaffProfile) {
                $teachers[$key][] = $existing;

                continue;
            }

            $profile = app(CreateTeacherOnboardingAction::class)->execute([
                'account_mode' => 'new',
                'full_name' => $name,
                'email' => $username.'@telecourse.org',
                'phone' => null,
                'username' => $username,
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
                'locale' => 'ar',
                'timezone' => self::TIMEZONE,
                'staff_code' => $code,
                'employment_type' => 'part_time',
                'gender' => $gender,
                'country_id' => $this->countryId($iso2),
                'region_id' => $this->regionId($iso2, $region),
                'date_of_birth' => $dob,
                'hired_at' => CarbonImmutable::now('UTC')->subMonths(8)->toDateString(),
                'bio' => $bio,
                'specializations' => $specializations,
                'contract_basis' => 'per_session',
                'contract_effective_from' => CarbonImmutable::now('UTC')->subMonths(8)->toDateString(),
                'currency' => self::currency(),
                'default_rate_major' => $catalog[$key]['rate'],
                'monthly_target_sessions' => 32,
                'course_ids' => [(string) $catalog[$key]['course']->getKey()],
                'qualification_notes' => 'مؤهَّل على كورس البرنامج بعد مراجعة السيرة والمقابلة.',
                'onboarding_reason' => 'ضم معلّم إلى فريق الأكاديمية ضمن بيانات العرض',
            ], $this->organizationId, $this->adminId);

            $teachers[$key][] = $profile;
        }

        $individualCourseId = (string) $catalog['quran_individual']['course']->getKey();
        foreach ($teachers['quran'] as $profile) {
            app(AssignTeacherQualificationsAction::class)->execute(
                $profile,
                [$individualCourseId],
                $this->adminId,
                'تأهيل معلم القرآن لمسار الحصص الفردية',
                'يمتد اعتماد القرآن الحالي إلى المسار الفردي.',
            );
        }
        $teachers['quran_individual'] = $teachers['quran'];

        return $teachers;
    }

    // ── الطلاب ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, array{program: Program, level: Level, course: Course, rate: string}> $catalog
     * @return array<string, list<StudentProfile>>
     */
    private function students(array $catalog): array
    {
        $definitions = [
            // القرآن — 7 إلى 12 سنة
            ['quran', 'أحمد ياسين عبدالله', 'ahmad.yassin', 'male', '2016-04-11', 'EG', 'القاهرة'],
            ['quran', 'مريم صلاح الدين', 'maryam.salah', 'female', '2015-09-23', 'EG', 'الجيزة'],
            ['quran', 'عمر بن سعيد البلوشي', 'omar.albalushi', 'male', '2017-01-05', 'OM', 'مسقط'],
            ['quran', 'فاطمة الزهراء بن عيسى', 'fatima.benaissa', 'female', '2016-11-30', 'DZ', 'الجزائر'],
            ['quran', 'يوسف محمد الدوسري', 'youssef.aldosari', 'male', '2015-06-18', 'SA', 'الرياض'],
            ['quran', 'خديجة عبدالرحيم', 'khadija.abdelrahim', 'female', '2017-03-02', 'SD', 'الخرطوم'],
            ['quran', 'زيد إبراهيم الحوراني', 'zaid.alhourani', 'male', '2016-08-14', 'JO', 'العاصمة'],
            ['quran', 'رقية أنس المصري', 'ruqayya.almasri', 'female', '2015-12-07', 'PS', 'غزة'],
            ['quran', 'إبراهيم ناصر العجمي', 'ibrahim.alajmi', 'male', '2016-02-25', 'KW', 'العاصمة'],
            ['quran', 'آمنة وليد القحطاني', 'amna.alqahtani', 'female', '2017-05-19', 'QA', 'الدوحة'],

            // البرمجة — 12 إلى 17 سنة
            ['coding', 'محمد طارق شعبان', 'mohamed.shaaban', 'male', '2010-07-08', 'EG', 'الإسكندرية'],
            ['coding', 'سارة عماد الخطيب', 'sara.alkhatib', 'female', '2011-02-16', 'SY', 'دمشق'],
            ['coding', 'عبدالله فهد المطيري', 'abdullah.almutairi', 'male', '2009-10-27', 'SA', 'مكة المكرمة'],
            ['coding', 'ليان أحمد الشامي', 'layan.alshami', 'female', '2010-12-03', 'AE', 'دبى'],
            ['coding', 'كريم مصطفى النجار', 'karim.elnaggar', 'male', '2011-05-21', 'EG', 'القاهرة'],
            ['coding', 'جنى رامي بو حمدان', 'jana.bouhamdan', 'female', '2009-08-09', 'LB', 'بيروت'],
            ['coding', 'أنس هيثم الزعبي', 'anas.alzoubi', 'male', '2010-03-12', 'JO', 'إربد'],
            ['coding', 'ملك سامي بن يوسف', 'malak.benyoussef', 'female', '2011-09-01', 'TN', 'تونس'],
            ['coding', 'حمزة عادل الرشيدي', 'hamza.alrashidi', 'male', '2009-11-15', 'KW', 'حولى'],
            ['coding', 'رهف بدر العنزي', 'rahaf.alanazi', 'female', '2010-06-06', 'SA', 'الشرقية'],

            // البيانات — 18 فما فوق
            ['data', 'خالد سليمان الحربي', 'khaled.alharbi', 'male', '1996-04-02', 'SA', 'الرياض'],
            ['data', 'نورهان أشرف زكي', 'nourhan.zaki', 'female', '1998-01-19', 'EG', 'القاهرة'],
            ['data', 'بلال محمود العلي', 'bilal.alali', 'male', '1994-09-28', 'JO', 'العاصمة'],
            ['data', 'هبة عمر الصايغ', 'heba.alsayegh', 'female', '1997-07-13', 'AE', 'الشارقة'],
            ['data', 'طارق عبدالحميد بن نصر', 'tarek.bennasr', 'male', '1992-11-04', 'TN', 'صفاقس'],
            ['data', 'أسماء يحيى المخلافي', 'asmaa.almikhlafi', 'female', '1999-03-26', 'YE', 'صنعاء'],
            ['data', 'رامي جورج حداد', 'rami.haddad', 'male', '1995-05-30', 'LB', 'بيروت'],
            ['data', 'دعاء إبراهيم العمراني', 'doaa.alamrani', 'female', '1996-12-11', 'MA', 'الرباط'],
            ['data', 'سيف الدين علي بابكر', 'saifeddin.babiker', 'male', '1993-08-22', 'SD', 'الخرطوم'],
            ['data', 'شيماء عبدالقادر بلحاج', 'shaimaa.belhaj', 'female', '1998-10-07', 'DZ', 'وهران'],
        ];

        $students = ['quran' => [], 'coding' => [], 'data' => []];

        foreach ($definitions as [$key, $name, $username, $gender, $dob, $iso2, $region]) {
            $existing = User::query()->where('username', $username)->first();

            if ($existing instanceof User) {
                $profile = StudentProfile::query()->where('user_id', $existing->getKey())->first();

                if ($profile instanceof StudentProfile) {
                    $students[$key][] = $profile;

                    continue;
                }
            }

            $students[$key][] = app(CreateStudentOnboardingAction::class)->execute([
                'account_mode' => 'new',
                'full_name' => $name,
                'email' => $username.'@student.telecourse.org',
                'username' => $username,
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
                'locale' => 'ar',
                'timezone' => self::TIMEZONE,
                'date_of_birth' => $dob,
                'gender' => $gender,
                'country_id' => $this->countryId($iso2),
                'region_id' => $this->regionId($iso2, $region),
                'preferred_program_id' => (string) $catalog[$key]['program']->getKey(),
                'preferred_course_id' => (string) $catalog[$key]['course']->getKey(),
                'nationality' => $iso2,
                'city' => $region,
                'preferred_language' => 'ar',
                'acceptance_reason' => 'قبول الطالب بعد مقابلة تحديد المستوى',
            ], $this->organizationId, $this->adminId);
        }

        return $students;
    }

    // ── المجموعات ──────────────────────────────────────────────────────────

    /**
     * @param array<string, array{program: Program, level: Level, course: Course, rate: string}> $catalog
     * @param array<string, list<StaffProfile>> $teachers
     * @param array<string, list<StudentProfile>> $students
     * @return list<array{group: Group, catalog: string, teacher: StaffProfile, students: list<StudentProfile>, weekdays: list<int>, time: string}>
     */
    private function groups(array $catalog, array $teachers, array $students): array
    {
        $plan = [
            ['quran', 'G-QRN-A', ['ar' => 'حلقة النور — بنين', 'en' => 'Al-Noor Circle — Boys'], 0, 0, 5, [0, 3], '16:00'],
            ['quran', 'G-QRN-B', ['ar' => 'حلقة الفرقان — بنات', 'en' => 'Al-Furqan Circle — Girls'], 1, 5, 5, [1, 4], '17:00'],
            ['coding', 'G-COD-A', ['ar' => 'مختبر بايثون — الفوج الأول', 'en' => 'Python Lab — Cohort A'], 0, 0, 5, [1, 4], '18:30'],
            ['coding', 'G-COD-B', ['ar' => 'مختبر بايثون — الفوج الثاني', 'en' => 'Python Lab — Cohort B'], 1, 5, 5, [2, 5], '19:30'],
            ['data', 'G-DAT-A', ['ar' => 'دفعة البيانات — المسار الصباحي', 'en' => 'Data Cohort — Morning'], 0, 0, 5, [0, 2], '10:00'],
            ['data', 'G-DAT-B', ['ar' => 'دفعة البيانات — المسار المسائي', 'en' => 'Data Cohort — Evening'], 1, 5, 5, [1, 3], '20:00'],
        ];

        $startsOn = CarbonImmutable::now('UTC')->subWeeks(self::PAST_WEEKS);
        $built = [];

        foreach ($plan as [$key, $code, $name, $teacherIndex, $offset, $size, $weekdays, $time]) {
            $teacher = $teachers[$key][$teacherIndex];
            $slice = array_slice($students[$key], $offset, $size);
            $course = $catalog[$key]['course'];
            $program = $catalog[$key]['program'];

            $group = Group::query()->where('code', $code)->first();

            if (!$group instanceof Group) {
                $group = app(CreateGroupAction::class)->execute([
                    'organization_id' => $this->organizationId,
                    'code' => $code,
                    'name' => $name,
                    'capacity' => 8,
                    'timezone' => self::TIMEZONE,
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => $startsOn->addMonths(6)->toDateString(),
                ], $this->adminId, 'إنشاء مجموعة ضمن بيانات العرض');

                app(AttachProgramAction::class)->execute(
                    $group,
                    (string) $program->getKey(),
                    $this->adminId,
                    'ربط المجموعة ببرنامجها',
                );

                app(AssignTeacherAction::class)->execute($group, [
                    'staff_profile_id' => (string) $teacher->getKey(),
                    'course_id' => (string) $course->getKey(),
                    'role' => GroupTeacherRole::Lead->value,
                    'assigned_from' => $startsOn->toDateString(),
                ], $this->adminId, 'إسناد المعلم الأساسي للمجموعة');

                app(ActivateGroupAction::class)->execute(
                    $group,
                    $this->adminId,
                    'تفعيل المجموعة بعد اكتمال المعلم والمواعيد',
                );
            }

            foreach ($slice as $student) {
                try {
                    app(AssignStudentToGroupAction::class)->execute(
                        actorOrganizationId: $this->organizationId,
                        studentProfileId: (string) $student->getKey(),
                        programId: (string) $program->getKey(),
                        groupId: (string) $group->getKey(),
                        courseId: (string) $course->getKey(),
                        actorId: $this->adminId,
                        reason: 'تسكين الطالب في المجموعة المناسبة لعمره ومستواه',
                    );
                } catch (Throwable $e) {
                    $this->command?->warn('  تعذّر تسكين '.$student->student_code.' — '.$e->getMessage());
                }
            }

            $built[] = [
                'group' => $group->refresh(),
                'catalog' => $key,
                'course' => $course,
                'program' => $program,
                'teacher' => $teacher,
                'students' => $slice,
                'weekdays' => $weekdays,
                'time' => $time,
            ];
        }

        return $built;
    }

    // ── الجداول والحصص القادمة ─────────────────────────────────────────────

    /** @param list<array<string, mixed>> $groups */
    private function schedules(array $groups): void
    {
        foreach ($groups as $entry) {
            /** @var Group $group */
            $group = $entry['group'];

            if (DB::table('schedules')->where('group_id', $group->getKey())->exists()) {
                continue;
            }

            try {
                $schedule = app(CreateScheduleAction::class)->execute(
                    $this->organizationId,
                    [
                        'target_type' => 'group',
                        'group_id' => (string) $group->getKey(),
                        'course_id' => (string) $entry['course']->getKey(),
                        'staff_profile_id' => (string) $entry['teacher']->getKey(),
                        'weekdays' => $entry['weekdays'],
                        'interval_weeks' => 1,
                        'start_time' => $entry['time'],
                        'duration_minutes' => (int) $entry['course']->default_duration_minutes,
                        'timezone' => self::TIMEZONE,
                        'starts_on' => CarbonImmutable::now('UTC')->subWeeks(self::PAST_WEEKS)->toDateString(),
                        'ends_on' => CarbonImmutable::now('UTC')->addWeeks(8)->toDateString(),
                    ],
                    $this->adminId,
                    'جدول المجموعة الأسبوعي',
                );

                app(MaterializeScheduleAction::class)->execute(
                    $schedule,
                    $this->adminId,
                    'توليد حصص الجدول حتى الأفق المسموح',
                );
            } catch (Throwable $e) {
                $this->command?->warn('  تعذّرت جدولة '.$group->code.' — '.$e->getMessage());
            }
        }
    }

    // ── الحصص الماضية ──────────────────────────────────────────────────────

    /**
     * المولِّد لا ينشئ حصصًا في الماضي عمدًا (يتخطّى كل موعد مضى)، فالتاريخ
     * يُبنى هنا يدويًا ثم يمرّ بدورة الحياة كاملة: بدء ← حضور ← إنهاء ← اعتماد.
     *
     * @param list<array<string, mixed>> $groups
     * @return list<array{session: Session, teacher: StaffProfile, students: list<StudentProfile>}>
     */
    private function pastSessions(array $groups): array
    {
        $delivered = [];
        $now = CarbonImmutable::now('UTC');

        foreach ($groups as $entry) {
            /** @var Group $group */
            $group = $entry['group'];
            /** @var StaffProfile $teacher */
            $teacher = $entry['teacher'];
            $actorId = (string) $teacher->user_id;
            $duration = (int) $entry['course']->default_duration_minutes;
            $index = 0;

            for ($week = self::PAST_WEEKS; $week >= 1; $week--) {
                foreach ($entry['weekdays'] as $weekday) {
                    $index++;
                    $start = $now
                        ->subWeeks($week)
                        ->startOfWeek(CarbonImmutable::SUNDAY)
                        ->addDays($weekday)
                        ->setTime((int) substr((string) $entry['time'], 0, 2), (int) substr((string) $entry['time'], 3, 2));

                    if ($start->greaterThanOrEqualTo($now->subHours(2))) {
                        continue;
                    }

                    $title = $this->sessionTitle($entry['catalog'], $index);

                    $exists = Session::query()
                        ->where('group_id', $group->getKey())
                        ->where('scheduled_start', $start)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    try {
                        $outcome = $this->outcomeFor($index);
                        $session = $this->buildPastSession($entry, $start, $duration, $title);
                        $participants = $this->inviteParticipants($session, $entry['students'], $entry['program']);

                        if ($participants === []) {
                            continue;
                        }

                        if ($outcome === 'cancelled') {
                            app(CancelSessionAction::class)->execute(
                                $session,
                                SessionStatus::CancelledByTeacher,
                                'اعتذار المعلم قبل الموعد بوقت كافٍ',
                                $actorId,
                            );

                            continue;
                        }

                        $session = app(StartSessionAction::class)->execute($session, $actorId, 'بدء الحصة');

                        foreach ($participants as $position => $participant) {
                            [$minutes, $joinedAfter, $leftBefore, $reason] =
                                $this->attendanceFor($index, $position, $duration);

                            app(RecordAttendanceAction::class)->execute(
                                sessionParticipantId: (string) $participant->getKey(),
                                attendedMinutes: $minutes,
                                sessionMinutes: $duration,
                                joinedAfterMinutes: $joinedAfter,
                                leftBeforeMinutes: $leftBefore,
                                organizationId: $this->organizationId,
                                actorId: $actorId,
                                reason: $reason,
                            );
                        }

                        $session = app(EndSessionAction::class)->execute($session, $actorId, 'إنهاء الحصة');
                        $session = app(CompleteSessionAction::class)->execute($session, $actorId, 'اعتماد الحصة بعد رصد الحضور');

                        $delivered[] = [
                            'session' => $session,
                            'teacher' => $teacher,
                            'students' => $entry['students'],
                            'index' => $index,
                        ];
                    } catch (Throwable $e) {
                        $this->command?->warn('  تعذّرت حصة '.$group->code.' #'.$index.' — '.$e->getMessage());
                    }
                }
            }
        }

        return $delivered;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string> $title
     */
    private function buildPastSession(array $entry, CarbonImmutable $start, int $duration, array $title): Session
    {
        return Session::query()->create([
            'organization_id' => $this->organizationId,
            'group_id' => $entry['group']->getKey(),
            'course_id' => $entry['course']->getKey(),
            'staff_profile_id' => $entry['teacher']->getKey(),
            'session_type' => 'group',
            'scheduled_start' => $start,
            'scheduled_end' => $start->addMinutes($duration),
            'title' => $title,
            'status' => SessionStatus::Scheduled,
        ]);
    }

    /**
     * @param list<StudentProfile> $students
     * @return list<SessionParticipant>
     */
    private function inviteParticipants(Session $session, array $students, Program $program): array
    {
        $participants = [];

        foreach ($students as $student) {
            $enrollment = Enrollment::query()
                ->where('student_profile_id', $student->getKey())
                ->where('program_id', $program->getKey())
                ->first();

            if (!$enrollment instanceof Enrollment) {
                continue;
            }

            $participants[] = SessionParticipant::query()->create([
                'session_id' => $session->getKey(),
                'student_profile_id' => $student->getKey(),
                'enrollment_id' => $enrollment->getKey(),
                'join_url_token' => Str::random(64),
                'invited_at' => $session->scheduled_start->subDays(2),
                'attended_minutes' => 0,
            ]);
        }

        return $participants;
    }

    /** نتيجة الحصة: الأغلبية تُعقد، وواحدة من كل عشر تُلغى — نسيج واقعي لا رتيب. */
    private function outcomeFor(int $index): string
    {
        return $index % 10 === 7 ? 'cancelled' : 'held';
    }

    /**
     * حضور واقعي: الأغلبية كاملة، وتأخّر متفرّق، وغياب نادر — حتى لا تكون
     * نسبة الحضور 100% في كل بطاقة فتفقد معناها.
     *
     * الدقائق وحدها لا تكفي: `AttendanceStatus::deriveFromMinutes` تميّز
     * «متأخر» بدقائق الدخول المتأخر و«انصراف مبكر» بدقائق المغادرة. تمريرُ
     * الدقائق فقط كان سينتج «جزئي» في الحالتين ويطمس الفرق.
     *
     * @return array{0: int, 1: int, 2: int, 3: string}
     */
    private function attendanceFor(int $index, int $position, int $duration): array
    {
        $seed = ($index * 7 + $position * 3) % 12;

        return match (true) {
            // غياب تام
            $seed === 0 => [0, 0, 0, 'غياب دون إشعار مسبق'],
            // متأخر: حضر أغلب الحصة لكنه دخل بعد العتبة (10 دقائق)
            $seed === 1 => [$duration - 14, 14, 0, 'التحاق متأخر بسبب انقطاع الإنترنت'],
            // انصراف مبكر
            $seed === 5 => [$duration - 12, 0, 12, 'انصراف مبكر بإذن ولي الأمر'],
            // حضور جزئي
            $seed === 9 => [(int) round($duration * 0.55), 3, 0, 'حضور جزئي — عطل في الصوت'],
            default => [$duration, 0, 0, 'حضور كامل'],
        };
    }

    /** @return array<string, string> */
    private function sessionTitle(string $catalog, int $index): array
    {
        $titles = [
            'quran' => [
                ['ar' => 'سورة النبأ — الحفظ والتصحيح', 'en' => 'Surat An-Naba — memorisation'],
                ['ar' => 'سورة النازعات — المقطع الأول', 'en' => 'Surat An-Naziat — part one'],
                ['ar' => 'أحكام النون الساكنة تطبيقًا', 'en' => 'Applied noon sakinah rules'],
                ['ar' => 'مراجعة تراكمية ومسابقة', 'en' => 'Cumulative revision & quiz'],
                ['ar' => 'سورة عبس — الحفظ', 'en' => 'Surat Abasa — memorisation'],
                ['ar' => 'مخارج الحروف — تدريب فردي', 'en' => 'Articulation points — drills'],
            ],
            'coding' => [
                ['ar' => 'المتغيرات وأنواع البيانات', 'en' => 'Variables and data types'],
                ['ar' => 'الشروط واتخاذ القرار', 'en' => 'Conditionals and decisions'],
                ['ar' => 'الحلقات التكرارية', 'en' => 'Loops'],
                ['ar' => 'الدوال وإعادة الاستخدام', 'en' => 'Functions and reuse'],
                ['ar' => 'القوائم والقواميس', 'en' => 'Lists and dictionaries'],
                ['ar' => 'قراءة الملفات ومعالجتها', 'en' => 'Reading and processing files'],
            ],
            'data' => [
                ['ar' => 'تنظيف البيانات الخام', 'en' => 'Cleaning raw data'],
                ['ar' => 'الجداول المحورية عمليًا', 'en' => 'Pivot tables in practice'],
                ['ar' => 'اختيار الرسم البياني الصحيح', 'en' => 'Choosing the right chart'],
                ['ar' => 'بناء لوحة المؤشرات الأولى', 'en' => 'Building your first dashboard'],
                ['ar' => 'مؤشرات الأداء وقياسها', 'en' => 'KPIs and how to measure them'],
                ['ar' => 'عرض النتائج على الإدارة', 'en' => 'Presenting findings to management'],
            ],
        ];

        $list = $titles[$catalog];

        return $list[($index - 1) % count($list)];
    }

    // ── تقارير الحصص ───────────────────────────────────────────────────────

    /** @param list<array<string, mixed>> $delivered */
    private function sessionReports(array $delivered): void
    {
        $notes = [
            'التزم الطلاب بالتحضير المسبق، وظهر تحسّن واضح في السرعة والدقة.',
            'المستوى متفاوت داخل المجموعة؛ خُصص وقت إضافي للمتعثرين في نهاية الحصة.',
            'تفاعل ممتاز مع التمرين العملي، والواجب أُرسل عبر البوابة.',
            'حصة مراجعة هادئة، وأغلب الملاحظات السابقة عولجت.',
        ];

        foreach ($delivered as $position => $item) {
            /** @var Session $session */
            $session = $item['session'];

            /*
             * أحدث حصتين في كل مجموعة تبقيان بلا تقرير عمدًا: مدرسة حقيقية فيها
             * تقارير متأخرة، والأهم أن `SubmitSessionReportAction` ترفض تقريرًا
             * ثانيًا لنفس الحصة — فلو كُتبت كلها لما بقيت حصة واحدة يجرّب عليها
             * العميل كتابة التقرير.
             */
            if ((int) $item['index'] >= self::PAST_WEEKS * 2 - 1) {
                continue;
            }

            if (DB::table('session_reports')->where('session_id', $session->getKey())->exists()) {
                continue;
            }

            $rows = [];

            foreach ($item['students'] as $studentPosition => $student) {
                // الدرجات من 1 إلى 5؛ نبقى في 3..5 لأن مجموعةً كاملة بدرجة 1
                // في بيانات عرض تقرأ كخلل لا كتقييم.
                $seed = ($position + $studentPosition) % 5;
                $rows[] = [
                    'student_profile_id' => (string) $student->getKey(),
                    'participation' => 3 + ($seed % 3),
                    'performance' => 3 + (($seed + 1) % 3),
                    'commitment' => 4 + ($seed % 2),
                    'strengths' => $seed % 2 === 0 ? 'انتباه جيد ومشاركة تلقائية.' : null,
                    'weaknesses' => $seed === 3 ? 'يحتاج تدريبًا إضافيًا على التطبيق العملي.' : null,
                ];
            }

            try {
                app(SubmitSessionReportAction::class)->execute(
                    sessionId: (string) $session->getKey(),
                    staffProfileId: (string) $item['teacher']->getKey(),
                    students: $rows,
                    submittedAt: $session->scheduled_end?->addHours(2),
                    sessionEndedAt: $session->scheduled_end,
                    topicsCovered: (string) (($session->title['ar'] ?? null) ?: 'موضوع الحصة'),
                    generalNotes: $notes[$position % count($notes)],
                );
            } catch (Throwable $e) {
                $this->command?->warn('  تعذّر تقرير حصة — '.$e->getMessage());
            }
        }
    }

    // ── التقارير الشهرية ───────────────────────────────────────────────────

    /** @param list<array<string, mixed>> $groups */
    private function monthlyReports(array $groups): void
    {
        $month = CarbonImmutable::now('UTC')->subMonth();

        foreach ($groups as $entry) {
            foreach ($entry['students'] as $student) {
                $enrollment = Enrollment::query()
                    ->where('student_profile_id', $student->getKey())
                    ->where('program_id', $entry['program']->getKey())
                    ->first();

                if (!$enrollment instanceof Enrollment) {
                    continue;
                }

                $exists = DB::table('monthly_reports')
                    ->where('student_profile_id', $student->getKey())
                    ->where('period_year', $month->year)
                    ->where('period_month', $month->month)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $metrics = $this->monthlyMetrics((string) $student->getKey(), $month);

                try {
                    $report = app(DraftMonthlyReportAction::class)->execute(
                        organizationId: $this->organizationId,
                        studentProfileId: (string) $student->getKey(),
                        enrollmentId: (string) $enrollment->getKey(),
                        periodYear: $month->year,
                        periodMonth: $month->month,
                        metrics: $metrics,
                        supervisorSummary: $this->monthlySummary($metrics),
                    );

                    app(ApproveMonthlyReportAction::class)->execute(
                        $report,
                        $this->adminId,
                        'اعتماد التقرير الشهري بعد مراجعة الإشراف الأكاديمي',
                    );
                } catch (Throwable $e) {
                    $this->command?->warn('  تعذّر تقرير شهري — '.$e->getMessage());
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function monthlyMetrics(string $studentProfileId, CarbonImmutable $month): array
    {
        $rows = DB::table('attendances')
            ->join('session_participants', 'session_participants.id', '=', 'attendances.session_participant_id')
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->where('session_participants.student_profile_id', $studentProfileId)
            ->whereBetween('sessions.scheduled_start', [$month->startOfMonth(), $month->endOfMonth()])
            ->get(['attendances.derived_status']);

        $total = $rows->count();
        // «حضر» يشمل المتأخر والمنصرف مبكرًا: كلاهما حضر الحصة فعلًا.
        $attended = $rows->whereIn('derived_status', ['present', 'late', 'left_early'])->count();

        return [
            'sessions_total' => $total,
            'sessions_attended' => $attended,
            'attendance_rate' => $total > 0 ? (int) round($attended / $total * 100) : 0,
            'late_count' => $rows->where('derived_status', 'late')->count(),
            'partial_count' => $rows->where('derived_status', 'partial')->count(),
            'absent_count' => $rows->whereIn('derived_status', ['absent', 'no_show'])->count(),
        ];
    }

    /** @param array<string, mixed> $metrics */
    private function monthlySummary(array $metrics): string
    {
        $rate = (int) ($metrics['attendance_rate'] ?? 0);

        return match (true) {
            $rate >= 90 => 'انتظام ممتاز خلال الشهر مع مشاركة فاعلة في الحصص. نوصي بالاستمرار على المستوى نفسه.',
            $rate >= 75 => 'انتظام جيد مع غياب محدود. يُفضَّل تعويض ما فات عبر التسجيلات المتاحة في البوابة.',
            $rate > 0 => 'الانتظام أقل من المتوقع هذا الشهر. رجاءً التواصل مع الإشراف الأكاديمي لمراجعة المواعيد.',
            default => 'لا توجد حصص مسجّلة لهذا الشهر.',
        };
    }

    // ── طلبات تسجيل معلّقة ─────────────────────────────────────────────────

    /**
     * صندوق الطلبات فارغ بلا هذه: خطوتا «استقبال الطلبات» و«الفرز» في رحلة
     * العميل لا شيء فيهما ما لم يوجد طلب لم يُبتّ فيه بعد.
     *
     * @param array<string, array{program: Program, level: Level, course: Course, rate: string}> $catalog
     */
    private function pendingApplications(array $catalog): void
    {
        $pending = [
            ['quran', 'بشرى عادل الشهري', 'female', '2016-06-12', 'SA', 'عسير', '+966501122334', 'ترغب الأسرة في حلقة نسائية مسائية.'],
            ['quran', 'معاذ سليم أبو ريدة', 'male', '2015-10-04', 'PS', 'رام الله والبيرة', '+970599887766', 'الطالب أتمّ حفظ ثلاث سور ويرغب بمواصلة الجزء.'],
            ['coding', 'تالا محمد بركات', 'female', '2010-04-27', 'JO', 'العاصمة', '+962791234567', 'لديها خلفية في سكراتش وتريد الانتقال لبايثون.'],
            ['coding', 'زياد ماهر الصبان', 'male', '2011-01-16', 'SA', 'مكة المكرمة', '+966533445566', 'يطلب موعدًا بعد السابعة مساءً بتوقيت مكة.'],
            ['data', 'ولاء ناصر الكندري', 'female', '1997-02-08', 'KW', 'العاصمة', '+96599776655', 'تعمل في قسم المشتريات وتريد لوحات مؤشرات عملية.'],
        ];

        foreach ($pending as [$key, $name, $gender, $dob, $iso2, $region, $phone, $notes]) {
            $exists = DB::table('registration_applications')->where('full_name', $name)->exists();

            if ($exists) {
                continue;
            }

            try {
                $application = app(CreateRegistrationApplicationAction::class)->execute([
                    'full_name' => $name,
                    'date_of_birth' => $dob,
                    'gender' => $gender,
                    'country_id' => $this->countryId($iso2),
                    'region_id' => $this->regionId($iso2, $region),
                    'email' => null,
                    'phone' => $phone,
                    'preferred_program_id' => (string) $catalog[$key]['program']->getKey(),
                    'preferred_course_id' => (string) $catalog[$key]['course']->getKey(),
                    'notes' => $notes,
                ], $this->organizationId, null);

                app(SubmitRegistrationApplicationAction::class)->execute($application);
            } catch (Throwable $e) {
                $this->command?->warn('  تعذّر طلب تسجيل — '.$e->getMessage());
            }
        }
    }

    // ── الخلاصة ────────────────────────────────────────────────────────────

    private function summary(): void
    {
        $counts = [
            'برامج' => DB::table('programs')->count(),
            'كورسات' => DB::table('courses')->count(),
            'معلمون' => DB::table('staff_profiles')->count(),
            'طلاب' => DB::table('student_profiles')->count(),
            'مجموعات' => DB::table('groups')->count(),
            'قيود' => DB::table('enrollments')->count(),
            'حصص' => DB::table('sessions')->count(),
            'قيود حضور' => DB::table('attendances')->count(),
            'تقارير حصص' => DB::table('session_reports')->count(),
            'تقارير شهرية' => DB::table('monthly_reports')->count(),
            'قيود مستحقات' => DB::table('payroll_entries')->count(),
            'طلبات تسجيل' => DB::table('registration_applications')->count(),
        ];

        foreach ($counts as $label => $value) {
            $this->command?->line(sprintf('  %-14s %d', $label, $value));
        }
    }
}
