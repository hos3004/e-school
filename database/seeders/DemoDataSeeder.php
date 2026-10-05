<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use RuntimeException;

/**
 * بذرة بيانات تجريبية تجعل لوحة الإدارة قابلة للتصفح:
 * معلمون بعقود وأسعار، برامج ومستويات ومواد، مجموعات وقواعد متكررة،
 * أربعون حصة بحالات متنوعة مع حضورها، وفترة مستحقات بقيدها.
 * قابلة لإعادة التشغيل — تنظّف أثرها السابق أولًا.
 */
final class DemoDataSeeder extends Seeder
{
    private const EMAIL_MARKER = '@demo.eschool.local';

    public function run(): void
    {
        $organizationId = DB::table('organizations')->orderBy('created_at')->value('id');

        if (!is_string($organizationId) || $organizationId === '') {
            throw new RuntimeException('DemoDataSeeder: لا توجد مؤسسة في قاعدة البيانات.');
        }

        $this->forgetPreviousDemoData($organizationId);

        $actorId = $this->resolveActor($organizationId);
        $now = CarbonImmutable::now('UTC');

        $students = DB::table('student_profiles')
            ->where('organization_id', $organizationId)
            ->orderBy('created_at')
            ->limit(5)
            ->pluck('id')
            ->all();

        if (count($students) < 5) {
            throw new RuntimeException('DemoDataSeeder: يلزم وجود خمسة طلاب مسبقًا قبل تشغيل البذرة.');
        }

        $teachers = $this->seedTeachers($organizationId, $now);
        $academics = $this->seedAcademics($organizationId, $now);

        $groups = $this->seedGroups(
            $organizationId,
            $academics,
            $teachers,
            [
                'g1' => ['teacher' => 't3', 'course' => 'quran_hifz', 'program' => 'quran'],
                'g2' => ['teacher' => 't1', 'course' => 'quran_tajwid', 'program' => 'quran'],
                'g3' => ['teacher' => 't4', 'course' => 'eng_conv', 'program' => 'english'],
                'g4' => ['teacher' => 't5', 'course' => 'code_py', 'program' => 'coding'],
            ],
            $now,
        );

        $enrollments = $this->seedEnrollments($organizationId, $academics, $students, $now);
        $this->linkMemberships($groups, $students, $now);

        $scheduleConfig = [
            'g1' => ['start' => '16:00:00', 'minutes' => 60, 'bydays' => ['MO', 'WE']],
            'g2' => ['start' => '17:00:00', 'minutes' => 60, 'bydays' => ['TU', 'SA']],
            'g3' => ['start' => '18:00:00', 'minutes' => 60, 'bydays' => ['SU', 'TH']],
            'g4' => ['start' => '19:00:00', 'minutes' => 90, 'bydays' => ['WE', 'SA']],
        ];

        $schedules = $this->seedSchedules($organizationId, $groups, $scheduleConfig, $actorId, $now);
        $sessions = $this->seedSessions($organizationId, $schedules, $groups, $scheduleConfig, $enrollments, $students, $actorId, $now);
        $this->seedAttendances($sessions, $actorId, $now);
        $this->seedPayroll($organizationId, $sessions, $teachers, $now);
    }

    private function forgetPreviousDemoData(string $organizationId): void
    {
        $userIds = DB::table('users')
            ->where('email', 'like', '%'.self::EMAIL_MARKER)
            ->pluck('id')
            ->all();

        $staffIds = $userIds === []
            ? []
            : DB::table('staff_profiles')->whereIn('user_id', $userIds)->pluck('id')->all();

        $contractIds = $staffIds === []
            ? []
            : DB::table('teacher_contracts')->whereIn('staff_profile_id', $staffIds)->pluck('id')->all();

        // المجموعات والبرامج تُعرَّف عبر ارتباطها بمعلمي البذرة، لا عبر بادئة الكود:
        // الأكواد صارت قصيرة ومتسلسلة (G001) ولم تعد صالحة كعلامة تمييز.
        $groupIds = $staffIds === []
            ? []
            : DB::table('group_teachers')->whereIn('staff_profile_id', $staffIds)->pluck('group_id')->unique()->all();

        $programIds = $groupIds === []
            ? []
            : DB::table('group_programs')->whereIn('group_id', $groupIds)->pluck('program_id')->unique()->all();

        $sessionIds = $staffIds === []
            ? []
            : DB::table('sessions')->whereIn('staff_profile_id', $staffIds)->pluck('id')->all();

        $participantIds = $sessionIds === []
            ? []
            : DB::table('session_participants')->whereIn('session_id', $sessionIds)->pluck('id')->all();

        if ($sessionIds !== []) {
            DB::table('payroll_entries')->whereIn('session_id', $sessionIds)->delete();
        }

        if ($staffIds !== []) {
            DB::table('payroll_entries')->whereIn('staff_profile_id', $staffIds)->delete();
        }

        if ($participantIds !== []) {
            DB::table('attendances')->whereIn('session_participant_id', $participantIds)->delete();
        }

        if ($sessionIds !== []) {
            DB::table('session_participants')->whereIn('session_id', $sessionIds)->delete();
            DB::table('sessions')->whereIn('id', $sessionIds)->delete();
        }

        if ($staffIds !== []) {
            DB::table('schedules')->whereIn('staff_profile_id', $staffIds)->delete();
        }

        if ($groupIds !== []) {
            DB::table('group_memberships')->whereIn('group_id', $groupIds)->delete();
            DB::table('group_teachers')->whereIn('group_id', $groupIds)->delete();
            DB::table('group_programs')->whereIn('group_id', $groupIds)->delete();
            DB::table('groups')->whereIn('id', $groupIds)->delete();
        }

        if ($programIds !== []) {
            DB::table('enrollments')->whereIn('program_id', $programIds)->delete();
            DB::table('courses')->whereIn('level_id', function ($query) use ($programIds): void {
                $query->select('id')->from('levels')->whereIn('program_id', $programIds);
            })->delete();
            DB::table('levels')->whereIn('program_id', $programIds)->delete();
            DB::table('programs')->whereIn('id', $programIds)->delete();
        }

        if ($contractIds !== []) {
            DB::table('teacher_rates')->whereIn('teacher_contract_id', $contractIds)->delete();
            DB::table('teacher_contracts')->whereIn('id', $contractIds)->delete();
        }

        if ($staffIds !== []) {
            DB::table('staff_profiles')->whereIn('id', $staffIds)->delete();
        }

        if ($userIds !== []) {
            DB::table('users')->whereIn('id', $userIds)->delete();
        }
    }

    private function resolveActor(string $organizationId): string
    {
        $demoUserIds = DB::table('users')
            ->where('email', 'like', '%'.self::EMAIL_MARKER)
            ->pluck('id')
            ->all();

        $actorId = DB::table('users')
            ->where('organization_id', $organizationId)
            ->when($demoUserIds !== [], fn ($query) => $query->whereNotIn('id', $demoUserIds))
            ->orderBy('created_at')
            ->value('id');

        if (!is_string($actorId) || $actorId === '') {
            $actorId = DB::table('users')
                ->where('organization_id', $organizationId)
                ->orderBy('created_at')
                ->value('id');
        }

        if (!is_string($actorId) || $actorId === '') {
            throw new RuntimeException('DemoDataSeeder: لا يوجد مستخدم للاعتماد عليه كمنشئ للبيانات.');
        }

        return $actorId;
    }

    /**
     * @return array<string, array{staff_profile_id: string, contract_id: string, rate: int, resolved_via: string}>
     */
    private function seedTeachers(string $organizationId, CarbonImmutable $now): array
    {
        $password = bcrypt('password');
        $effectiveFrom = $now->subMonths(3)->toDateString();
        $hiredAt = $now->subYear()->toDateString();

        $definitions = [
            't1' => [
                'name' => 'أ. أحمد عبد الرحمن',
                'local' => 'ahmed.abdelrahman',
                'code' => 'T101',
                'employment' => 'part_time',
                'basis' => 'per_session',
                'base' => null,
                'targets' => [],
                'rate' => 7500,
                'scope' => 'default',
                'specialties' => ['تحفيظ القرآن الكريم', 'قراءات راشدة'],
                'bio' => 'معلم تحفيظ بخبرة عشر سنوات في تدريس الجزء الثلاثين.',
            ],
            't2' => [
                'name' => 'أ. محمود سعيد',
                'local' => 'mahmoud.saeed',
                'code' => 'T102',
                'employment' => 'part_time',
                'basis' => 'per_session',
                'base' => null,
                'targets' => [],
                'rate' => 6000,
                'scope' => 'default',
                'specialties' => ['التجويد', 'الترتيل'],
                'bio' => 'إجازة في قراءة حفص عن عاصم، وخبرة في تصحيح التلاوة.',
            ],
            't3' => [
                'name' => 'أ. فاطمة الحسيني',
                'local' => 'fatima.elhusseiny',
                'code' => 'T103',
                'employment' => 'part_time',
                'basis' => 'monthly_with_deductions',
                'base' => 60000,
                'targets' => ['monthly_target_sessions' => 12],
                'rate' => 5000,
                'scope' => 'default',
                'specialties' => ['تحفيظ الأطفال', 'حلقات صغيرة'],
                'bio' => 'متخصصة في حلقات التحفيظ الجماعية الصغيرة.',
            ],
            't4' => [
                'name' => 'أ. سارة منصور',
                'local' => 'sara.mansour',
                'code' => 'T104',
                'employment' => 'full_time',
                'basis' => 'per_session',
                'base' => null,
                'targets' => [],
                'rate' => 8000,
                'scope' => 'program',
                'specialties' => ['المحادثة الإنجليزية', 'تحضير IELTS'],
                'bio' => 'مدرسة لغة إنجليزية بتركيز على المحادثة والاستماع.',
            ],
            't5' => [
                'name' => 'أ. عمر خليل',
                'local' => 'omar.khalil',
                'code' => 'T105',
                'employment' => 'contractor',
                'basis' => 'per_session',
                'base' => null,
                'targets' => [],
                'rate' => 9000,
                'scope' => 'default',
                'specialties' => ['بايثون', 'تطوير الويب'],
                'bio' => 'مهندس برمجيات يدرّس أساسيات البرمجة للناشئين.',
            ],
            't6' => [
                'name' => 'أ. ليلى إبراهيم',
                'local' => 'laila.ibrahim',
                'code' => 'T106',
                'employment' => 'full_time',
                'basis' => 'salaried',
                'base' => 800000,
                'targets' => [
                    'monthly_target_sessions' => 8,
                    'target_admin_tasks' => 20,
                    'target_training_sessions' => 4,
                ],
                'rate' => 0,
                'scope' => 'default',
                'specialties' => ['الإشراف الأكاديمي', 'تدريب المعلمين'],
                'bio' => 'مشرفة أكاديمية مسؤولة عن جودة التدريس وخطط التدريب.',
            ],
        ];

        $teachers = [];

        foreach ($definitions as $key => $definition) {
            $userId = self::ulid();
            $staffId = self::ulid();
            $contractId = self::ulid();

            DB::table('users')->insert([
                'id' => $userId,
                'organization_id' => $organizationId,
                'name' => $definition['name'],
                'email' => $definition['local'].self::EMAIL_MARKER,
                'username' => $definition['local'],
                'password' => $password,
                'locale' => 'ar',
                'timezone' => 'Africa/Cairo',
                'email_verified_at' => self::ts($now),
                'status' => 'active',
                'created_at' => self::ts($now),
                'updated_at' => self::ts($now),
            ]);

            DB::table('staff_profiles')->insert([
                'id' => $staffId,
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'staff_code' => $definition['code'],
                'employment_type' => $definition['employment'],
                'hired_at' => $hiredAt,
                'bio' => self::js(['ar' => $definition['bio'], 'en' => $definition['bio']]),
                'specializations' => self::js($definition['specialties']),
                'created_at' => self::ts($now),
                'updated_at' => self::ts($now),
            ]);

            DB::table('teacher_contracts')->insert([
                'id' => $contractId,
                'organization_id' => $organizationId,
                'staff_profile_id' => $staffId,
                'basis' => $definition['basis'],
                'effective_from' => $effectiveFrom,
                'base_amount' => $definition['base'],
                'currency' => 'EGP',
                'monthly_target_sessions' => $definition['targets']['monthly_target_sessions'] ?? null,
                'target_admin_tasks' => $definition['targets']['target_admin_tasks'] ?? null,
                'target_training_sessions' => $definition['targets']['target_training_sessions'] ?? null,
                'terms' => self::js([
                    'ar' => 'يلتزم المعلم بالحضور قبل موعد الحصة بخمس دقائق وتسجيل الحضور في نفس اليوم.',
                    'en' => 'Teacher joins five minutes early and records attendance the same day.',
                ]),
                'created_at' => self::ts($now),
                'updated_at' => self::ts($now),
            ]);

            if ($definition['rate'] > 0) {
                DB::table('teacher_rates')->insert([
                    'id' => self::ulid(),
                    'teacher_contract_id' => $contractId,
                    'scope' => $definition['scope'],
                    'amount' => $definition['rate'],
                    'currency' => 'EGP',
                    'effective_from' => $effectiveFrom,
                    'created_at' => self::ts($now),
                ]);
            }

            $teachers[$key] = [
                'staff_profile_id' => $staffId,
                'contract_id' => $contractId,
                'rate' => $definition['rate'],
                'resolved_via' => $definition['scope'] === 'program' ? 'contract_program' : 'contract_default',
            ];
        }

        return $teachers;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedAcademics(string $organizationId, CarbonImmutable $now): array
    {
        $programs = [
            'quran' => [
                'code' => 'P101',
                'name' => ['ar' => 'القرآن الكريم', 'en' => 'Holy Quran'],
                'description' => ['ar' => 'برنامج تحفيظ وتجويد متدرج لكل الأعمار.', 'en' => 'Graduated memorization and tajweed program.'],
                'weeks' => 36,
                'minutes' => 60,
                'rate' => 5000,
                'sort' => 1,
                'levels' => [
                    'L1' => ['ar' => 'المستوى الأول', 'en' => 'Level One'],
                    'L2' => ['ar' => 'المستوى الثاني', 'en' => 'Level Two'],
                ],
                'courses' => [
                    'quran_hifz' => [
                        'level' => 'L1',
                        'code' => 'C101',
                        'name' => ['ar' => 'حفظ جزء عمّ', 'en' => 'Juz Amma Memorization'],
                    ],
                    'quran_tajwid' => [
                        'level' => 'L2',
                        'code' => 'C102',
                        'name' => ['ar' => 'تجويد متقدم', 'en' => 'Advanced Tajweed'],
                    ],
                ],
            ],
            'english' => [
                'code' => 'P102',
                'name' => ['ar' => 'اللغة الإنجليزية', 'en' => 'English Language'],
                'description' => ['ar' => 'مسار قواعد ومحادثة حتى الطلاقة.', 'en' => 'Grammar and conversation track towards fluency.'],
                'weeks' => 24,
                'minutes' => 60,
                'rate' => 7000,
                'sort' => 2,
                'levels' => [
                    'L1' => ['ar' => 'المستوى الأول', 'en' => 'Level One'],
                    'L2' => ['ar' => 'المستوى الثاني', 'en' => 'Level Two'],
                ],
                'courses' => [
                    'eng_basic' => [
                        'level' => 'L1',
                        'code' => 'C103',
                        'name' => ['ar' => 'إنجليزيات الأساس', 'en' => 'Foundation English'],
                    ],
                    'eng_conv' => [
                        'level' => 'L2',
                        'code' => 'C104',
                        'name' => ['ar' => 'محادثة متقدمة', 'en' => 'Advanced Conversation'],
                    ],
                ],
            ],
            'coding' => [
                'code' => 'P103',
                'name' => ['ar' => 'البرمجة', 'en' => 'Programming'],
                'description' => ['ar' => 'من أوامر بايثون الأولى إلى بناء صفحات ويب.', 'en' => 'From first Python commands to building web pages.'],
                'weeks' => 24,
                'minutes' => 90,
                'rate' => 9000,
                'sort' => 3,
                'levels' => [
                    'L1' => ['ar' => 'المستوى الأول', 'en' => 'Level One'],
                    'L2' => ['ar' => 'المستوى الثاني', 'en' => 'Level Two'],
                ],
                'courses' => [
                    'code_py' => [
                        'level' => 'L1',
                        'code' => 'C105',
                        'name' => ['ar' => 'أساسيات بايثون', 'en' => 'Python Basics'],
                    ],
                    'code_web' => [
                        'level' => 'L2',
                        'code' => 'C106',
                        'name' => ['ar' => 'تطوير الويب', 'en' => 'Web Development'],
                    ],
                ],
            ],
        ];

        $built = ['programs' => [], 'courses' => []];

        foreach ($programs as $programKey => $program) {
            $programId = self::ulid();
            $levelIds = [];
            $createdAt = self::ts($now);

            DB::table('programs')->insert([
                'id' => $programId,
                'organization_id' => $organizationId,
                'code' => $program['code'],
                'name' => self::js($program['name']),
                'description' => self::js($program['description']),
                'duration_weeks' => $program['weeks'],
                'default_session_minutes' => $program['minutes'],
                'default_rate' => $program['rate'],
                'currency' => 'EGP',
                'is_active' => true,
                'sort_order' => $program['sort'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach (['L1', 'L2'] as $index => $levelCode) {
                $levelId = self::ulid();
                $levelIds[$levelCode] = $levelId;

                DB::table('levels')->insert([
                    'id' => $levelId,
                    'program_id' => $programId,
                    'code' => $levelCode,
                    'name' => self::js($program['levels'][$levelCode]),
                    'sort_order' => $index + 1,
                    'created_at' => $createdAt,
                ]);
            }

            foreach ($program['courses'] as $courseKey => $course) {
                $courseId = self::ulid();

                DB::table('courses')->insert([
                    'id' => $courseId,
                    'organization_id' => $organizationId,
                    'level_id' => $levelIds[$course['level']],
                    'code' => $course['code'],
                    'name' => self::js($course['name']),
                    'total_sessions' => 24,
                    'is_active' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $built['courses'][$courseKey] = ['id' => $courseId, 'name' => $course['name']];
            }

            $built['programs'][$programKey] = [
                'id' => $programId,
                'level_l1' => $levelIds['L1'],
                'level_l2' => $levelIds['L2'],
            ];
        }

        return $built;
    }

    /**
     * @param array<string, mixed> $academics
     * @param array<string, array{staff_profile_id: string, contract_id: string, rate: int, resolved_via: string}> $teachers
     * @param array<string, array<string, string>> $mapping
     * @return array<string, array<string, mixed>>
     */
    private function seedGroups(
        string $organizationId,
        array $academics,
        array $teachers,
        array $mapping,
        CarbonImmutable $now,
    ): array {
        $definitions = [
            'g1' => [
                'code' => 'G101',
                'name' => ['ar' => 'حلقة القرآن — المبتدئون', 'en' => 'Quran Circle — Beginners'],
                'capacity' => 4,
            ],
            'g2' => [
                'code' => 'G102',
                'name' => ['ar' => 'حلقة القرآن — المتقدمون', 'en' => 'Quran Circle — Advanced'],
                'capacity' => 6,
            ],
            'g3' => [
                'code' => 'G103',
                'name' => ['ar' => 'مجموعة الإنجليزية المسائية', 'en' => 'Evening English Group'],
                'capacity' => 8,
            ],
            'g4' => [
                'code' => 'G104',
                'name' => ['ar' => 'مجموعة البرمجة', 'en' => 'Programming Group'],
                'capacity' => 8,
            ],
        ];

        $startsOn = $now->subMonths(2)->toDateString();
        $groups = [];

        foreach ($definitions as $key => $definition) {
            $groupId = self::ulid();
            $map = $mapping[$key];
            $createdAt = self::ts($now);

            DB::table('groups')->insert([
                'id' => $groupId,
                'organization_id' => $organizationId,
                'code' => $definition['code'],
                'name' => self::js($definition['name']),
                'capacity' => $definition['capacity'],
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'starts_on' => $startsOn,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            DB::table('group_programs')->insert([
                'id' => self::ulid(),
                'group_id' => $groupId,
                'program_id' => $academics['programs'][$map['program']]['id'],
                'created_at' => $createdAt,
            ]);

            DB::table('group_teachers')->insert([
                'id' => self::ulid(),
                'group_id' => $groupId,
                'staff_profile_id' => $teachers[$map['teacher']]['staff_profile_id'],
                'course_id' => $academics['courses'][$map['course']]['id'],
                'role' => GroupTeacherRole::Lead->value,
                'assigned_from' => $startsOn,
                'created_at' => $createdAt,
            ]);

            $groups[$key] = [
                'id' => $groupId,
                'teacher_key' => $map['teacher'],
                'course_key' => $map['course'],
                'course_id' => $academics['courses'][$map['course']]['id'],
                'course_name' => $academics['courses'][$map['course']]['name'],
                'staff_profile_id' => $teachers[$map['teacher']]['staff_profile_id'],
                'program_key' => $map['program'],
                'name' => $definition['name'],
            ];
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $academics
     * @param array<int, string> $students
     * @return array<string, string>
     */
    private function seedEnrollments(
        string $organizationId,
        array $academics,
        array $students,
        CarbonImmutable $now,
    ): array {
        $plans = [
            'quran' => [
                'active' => [0, 1, 2],
                'paused' => [3],
                'frozen' => [4],
            ],
            'english' => [
                'active' => [0, 2, 3],
                'paused' => [],
                'frozen' => [],
            ],
            'coding' => [
                'active' => [1, 4],
                'paused' => [],
                'frozen' => [],
            ],
        ];

        $enrollments = [];
        $appliedAt = self::ts($now->subDays(28));
        $activatedAt = self::ts($now->subDays(27));
        $createdAt = self::ts($now);

        foreach ($plans as $programKey => $plan) {
            $programId = $academics['programs'][$programKey]['id'];
            $levelL1 = $academics['programs'][$programKey]['level_l1'];

            foreach ($plan['active'] as $index) {
                $enrollmentId = self::ulid();

                DB::table('enrollments')->insert([
                    'id' => $enrollmentId,
                    'organization_id' => $organizationId,
                    'student_profile_id' => $students[$index],
                    'program_id' => $programId,
                    'status' => 'active',
                    'applied_at' => $appliedAt,
                    'activated_at' => $activatedAt,
                    'current_level_id' => $levelL1,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $enrollments[$index.':'.$programKey] = $enrollmentId;
            }

            foreach ($plan['paused'] as $index) {
                $enrollmentId = self::ulid();

                DB::table('enrollments')->insert([
                    'id' => $enrollmentId,
                    'organization_id' => $organizationId,
                    'student_profile_id' => $students[$index],
                    'program_id' => $programId,
                    'status' => 'paused',
                    'applied_at' => $appliedAt,
                    'activated_at' => $activatedAt,
                    'current_level_id' => $levelL1,
                    'expected_return_date' => $now->addDays(14)->toDateString(),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $enrollments[$index.':'.$programKey] = $enrollmentId;
            }

            foreach ($plan['frozen'] as $index) {
                $enrollmentId = self::ulid();

                DB::table('enrollments')->insert([
                    'id' => $enrollmentId,
                    'organization_id' => $organizationId,
                    'student_profile_id' => $students[$index],
                    'program_id' => $programId,
                    'status' => 'frozen',
                    'applied_at' => $appliedAt,
                    'activated_at' => $activatedAt,
                    'current_level_id' => $levelL1,
                    'frozen_at' => self::ts($now->subDays(5)),
                    'frozen_reason' => 'غيابات متكررة بدون عذر خلال الشهر',
                    'freeze_type' => 'disciplinary',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $enrollments[$index.':'.$programKey] = $enrollmentId;
            }
        }

        return $enrollments;
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     * @param array<int, string> $students
     */
    private function linkMemberships(array $groups, array $students, CarbonImmutable $now): void
    {
        $patterns = [
            'g1', 'g3',
            'g1', 'g4',
            'g1', 'g3',
            'g2', 'g3',
            'g2', 'g4',
        ];

        $joinedAt = self::ts($now->subDays(25));
        $createdAt = self::ts($now);

        foreach ($students as $index => $studentId) {
            $slots = [$patterns[($index * 2) % count($patterns)], $patterns[($index * 2 + 1) % count($patterns)]];

            foreach (array_unique($slots) as $groupKey) {
                DB::table('group_memberships')->insert([
                    'id' => self::ulid(),
                    'group_id' => $groups[$groupKey]['id'],
                    'student_profile_id' => $studentId,
                    'joined_at' => $joinedAt,
                    'status' => 'active',
                    'created_at' => $createdAt,
                ]);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     * @param array<string, array<string, mixed>> $scheduleConfig
     * @return array<string, string>
     */
    private function seedSchedules(
        string $organizationId,
        array $groups,
        array $scheduleConfig,
        string $actorId,
        CarbonImmutable $now,
    ): array {
        $schedules = [];

        foreach ($groups as $groupKey => $group) {
            $config = $scheduleConfig[$groupKey];
            $scheduleId = self::ulid();

            DB::table('schedules')->insert([
                'id' => $scheduleId,
                'organization_id' => $organizationId,
                'group_id' => $group['id'],
                'course_id' => $group['course_id'],
                'staff_profile_id' => $group['staff_profile_id'],
                'session_type' => 'group',
                'rrule' => 'FREQ=WEEKLY;BYDAY='.implode(',', $config['bydays']),
                'start_time' => $config['start'],
                'duration_minutes' => $config['minutes'],
                'timezone' => 'Africa/Cairo',
                'starts_on' => $now->subDays(35)->toDateString(),
                'materialized_until' => $now->addDays(21)->toDateString(),
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => self::ts($now),
                'updated_at' => self::ts($now),
            ]);

            $schedules[$groupKey] = $scheduleId;
        }

        return $schedules;
    }

    /**
     * @param array<string, string> $schedules
     * @param array<string, array<string, mixed>> $groups
     * @param array<string, array<string, mixed>> $scheduleConfig
     * @param array<string, string> $enrollments
     * @param array<int, string> $students
     * @return array<int, array<string, mixed>>
     */
    private function seedSessions(
        string $organizationId,
        array $schedules,
        array $groups,
        array $scheduleConfig,
        array $enrollments,
        array $students,
        string $actorId,
        CarbonImmutable $now,
    ): array {
        $weekdayCodes = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];

        $meta = [];

        foreach ($groups as $groupKey => $group) {
            $config = $scheduleConfig[$groupKey];
            $matches = [];

            for ($offset = -29; $offset <= 14; $offset++) {
                if ($offset === 0) {
                    continue;
                }

                $day = $now->addDays($offset);

                if (in_array($weekdayCodes[$day->dayOfWeekIso], $config['bydays'], true)) {
                    $matches[] = $day;
                }
            }

            usort($matches, fn (CarbonImmutable $a, CarbonImmutable $b) => $a->getTimestamp() <=> $b->getTimestamp());
            $matches = array_slice($matches, 0, 10);

            $pastIndexes = [];
            $startHour = (int) substr($config['start'], 0, 2);
            $startMinute = (int) substr($config['start'], 3, 2);

            foreach ($matches as $position => $day) {
                $scheduledStart = $day->setTime($startHour, $startMinute, 0);
                $scheduledEnd = $scheduledStart->addMinutes($config['minutes']);

                if ($scheduledStart->greaterThanOrEqualTo($now->startOfDay())) {
                    $status = 'scheduled';
                } else {
                    $pastIndexes[] = $position;
                    $fromEnd = count($pastIndexes);
                    $status = match (true) {
                        $fromEnd === 1 => 'no_show',
                        $fromEnd === 2 => 'cancelled_by_student',
                        $fromEnd === 3 => 'postponed',
                        default => 'completed',
                    };
                }

                $sessionId = self::ulid();
                $courseName = $group['course_name'];
                $title = [
                    'ar' => $courseName['ar'].' — '.$group['name']['ar'],
                    'en' => $courseName['en'].' — '.$group['name']['en'],
                ];

                $row = [
                    'id' => $sessionId,
                    'organization_id' => $organizationId,
                    'schedule_id' => $schedules[$groupKey],
                    'group_id' => $group['id'],
                    'course_id' => $group['course_id'],
                    'staff_profile_id' => $group['staff_profile_id'],
                    'original_teacher_id' => $group['staff_profile_id'],
                    'session_type' => 'group',
                    'status' => $status,
                    'scheduled_start' => self::ts($scheduledStart),
                    'scheduled_end' => self::ts($scheduledEnd),
                    'actual_start' => null,
                    'actual_end' => null,
                    'finalized_at' => null,
                    'finalized_by' => null,
                    'cancelled_by' => null,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    'notes' => null,
                    'title' => self::js($title),
                    'created_at' => self::ts($now),
                    'updated_at' => self::ts($now),
                ];

                if ($status === 'completed') {
                    $row['actual_start'] = self::ts($scheduledStart);
                    $row['actual_end'] = self::ts($scheduledEnd);
                    $row['finalized_at'] = self::ts($scheduledEnd->addMinutes(7));
                    $row['finalized_by'] = $actorId;
                } elseif ($status === 'no_show') {
                    $row['finalized_at'] = self::ts($scheduledEnd);
                    $row['finalized_by'] = $actorId;
                    $row['notes'] = 'لم يحضر أي طالب ولم يصل إشعار إلغاء.';
                } elseif ($status === 'cancelled_by_student') {
                    $row['cancelled_by'] = $actorId;
                    $row['cancelled_at'] = self::ts($scheduledStart->subMinutes(95));
                    $row['cancellation_reason'] = 'اعتذار ولي الأمر قبل انقضاء مهلة الإلغاء.';
                } elseif ($status === 'postponed') {
                    $row['notes'] = 'أُجّلت بقرار الإدارة — بانتظار تحديد موعد التلافي.';
                }

                $memberSlots = $this->memberIndexes($groupKey);

                $meta[] = [
                    'row' => $row,
                    'group_key' => $groupKey,
                    'course_name' => $group['course_name'],
                    'status' => $status,
                    'scheduled_start' => $scheduledStart,
                    'scheduled_end' => $scheduledEnd,
                    'members' => $memberSlots,
                    'position' => $position,
                ];
            }
        }

        $rows = array_map(fn (array $item): array => $item['row'], $meta);

        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('sessions')->insert($chunk);
        }

        $this->seedParticipants($meta, $enrollments, $students, $now);

        return $meta;
    }

    /**
     * @param array<int, array<string, mixed>> $sessions
     * @param array<string, string> $enrollments
     * @param array<int, string> $students
     * @return array<int, array<string, mixed>>
     */
    private function seedParticipants(
        array $sessions,
        array $enrollments,
        array $students,
        CarbonImmutable $now,
    ): array {
        $rows = [];

        foreach ($sessions as $session) {
            if ($session['status'] === 'scheduled') {
                continue;
            }

            $isHeld = in_array($session['status'], ['completed', 'no_show'], true);
            $programKey = $this->programKeyOfGroup($session['group_key']);

            foreach ($session['members'] as $slot) {
                $index = $slot['index'];
                $enrollmentId = $enrollments[$index.':'.$programKey] ?? null;

                if ($enrollmentId === null) {
                    continue;
                }

                $attendanceKey = $this->attendanceOutcome($session, $index);

                $firstJoined = null;
                $lastLeft = null;
                $attendedMinutes = 0;

                if ($isHeld && in_array($attendanceKey, ['present', 'late'], true)) {
                    $firstJoined = $session['scheduled_start']->addMinutes($attendanceKey === 'late' ? 9 : 1);
                    $lastLeft = $session['scheduled_end'];
                    $attendedMinutes = $attendanceKey === 'late'
                        ? max($session['scheduled_end']->diffInMinutes($firstJoined), 1)
                        : (int) $session['scheduled_start']->diffInMinutes($session['scheduled_end']);
                }

                $participantId = self::ulid();

                $rows[] = [
                    'table' => 'session_participants',
                    'values' => [
                        'id' => $participantId,
                        'session_id' => $session['row']['id'],
                        'student_profile_id' => $students[$index],
                        'enrollment_id' => $enrollmentId,
                        'join_url_token' => strtolower((string) Str::ulid()),
                        'invited_at' => self::ts($session['scheduled_start']->subDay()),
                        'first_joined_at' => $firstJoined === null ? null : self::ts($firstJoined),
                        'last_left_at' => $lastLeft === null ? null : self::ts($lastLeft),
                        'attended_minutes' => $attendedMinutes,
                        'created_at' => self::ts($now),
                    ],
                    'session_status' => $session['status'],
                    'outcome' => $attendanceKey,
                    'scheduled_end' => $session['scheduled_end'],
                ];
            }
        }

        foreach (array_chunk(array_column($rows, 'values'), 50) as $chunk) {
            DB::table('session_participants')->insert($chunk);
        }

        $this->pendingAttendanceRows = $rows;

        return $rows;
    }

    /** @var array<int, array<string, mixed>> */
    private array $pendingAttendanceRows = [];

    /**
     * @param array<int, array<string, mixed>> $sessions
     */
    private function seedAttendances(array $sessions, string $actorId, CarbonImmutable $now): void
    {
        $rows = [];

        foreach ($this->pendingAttendanceRows as $participant) {
            $outcome = $participant['outcome'];
            $held = in_array($participant['session_status'], ['completed', 'no_show'], true);

            if (!$held) {
                $status = 'not_held';
            } elseif ($participant['session_status'] === 'no_show') {
                $status = 'no_show';
            } else {
                $status = $outcome;
            }

            $attended = match ($status) {
                'present' => 60,
                'late' => 52,
                default => 0,
            };

            $joinedAfter = $status === 'late' ? 9 : 0;
            $confirmed = $held && !in_array($status, ['excused'], true);

            $rows[] = [
                'id' => self::ulid(),
                'session_participant_id' => $participant['values']['id'],
                'status' => $status,
                'derived_status' => $status,
                'attended_minutes' => $attended,
                'joined_after_minutes' => $joinedAfter,
                'left_before_minutes' => 0,
                'confirmed_by' => $confirmed ? $actorId : null,
                'confirmed_at' => $confirmed ? self::ts($participant['scheduled_end']) : null,
                'override_reason' => null,
                'created_at' => self::ts($now),
                'updated_at' => self::ts($now),
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('attendances')->insert($chunk);
        }
    }

    /**
     * @param array<string, mixed> $session
     */
    private function attendanceOutcome(array $session, int $studentIndex): string
    {
        if ($session['status'] === 'no_show') {
            return 'absent';
        }

        $cycle = (($session['position'] + 1) * ($studentIndex + 3)) % 8;

        return match (true) {
            $cycle <= 4 => 'present',
            $cycle === 5 => 'late',
            $cycle === 6 => 'absent',
            default => 'excused',
        };
    }

    /** @var array<string, array<int, array{index: int}>> */
    private array $groupMemberIndexes = [
        'g1' => [
            ['index' => 0],
            ['index' => 1],
            ['index' => 2],
        ],
        'g2' => [
            ['index' => 3],
            ['index' => 4],
        ],
        'g3' => [
            ['index' => 0],
            ['index' => 2],
            ['index' => 3],
        ],
        'g4' => [
            ['index' => 1],
            ['index' => 4],
        ],
    ];

    /**
     * @return array<int, array{index: int}>
     */
    private function memberIndexes(string $groupKey): array
    {
        return $this->groupMemberIndexes[$groupKey];
    }

    /** @var array<string, string> */
    private array $groupProgramKeys = [
        'g1' => 'quran',
        'g2' => 'quran',
        'g3' => 'english',
        'g4' => 'coding',
    ];

    private function programKeyOfGroup(string $groupKey): string
    {
        return $this->groupProgramKeys[$groupKey];
    }

    /**
     * @param array<int, array<string, mixed>> $sessions
     * @param array<string, array{staff_profile_id: string, contract_id: string, rate: int, resolved_via: string}> $teachers
     */
    private function seedPayroll(
        string $organizationId,
        array $sessions,
        array $teachers,
        CarbonImmutable $now,
    ): void {
        $periodStart = $now->startOfDay()->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $periodId = DB::table('payroll_periods')
            ->where('organization_id', $organizationId)
            ->where('year', (int) $periodStart->format('Y'))
            ->where('month', (int) $periodStart->format('n'))
            ->value('id');

        if (is_string($periodId) && $periodId !== '') {
            $periodStatus = DB::table('payroll_periods')->where('id', $periodId)->value('status');

            if ($periodStatus !== 'open') {
                throw new RuntimeException('DemoDataSeeder: فترة الرواتب الحالية مقفلة ولا تقبل قيود العرض.');
            }
        } else {
            $periodId = self::ulid();
        }

        $entries = [];

        foreach ($sessions as $session) {
            if (!in_array($session['status'], ['completed', 'no_show'], true)) {
                continue;
            }

            $teacherKey = $this->teacherKeyOfGroup($session['group_key']);
            $teacher = $teachers[$teacherKey];

            if ($teacher['rate'] <= 0) {
                continue;
            }

            $amount = $teacher['rate'];
            $outcomeKey = $session['status'] === 'completed' ? 'completed' : 'student_no_show';

            $entries[] = [
                'id' => self::ulid(),
                'organization_id' => $organizationId,
                'payroll_period_id' => $periodId,
                'staff_profile_id' => $teacher['staff_profile_id'],
                'session_id' => $session['row']['id'],
                'teacher_contract_id' => $teacher['contract_id'],
                'entry_type' => 'session_earning',
                'outcome_key' => $outcomeKey,
                'amount' => $amount,
                'currency' => 'EGP',
                'rate_snapshot' => self::js([
                    'amount_minor_units' => $amount,
                    'currency' => 'EGP',
                    'resolved_via' => $teacher['resolved_via'],
                    'session_time' => [
                        'start' => $session['scheduled_start']->toIso8601String(),
                        'end' => $session['scheduled_end']->toIso8601String(),
                    ],
                    'captured_at' => $now->toIso8601String(),
                ]),
                'status' => 'recorded',
                'description' => self::js([
                    'ar' => 'مستحقات حصة '.$session['course_name']['ar'],
                    'en' => 'Session earning',
                ]),
                'created_at' => self::ts($session['scheduled_end']),
            ];
        }

        $totalMinorUnits = array_sum(array_column($entries, 'amount'));

        $periodExists = DB::table('payroll_periods')->where('id', $periodId)->exists();

        if (!$periodExists) {
            DB::table('payroll_periods')->insert([
                'id' => $periodId,
                'organization_id' => $organizationId,
                'year' => (int) $periodStart->format('Y'),
                'month' => (int) $periodStart->format('n'),
                'starts_on' => $periodStart->toDateString(),
                'ends_on' => $periodEnd->toDateString(),
                'status' => 'open',
                'totals' => self::js([
                    'currency' => 'EGP',
                    'entries_count' => count($entries),
                    'total_minor_units' => $totalMinorUnits,
                ]),
                'created_at' => self::ts($now),
                'updated_at' => self::ts($now),
            ]);
        }

        foreach (array_chunk($entries, 50) as $chunk) {
            DB::table('payroll_entries')->insert($chunk);
        }

        $periodTotals = DB::table('payroll_entries')
            ->where('payroll_period_id', $periodId)
            ->selectRaw('COUNT(*) AS entries_count, COALESCE(SUM(amount), 0) AS total_minor_units')
            ->first();

        DB::table('payroll_periods')->where('id', $periodId)->update([
            'totals' => self::js([
                'currency' => 'EGP',
                'entries_count' => (int) ($periodTotals->entries_count ?? 0),
                'total_minor_units' => (int) ($periodTotals->total_minor_units ?? 0),
            ]),
            'updated_at' => self::ts($now),
        ]);
    }

    /** @var array<string, string> */
    private array $groupTeacherKeys = [
        'g1' => 't3',
        'g2' => 't1',
        'g3' => 't4',
        'g4' => 't5',
    ];

    private function teacherKeyOfGroup(string $groupKey): string
    {
        return $this->groupTeacherKeys[$groupKey];
    }

    private static function ulid(): string
    {
        return (string) Str::ulid();
    }

    private static function ts(CarbonImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:s');
    }

    /**
     * @param array<mixed> $value
     */
    private static function js(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
