<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;

final readonly class PortalData
{
    public function studentProfileId(string $userId, string $organizationId): ?string
    {
        $id = DB::table('student_profiles')
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->value('id');

        return $id === null ? null : (string) $id;
    }

    /** @return array<string, mixed>|null */
    public function studentProfile(string $userId, string $organizationId, string $locale): ?array
    {
        $row = DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.user_id', $userId)
            ->where('student_profiles.organization_id', $organizationId)
            ->whereNull('student_profiles.deleted_at')
            ->first([
                'student_profiles.id', 'student_profiles.student_code',
                'student_profiles.country', 'student_profiles.city',
                'student_profiles.date_of_birth', 'users.name', 'users.email', 'users.phone',
            ]);

        return $row === null ? null : [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'code' => (string) $row->student_code,
            'email' => (string) $row->email,
            'phone' => $row->phone,
            'country' => $row->country,
            'city' => $row->city,
            'dateOfBirth' => $row->date_of_birth,
        ];
    }

    /**
     * حقول الحساب القابلة للتحرير من البوابة.
     *
     * البريد واسم المستخدم ليسا هنا: تغييرهما يمس الدخول والاستعادة، وقراره
     * إداري بتدقيق — لذلك يُعرضان للقراءة فقط.
     *
     * @return array<string, mixed>|null
     */
    public function accountSettings(string $userId, string $organizationId): ?array
    {
        $row = DB::table('users')
            ->where('id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->first([
                'name',
                'username',
                'email',
                'phone',
                'phone_country',
                'locale',
                'timezone',
                'status',
                'last_login_at',
            ]);

        return $row === null ? null : [
            'name' => (string) $row->name,
            'username' => $row->username === null ? null : (string) $row->username,
            'email' => (string) $row->email,
            'phone' => $row->phone === null ? null : (string) $row->phone,
            'phoneCountry' => $row->phone_country === null ? null : (string) $row->phone_country,
            'locale' => (string) $row->locale,
            'timezone' => (string) $row->timezone,
            'status' => (string) $row->status,
            'lastLoginAt' => $this->iso($row->last_login_at),
        ];
    }

    /**
     * المناطق الزمنية المعروضة في اختيار المستخدم.
     *
     * @return list<string>
     */
    public function timezoneOptions(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    /** @return list<array<string, mixed>> */
    public function studentPrograms(string $studentProfileId, string $organizationId, string $locale): array
    {
        return DB::table('enrollments')
            ->join('programs', 'programs.id', '=', 'enrollments.program_id')
            ->leftJoin('levels', 'levels.id', '=', 'enrollments.current_level_id')
            ->where('enrollments.student_profile_id', $studentProfileId)
            ->where('enrollments.organization_id', $organizationId)
            ->whereNull('enrollments.deleted_at')
            ->whereNull('programs.deleted_at')
            ->orderBy('programs.sort_order')
            ->get([
                'enrollments.id',
                'enrollments.status',
                'enrollments.applied_at',
                'enrollments.activated_at',
                'enrollments.completed_at',
                'enrollments.frozen_at',
                'enrollments.frozen_reason',
                'enrollments.freeze_type',
                'enrollments.expected_return_date',
                'programs.code',
                'programs.name',
                'programs.description',
                'levels.name as level_name',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'title' => $this->localized($row->name, $locale),
                'description' => $this->localized($row->description, $locale),
                'status' => (string) $row->status,
                'levelName' => $row->level_name === null
                    ? null
                    : $this->localized($row->level_name, $locale),
                'appliedAt' => $this->iso($row->applied_at),
                'activatedAt' => $this->iso($row->activated_at),
                'completedAt' => $this->iso($row->completed_at),
                'frozenAt' => $this->iso($row->frozen_at),
                /*
                 * سبب التجميد يظهر للطالب لأن قرار تجميده يخصّه، ولا يجوز أن
                 * يُمنع من الحصص دون أن يعرف لماذا ومتى يُتوقع رجوعه.
                 */
                'frozenReason' => $row->frozen_reason === null ? null : (string) $row->frozen_reason,
                'freezeType' => $row->freeze_type === null ? null : (string) $row->freeze_type,
                'expectedReturnDate' => $row->expected_return_date === null
                    ? null
                    : (string) $row->expected_return_date,
            ])->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function studentGroup(string $studentProfileId, string $organizationId, string $locale): ?array
    {
        $row = DB::table('group_memberships')
            ->join('groups', 'groups.id', '=', 'group_memberships.group_id')
            ->leftJoin('group_teachers', 'group_teachers.group_id', '=', 'groups.id')
            ->leftJoin('staff_profiles', 'staff_profiles.id', '=', 'group_teachers.staff_profile_id')
            ->leftJoin('users as teachers', 'teachers.id', '=', 'staff_profiles.user_id')
            ->where('group_memberships.student_profile_id', $studentProfileId)
            ->whereNull('group_memberships.left_at')
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->first([
                'groups.id', 'groups.code', 'groups.name', 'groups.capacity',
                DB::raw('(select count(*) from group_memberships gm2 where gm2.group_id = groups.id and gm2.left_at is null) as members_count'),
                'teachers.name as teacher_name',
            ]);

        return $row === null ? null : [
            'id' => (string) $row->id,
            'code' => (string) $row->code,
            'name' => $this->localized($row->name, $locale),
            'capacity' => (int) $row->capacity,
            'membersCount' => (int) $row->members_count,
            'teacherName' => $row->teacher_name,
        ];
    }

    /**
     * كل مجموعات الطالب الفاعلة مع زملائه ومعلميها وبرامجها.
     *
     * الطالب قد يدرس أكثر من برنامج، فيكون له أكثر من صف تشغيلي. عرض مجموعة
     * واحدة يخفي عنه بقية صفوفه، لذلك الإرجاع قائمة لا سجل واحد.
     *
     * @return list<array<string, mixed>>
     */
    public function studentGroupsDetailed(
        string $studentProfileId,
        string $organizationId,
        string $locale,
    ): array {
        $groups = DB::table('group_memberships')
            ->join('groups', 'groups.id', '=', 'group_memberships.group_id')
            ->where('group_memberships.student_profile_id', $studentProfileId)
            ->whereNull('group_memberships.left_at')
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->orderBy('groups.code')
            ->get([
                'groups.id',
                'groups.code',
                'groups.name',
                'groups.capacity',
                'groups.status',
                'groups.timezone',
                'groups.starts_on',
                'groups.ends_on',
                'group_memberships.joined_at',
            ]);

        return $groups->map(function (object $row) use ($locale, $organizationId, $studentProfileId): array {
            $groupId = (string) $row->id;

            return [
                'id' => $groupId,
                'code' => (string) $row->code,
                'name' => $this->localized($row->name, $locale),
                'capacity' => (int) $row->capacity,
                'status' => (string) $row->status,
                'timezone' => $this->validTimezone((string) $row->timezone),
                'startsOn' => $row->starts_on === null ? null : (string) $row->starts_on,
                'endsOn' => $row->ends_on === null ? null : (string) $row->ends_on,
                'joinedAt' => $row->joined_at === null ? null : (string) $row->joined_at,
                'teachers' => $this->groupTeachers($groupId, $locale),
                'programs' => $this->groupPrograms($groupId, $locale),
                'classmates' => $this->groupClassmates($groupId, $studentProfileId),
                'membersCount' => $this->groupMembersCount($groupId),
                'nextSession' => $this->studentNextSessionInGroup(
                    $groupId,
                    $studentProfileId,
                    $organizationId,
                    $locale,
                ),
            ];
        })->values()->all();
    }

    /**
     * أقرب حصة قادمة للطالب داخل مجموعة بعينها.
     *
     * @return array<string, mixed>|null
     */
    public function studentNextSessionInGroup(
        string $groupId,
        string $studentProfileId,
        string $organizationId,
        string $locale,
    ): ?array {
        $row = $this->baseSessionsQuery($organizationId)
            ->join('session_participants', 'session_participants.session_id', '=', 'sessions.id')
            ->where('session_participants.student_profile_id', $studentProfileId)
            ->where('sessions.group_id', $groupId)
            ->where('sessions.scheduled_end', '>=', CarbonImmutable::now('UTC'))
            ->orderBy('sessions.scheduled_start')
            ->first();

        return $row === null ? null : $this->mapSession($row, $locale);
    }

    /**
     * معلمو مجموعة مع الدورة/المادة التي يدرّسونها فيها.
     *
     * @return list<array<string, mixed>>
     */
    public function groupTeachers(string $groupId, string $locale): array
    {
        return DB::table('group_teachers')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'group_teachers.staff_profile_id')
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->leftJoin('courses', 'courses.id', '=', 'group_teachers.course_id')
            ->where('group_teachers.group_id', $groupId)
            ->whereNull('staff_profiles.deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('group_teachers.assigned_to')
                    ->orWhere('group_teachers.assigned_to', '>=', now('UTC')->toDateString());
            })
            ->orderBy('users.name')
            ->get([
                'staff_profiles.id',
                'users.name',
                'courses.name as course_name',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'courseName' => $row->course_name === null
                    ? null
                    : $this->localized($row->course_name, $locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupPrograms(string $groupId, string $locale): array
    {
        return DB::table('group_programs')
            ->join('programs', 'programs.id', '=', 'group_programs.program_id')
            ->where('group_programs.group_id', $groupId)
            ->whereNull('programs.deleted_at')
            ->orderBy('programs.sort_order')
            ->get(['programs.id', 'programs.code', 'programs.name'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'name' => $this->localized($row->name, $locale),
            ])
            ->values()
            ->all();
    }

    /**
     * زملاء الصف بأسمائهم فقط.
     *
     * لا تُكشف بيانات اتصال ولا مؤشرات أداء زميل لزميله؛ الاسم وحده ما يخدم
     * الغرض التربوي ولا يوسّع سطح الخصوصية.
     *
     * @return list<array<string, mixed>>
     */
    public function groupClassmates(string $groupId, string $excludeStudentProfileId): array
    {
        return DB::table('group_memberships')
            ->join('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('group_memberships.group_id', $groupId)
            ->whereNull('group_memberships.left_at')
            ->whereNull('student_profiles.deleted_at')
            ->where('student_profiles.id', '!=', $excludeStudentProfileId)
            ->orderBy('users.name')
            ->get(['student_profiles.id', 'users.name'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    public function groupMembersCount(string $groupId): int
    {
        return DB::table('group_memberships')
            ->where('group_id', $groupId)
            ->whereNull('left_at')
            ->count();
    }

    public function staffProfileId(string $userId, string $organizationId): ?string
    {
        $id = DB::table('staff_profiles')
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->value('id');

        return $id === null ? null : (string) $id;
    }

    /** @return array<string, mixed>|null */
    public function teacherProfile(string $userId, string $organizationId): ?array
    {
        $row = DB::table('staff_profiles')
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->where('staff_profiles.user_id', $userId)->where('staff_profiles.organization_id', $organizationId)
            ->whereNull('staff_profiles.deleted_at')->first([
                'staff_profiles.id', 'staff_profiles.staff_code', 'staff_profiles.bio', 'staff_profiles.specializations',
                'users.name', 'users.email', 'users.phone',
            ]);
        if ($row === null) {
            return null;
        }

        return ['id' => (string) $row->id, 'name' => (string) $row->name, 'code' => (string) $row->staff_code,
            'email' => (string) $row->email, 'phone' => $row->phone,
            'specializations' => array_values(array_filter((array) $this->json($row->specializations))),
            'bio' => $this->json($row->bio)];
    }

    /**
     * الدورات التي اعتُمد تأهيل المعلم لها.
     *
     * التأهيل مملوك لموديول Staff ويُقرأ هنا للعرض فقط؛ الكتابة عليه قرار
     * إشرافي ولا تمر من بوابة المعلم.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherQualifications(string $staffProfileId, string $locale): array
    {
        return DB::table('teacher_courses')
            ->join('courses', 'courses.id', '=', 'teacher_courses.course_id')
            ->leftJoin('levels', 'levels.id', '=', 'courses.level_id')
            ->leftJoin('programs', 'programs.id', '=', 'levels.program_id')
            ->where('teacher_courses.staff_profile_id', $staffProfileId)
            ->whereNull('courses.deleted_at')
            ->orderBy('courses.code')
            ->get([
                'courses.id',
                'courses.code',
                'courses.name',
                'programs.name as program_name',
                'teacher_courses.qualified_at',
                'teacher_courses.notes',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'name' => $this->localized($row->name, $locale),
                'programName' => $row->program_name === null
                    ? null
                    : $this->localized($row->program_name, $locale),
                'qualifiedAt' => $this->iso($row->qualified_at),
                'notes' => $row->notes === null ? null : (string) $row->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * مجموعات المعلم مع برامجها وأقرب حصة قادمة فيها.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherGroupsDetailed(
        string $staffProfileId,
        string $organizationId,
        string $locale,
    ): array {
        $groups = DB::table('group_teachers')
            ->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->leftJoin('courses', 'courses.id', '=', 'group_teachers.course_id')
            ->where('group_teachers.staff_profile_id', $staffProfileId)
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('group_teachers.assigned_to')
                    ->orWhere('group_teachers.assigned_to', '>=', now('UTC')->toDateString());
            })
            ->orderBy('groups.code')
            ->get([
                'groups.id',
                'groups.code',
                'groups.name',
                'groups.capacity',
                'groups.status',
                'groups.timezone',
                'groups.starts_on',
                'groups.ends_on',
                'group_teachers.role',
                'courses.name as course_name',
            ]);

        return $groups->map(function (object $row) use ($locale, $organizationId, $staffProfileId): array {
            $groupId = (string) $row->id;

            return [
                'id' => $groupId,
                'code' => (string) $row->code,
                'name' => $this->localized($row->name, $locale),
                'capacity' => (int) $row->capacity,
                'status' => (string) $row->status,
                'timezone' => $this->validTimezone((string) $row->timezone),
                'startsOn' => $row->starts_on === null ? null : (string) $row->starts_on,
                'endsOn' => $row->ends_on === null ? null : (string) $row->ends_on,
                'role' => (string) $row->role,
                'courseName' => $row->course_name === null
                    ? null
                    : $this->localized($row->course_name, $locale),
                'studentsCount' => $this->groupMembersCount($groupId),
                'programs' => $this->groupPrograms($groupId, $locale),
                'nextSession' => $this->groupNextSession(
                    $groupId,
                    $staffProfileId,
                    $organizationId,
                    $locale,
                ),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function groupNextSession(
        string $groupId,
        string $staffProfileId,
        string $organizationId,
        string $locale,
    ): ?array {
        $row = $this->baseSessionsQuery($organizationId)
            ->where('sessions.group_id', $groupId)
            ->where('sessions.staff_profile_id', $staffProfileId)
            ->where('sessions.scheduled_end', '>=', CarbonImmutable::now('UTC'))
            ->orderBy('sessions.scheduled_start')
            ->first();

        return $row === null ? null : $this->mapSession($row, $locale);
    }

    /**
     * طلاب المعلم مع مؤشرات المتابعة التي يحتاجها فعلًا.
     *
     * النطاق صارم: من كان عضوًا فاعلًا في مجموعة يدرّسها هذا المعلم فقط.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherStudentsDetailed(
        string $staffProfileId,
        string $organizationId,
        string $locale,
    ): array {
        $rows = DB::table('group_teachers')
            ->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->join('group_memberships', 'group_memberships.group_id', '=', 'groups.id')
            ->join('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('group_teachers.staff_profile_id', $staffProfileId)
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->whereNull('student_profiles.deleted_at')
            ->whereNull('group_memberships.left_at')
            ->distinct()
            ->orderBy('users.name')
            ->get([
                'student_profiles.id',
                'student_profiles.student_code',
                'student_profiles.date_of_birth',
                'student_profiles.gender',
                'users.name',
                'groups.id as group_id',
                'groups.name as group_name',
                'group_memberships.joined_at',
            ]);

        return $rows->map(function (object $row) use ($locale, $organizationId): array {
            $studentProfileId = (string) $row->id;

            return [
                'id' => $studentProfileId,
                'name' => (string) $row->name,
                'code' => (string) $row->student_code,
                'gender' => $row->gender === null ? null : (string) $row->gender,
                'groupId' => (string) $row->group_id,
                'groupName' => $this->localized($row->group_name, $locale),
                'joinedAt' => $row->joined_at === null ? null : (string) $row->joined_at,
                'attendanceRate' => $this->attendanceRate($studentProfileId, $organizationId),
                'openAssignmentsCount' => $this->studentOpenAssignmentsCount(
                    $studentProfileId,
                    $organizationId,
                ),
            ];
        })->values()->all();
    }

    /**
     * عدد التكليفات التي لم يسلّمها الطالب بعد.
     */
    public function studentOpenAssignmentsCount(string $studentProfileId, string $organizationId): int
    {
        $submittedStatuses = [
            AssignmentSubmissionStatus::Submitted->value,
            AssignmentSubmissionStatus::Late->value,
            AssignmentSubmissionStatus::Graded->value,
        ];

        return DB::table('assignments')
            ->leftJoin('assignment_submissions', function (JoinClause $join) use ($studentProfileId): void {
                $join->on('assignment_submissions.assignment_id', '=', 'assignments.id')
                    ->where('assignment_submissions.student_profile_id', '=', $studentProfileId);
            })
            ->where('assignments.organization_id', $organizationId)
            ->whereNull('assignments.deleted_at')
            ->where('assignments.assigned_at', '<=', CarbonImmutable::now('UTC'))
            ->whereExists(function (Builder $scope) use ($studentProfileId, $organizationId): void {
                $scope->selectRaw('1')
                    ->from('session_participants')
                    ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
                    ->where('session_participants.student_profile_id', $studentProfileId)
                    ->where('sessions.organization_id', $organizationId)
                    ->whereNull('sessions.deleted_at')
                    ->whereColumn('sessions.course_id', 'assignments.course_id')
                    ->where(function (Builder $group): void {
                        $group->whereNull('assignments.group_id')
                            ->orWhereColumn('sessions.group_id', 'assignments.group_id');
                    });
            })
            ->where(function (Builder $query) use ($submittedStatuses): void {
                $query->whereNull('assignment_submissions.status')
                    ->orWhereNotIn('assignment_submissions.status', $submittedStatuses);
            })
            ->count();
    }

    /** @return list<array<string, mixed>> */
    public function teacherGroups(string $staffProfileId, string $organizationId, string $locale): array
    {
        return DB::table('group_teachers')->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->where('group_teachers.staff_profile_id', $staffProfileId)->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')->where(function (Builder $q): void {
                $q->whereNull('group_teachers.assigned_to')->orWhere('group_teachers.assigned_to', '>=', now('UTC')->toDateString());
            })
            ->orderBy('groups.code')->get(['groups.id', 'groups.code', 'groups.name', 'groups.capacity'])
            ->map(fn (object $row): array => ['id' => (string) $row->id, 'code' => (string) $row->code, 'name' => $this->localized($row->name, $locale),
                'capacity' => (int) $row->capacity, 'studentsCount' => (int) DB::table('group_memberships')->where('group_id', $row->id)->whereNull('left_at')->count()])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function teacherStudents(string $staffProfileId, string $organizationId, string $locale): array
    {
        return DB::table('group_teachers')->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->join('group_memberships', 'group_memberships.group_id', '=', 'groups.id')->join('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')->where('group_teachers.staff_profile_id', $staffProfileId)->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')->whereNull('student_profiles.deleted_at')->whereNull('group_memberships.left_at')
            ->distinct()->orderBy('users.name')->get(['student_profiles.id', 'student_profiles.student_code', 'users.name', 'groups.name as group_name'])
            ->map(fn (object $row): array => ['id' => (string) $row->id, 'name' => (string) $row->name, 'code' => (string) $row->student_code, 'groupName' => $this->localized($row->group_name, $locale)])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function teacherAvailability(string $staffProfileId): array
    {
        return DB::table('teacher_availability')->where('staff_profile_id', $staffProfileId)->orderBy('weekday')->orderBy('start_time')->get()
            ->map(fn (object $row): array => ['id' => (string) $row->id, 'weekday' => (int) $row->weekday, 'startTime' => (string) $row->start_time, 'endTime' => (string) $row->end_time, 'timezone' => (string) $row->timezone, 'effectiveFrom' => (string) $row->effective_from, 'effectiveTo' => $row->effective_to, 'approvalStatus' => (string) $row->approval_status])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upcomingStudentSessions(
        string $studentProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = $this->studentSessionsQuery($studentProfileId, $organizationId)
            ->where('sessions.scheduled_end', '>=', CarbonImmutable::now('UTC'))
            ->orderBy('sessions.scheduled_start')
            ->get();

        return $this->mapSessions($rows, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function studentWeekSessions(
        string $studentProfileId,
        string $locale,
        string $timezone,
        string $organizationId,
    ): array {
        $now = CarbonImmutable::now($this->validTimezone($timezone));
        $from = $now->startOfWeek()->utc();
        $until = $now->endOfWeek()->utc();

        $rows = $this->studentSessionsQuery($studentProfileId, $organizationId)
            ->whereBetween('sessions.scheduled_start', [$from, $until])
            ->orderBy('sessions.scheduled_start')
            ->get();

        return $this->mapSessions($rows, $locale);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function studentSession(
        string $studentProfileId,
        string $sessionId,
        string $locale,
        string $organizationId,
    ): ?array {
        $row = $this->studentSessionsQuery($studentProfileId, $organizationId)
            ->where('sessions.id', $sessionId)
            ->first();

        return $row === null ? null : $this->mapSession($row, $locale);
    }

    public function attendanceRate(string $studentProfileId, string $organizationId): ?float
    {
        $query = DB::table('attendances')
            ->join(
                'session_participants',
                'session_participants.id',
                '=',
                'attendances.session_participant_id',
            )
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->where('session_participants.student_profile_id', $studentProfileId)
            ->where('sessions.organization_id', $organizationId)
            ->whereNull('sessions.deleted_at');

        $attendedStatuses = array_map(
            static fn (AttendanceStatus $status): string => $status->value,
            array_values(array_filter(
                AttendanceStatus::cases(),
                static fn (AttendanceStatus $status): bool => $status->isPresent(),
            )),
        );
        $countedStatuses = array_map(
            static fn (AttendanceStatus $status): string => $status->value,
            array_values(array_filter(
                AttendanceStatus::cases(),
                static fn (AttendanceStatus $status): bool => $status->isPresent()
                    || $status->isViolation(),
            )),
        );
        $total = (clone $query)->whereIn('attendances.status', $countedStatuses)->count();

        if ($total === 0) {
            return null;
        }

        $attended = (clone $query)->whereIn('attendances.status', $attendedStatuses)->count();

        return round($attended / $total, 4);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function studentAssignments(
        string $studentProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = DB::table('assignments')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->leftJoin('assignment_submissions', function (JoinClause $join) use ($studentProfileId): void {
                $join->on('assignment_submissions.assignment_id', '=', 'assignments.id')
                    ->where('assignment_submissions.student_profile_id', '=', $studentProfileId);
            })
            ->where('assignments.organization_id', $organizationId)
            ->whereColumn('courses.organization_id', 'assignments.organization_id')
            ->whereNull('assignments.deleted_at')
            ->where('assignments.assigned_at', '<=', CarbonImmutable::now('UTC'))
            ->whereExists(function (Builder $scope) use ($studentProfileId, $organizationId): void {
                $scope->selectRaw('1')
                    ->from('session_participants')
                    ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
                    ->where('session_participants.student_profile_id', $studentProfileId)
                    ->where('sessions.organization_id', $organizationId)
                    ->whereNull('sessions.deleted_at')
                    ->whereColumn('sessions.course_id', 'assignments.course_id')
                    ->where(function (Builder $group): void {
                        $group->whereNull('assignments.group_id')
                            ->orWhereColumn('sessions.group_id', 'assignments.group_id');
                    });
            })
            ->orderBy('assignments.due_at')
            ->get([
                'assignments.id',
                'assignments.title as assignment_title',
                'assignments.instructions',
                'assignments.due_at',
                'assignments.allows_late',
                'assignments.max_score',
                'assignments.late_penalty_percent',
                'courses.name as course_name',
                'assignment_submissions.status as submission_status',
                'assignment_submissions.submitted_at',
                'assignment_submissions.content as submission_content',
                'assignment_submissions.score',
                'assignment_submissions.feedback',
                'assignment_submissions.graded_at',
            ]);

        return $rows->map(function (object $row) use ($locale): array {
            $dueAt = CarbonImmutable::parse((string) $row->due_at, 'UTC')->utc();
            $submissionStatus = $row->submission_status === null
                ? ($dueAt->isPast() ? 'overdue' : 'open')
                : (string) $row->submission_status;
            $allowsLate = (bool) $row->allows_late;
            $isPastDue = $dueAt->isPast();

            /*
             * التسليم متاح ما لم يكن الواجب مرصودًا، وبعد الموعد يشترط
             * سماح الواجب بالتأخير. القرار النهائي يبقى لـSubmitAssignmentAction؛
             * هذه القيمة تحكم ظهور النموذج فقط ولا تغني عن التحقق الخادمي.
             */
            $canSubmit = $submissionStatus !== AssignmentSubmissionStatus::Graded->value
                && (!$isPastDue || $allowsLate);

            return [
                'id' => (string) $row->id,
                'title' => $this->localized($row->assignment_title, $locale),
                'instructions' => $this->localized($row->instructions, $locale),
                'courseName' => $this->localized($row->course_name, $locale),
                'dueAt' => $dueAt->toIso8601String(),
                'status' => $isPastDue ? 'closed' : 'open',
                'submissionStatus' => $submissionStatus,
                'submittedAt' => $this->iso($row->submitted_at),
                'submissionContent' => $row->submission_content === null
                    ? null
                    : (string) $row->submission_content,
                'allowsLate' => $allowsLate,
                'latePenaltyPercent' => (int) $row->late_penalty_percent,
                'maxScore' => (int) $row->max_score,
                'score' => $row->score === null ? null : (int) $row->score,
                'feedback' => $row->feedback === null ? null : (string) $row->feedback,
                'gradedAt' => $this->iso($row->graded_at),
                'canSubmit' => $canSubmit,
                'submitUrl' => route('portal.student.assignments.submit', ['assignment' => (string) $row->id]),
                'url' => null,
            ];
        })->values()->all();
    }

    /**
     * @param list<array<string, mixed>> $assignments
     * @return list<array<string, mixed>>
     */
    public function openAssignments(array $assignments): array
    {
        $submittedStatuses = [
            AssignmentSubmissionStatus::Submitted->value,
            AssignmentSubmissionStatus::Late->value,
            AssignmentSubmissionStatus::Graded->value,
        ];

        return array_values(array_filter(
            $assignments,
            static fn (array $assignment): bool => !in_array(
                $assignment['submissionStatus'],
                $submittedStatuses,
                true,
            ),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function monthlyReports(
        string $studentProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = DB::table('monthly_reports')
            ->where('student_profile_id', $studentProfileId)
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                MonthlyReportStatus::Approved->value,
                MonthlyReportStatus::Sent->value,
            ])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();

        return $rows->map(function (object $row) use ($locale): array {
            $monthDate = CarbonImmutable::create(
                (int) $row->period_year,
                (int) $row->period_month,
                1,
                0,
                0,
                0,
                'UTC',
            );
            $month = $monthDate->locale($locale)->translatedFormat('F Y');
            $metrics = $this->json($row->metrics);
            $attendanceRate = $this->numericMetric($metrics, [
                'attendance_rate',
                'attendanceRate',
                'attendance.rate',
            ]);

            return [
                'id' => (string) $row->id,
                'month' => $month,
                'title' => $this->translation(
                    'reports.monthly_title',
                    $locale,
                    ['month' => $month],
                ),
                'status' => (string) $row->status,
                'issuedAt' => $this->iso($row->sent_at ?? $row->approved_at ?? $row->updated_at),
                'attendanceRate' => $attendanceRate,
                'summary' => $row->supervisor_summary === null
                    ? null
                    : (string) $row->supervisor_summary,
                'downloadUrl' => null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teacherTodaySessions(
        string $staffProfileId,
        string $locale,
        string $timezone,
        string $organizationId,
    ): array {
        $now = CarbonImmutable::now($this->validTimezone($timezone));

        $rows = $this->teacherSessionsQuery($staffProfileId, $organizationId)
            ->whereBetween('sessions.scheduled_start', [
                $now->startOfDay()->utc(),
                $now->endOfDay()->utc(),
            ])
            ->orderBy('sessions.scheduled_start')
            ->get();

        return $this->mapSessions($rows, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teacherScheduleSessions(
        string $staffProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = $this->teacherSessionsQuery($staffProfileId, $organizationId)
            ->where('sessions.scheduled_end', '>=', CarbonImmutable::now('UTC'))
            ->orderBy('sessions.scheduled_start')
            ->get();

        return $this->mapSessions($rows, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teacherPendingAttendanceSessions(
        string $staffProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = $this->teacherSessionsQuery($staffProfileId, $organizationId)
            ->whereIn('sessions.status', [
                SessionStatus::AwaitingReview->value,
                SessionStatus::Completed->value,
            ])
            ->where('sessions.scheduled_end', '<', CarbonImmutable::now('UTC'))
            ->whereExists(function (Builder $participants): void {
                $participants->selectRaw('1')
                    ->from('session_participants')
                    ->whereColumn('session_participants.session_id', 'sessions.id')
                    ->whereNotExists(function (Builder $attendance): void {
                        $attendance->selectRaw('1')
                            ->from('attendances')
                            ->whereColumn(
                                'attendances.session_participant_id',
                                'session_participants.id',
                            );
                    });
            })
            ->orderByDesc('sessions.scheduled_start')
            ->get();

        return $this->mapSessions($rows, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teacherLateReportSessions(
        string $staffProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = $this->teacherSessionsQuery($staffProfileId, $organizationId)
            ->whereIn('sessions.status', [
                SessionStatus::AwaitingReview->value,
                SessionStatus::Completed->value,
            ])
            ->where('sessions.scheduled_end', '<', CarbonImmutable::now('UTC'))
            ->whereNotExists(function (Builder $reports): void {
                $reports->selectRaw('1')
                    ->from('session_reports')
                    ->whereColumn('session_reports.session_id', 'sessions.id');
            })
            ->orderByDesc('sessions.scheduled_start')
            ->get();

        return $this->mapSessions($rows, $locale);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function teacherSession(
        string $staffProfileId,
        string $sessionId,
        string $locale,
        string $organizationId,
    ): ?array {
        $row = $this->teacherSessionsQuery($staffProfileId, $organizationId)
            ->where('sessions.id', $sessionId)
            ->first();

        return $row === null ? null : $this->mapSession($row, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teacherAttendance(string $sessionId, string $organizationId): array
    {
        return DB::table('session_participants')
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->join('student_profiles', 'student_profiles.id', '=', 'session_participants.student_profile_id')
            ->join('users as student_users', 'student_users.id', '=', 'student_profiles.user_id')
            ->leftJoin('attendances', 'attendances.session_participant_id', '=', 'session_participants.id')
            ->where('session_participants.session_id', $sessionId)
            ->where('sessions.organization_id', $organizationId)
            ->whereColumn('student_profiles.organization_id', 'sessions.organization_id')
            ->whereColumn('student_users.organization_id', 'sessions.organization_id')
            ->whereNull('sessions.deleted_at')
            ->whereNull('student_profiles.deleted_at')
            ->orderBy('student_users.name')
            ->get([
                'session_participants.id as participant_id',
                'session_participants.student_profile_id',
                'attendances.id as attendance_id',
                'attendances.status',
                'attendances.override_reason',
                'attendances.confirmed_at',
                'attendances.updated_at',
                'student_users.name as student_name',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) ($row->attendance_id ?? $row->participant_id),
                'sessionId' => $sessionId,
                'studentId' => (string) $row->student_profile_id,
                'studentName' => (string) $row->student_name,
                'studentAvatarUrl' => null,
                'status' => $row->status === null ? 'pending' : (string) $row->status,
                'note' => $row->override_reason === null ? null : (string) $row->override_reason,
                'recordedAt' => $this->iso($row->confirmed_at ?? $row->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{summary: ?string, notes: ?string}|null
     */
    public function teacherInitialReport(
        string $sessionId,
        string $staffProfileId,
    ): ?array {
        $row = DB::table('session_reports')
            ->where('session_id', $sessionId)
            ->where('staff_profile_id', $staffProfileId)
            ->first(['topics_covered', 'general_notes']);

        if ($row === null) {
            return null;
        }

        return [
            'summary' => $row->topics_covered === null ? null : (string) $row->topics_covered,
            'notes' => $row->general_notes === null ? null : (string) $row->general_notes,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function guardianChildren(
        string $userId,
        string $locale,
        string $organizationId,
    ): array {
        return DB::table('guardian_profiles')
            ->join('guardian_links', 'guardian_links.guardian_profile_id', '=', 'guardian_profiles.id')
            ->join('student_profiles', 'student_profiles.id', '=', 'guardian_links.student_profile_id')
            ->join('users as student_users', 'student_users.id', '=', 'student_profiles.user_id')
            ->where('guardian_profiles.user_id', $userId)
            ->where('guardian_profiles.organization_id', $organizationId)
            ->whereColumn('student_profiles.organization_id', 'guardian_profiles.organization_id')
            ->whereColumn('student_users.organization_id', 'guardian_profiles.organization_id')
            ->whereNull('guardian_profiles.deleted_at')
            ->whereNull('student_profiles.deleted_at')
            ->whereNull('student_users.deleted_at')
            ->whereNotNull('guardian_links.verified_at')
            ->orderByDesc('guardian_links.is_primary')
            ->orderBy('student_users.name')
            ->get([
                'student_profiles.id',
                'student_users.name',
                'student_users.status',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'avatarUrl' => null,
                'gradeLevel' => null,
                'status' => (string) $row->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function guardianChild(
        string $userId,
        string $studentProfileId,
        string $locale,
        string $organizationId,
    ): ?array {
        foreach ($this->guardianChildren($userId, $locale, $organizationId) as $child) {
            if ($child['id'] === $studentProfileId) {
                return $child;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function guardianUpcomingSessions(string $studentProfileId, string $locale, string $organizationId): array
    {
        return $this->upcomingStudentSessions($studentProfileId, $locale, $organizationId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function guardianAttendances(
        string $studentProfileId,
        string $studentName,
        string $locale,
        string $organizationId,
    ): array {
        $rows = DB::table('attendances')
            ->join(
                'session_participants',
                'session_participants.id',
                '=',
                'attendances.session_participant_id',
            )
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->leftJoin('groups', 'groups.id', '=', 'sessions.group_id')
            ->join('courses', 'courses.id', '=', 'sessions.course_id')
            ->join('staff_profiles as teacher_profiles', 'teacher_profiles.id', '=', 'sessions.staff_profile_id')
            ->join('users as teacher_users', 'teacher_users.id', '=', 'teacher_profiles.user_id')
            ->where('session_participants.student_profile_id', $studentProfileId)
            ->where('sessions.organization_id', $organizationId)
            ->whereColumn('courses.organization_id', 'sessions.organization_id')
            ->whereColumn('teacher_profiles.organization_id', 'sessions.organization_id')
            ->whereColumn('teacher_users.organization_id', 'sessions.organization_id')
            ->whereNull('sessions.deleted_at')
            ->orderByDesc('sessions.scheduled_start')
            ->get([
                'attendances.id as attendance_id',
                'attendances.status as attendance_status',
                'attendances.override_reason',
                'attendances.confirmed_at',
                'attendances.updated_at as attendance_updated_at',
                'sessions.id',
                'sessions.title as session_title',
                'sessions.status',
                'sessions.scheduled_start',
                'sessions.scheduled_end',
                'groups.timezone as group_timezone',
                'courses.name as course_name',
                'teacher_users.id as teacher_id',
                'teacher_users.name as teacher_name',
            ]);

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->attendance_id,
            'sessionId' => (string) $row->id,
            'studentId' => $studentProfileId,
            'studentName' => $studentName,
            'studentAvatarUrl' => null,
            'status' => (string) $row->attendance_status,
            'note' => $row->override_reason === null ? null : (string) $row->override_reason,
            'recordedAt' => $this->iso($row->confirmed_at ?? $row->attendance_updated_at),
            'session' => $this->mapSession($row, $locale),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teacherPostponements(
        string $staffProfileId,
        string $locale,
        string $organizationId,
    ): array {
        $rows = DB::table('postponement_requests')
            ->join('sessions', 'sessions.id', '=', 'postponement_requests.session_id')
            ->leftJoin('groups', 'groups.id', '=', 'sessions.group_id')
            ->join('courses', 'courses.id', '=', 'sessions.course_id')
            ->join('staff_profiles as teacher_profiles', 'teacher_profiles.id', '=', 'sessions.staff_profile_id')
            ->join('users as teacher_users', 'teacher_users.id', '=', 'teacher_profiles.user_id')
            ->join('users as requester_users', 'requester_users.id', '=', 'postponement_requests.requested_by')
            ->where('sessions.staff_profile_id', $staffProfileId)
            ->where('sessions.organization_id', $organizationId)
            ->whereColumn('courses.organization_id', 'sessions.organization_id')
            ->whereColumn('teacher_profiles.organization_id', 'sessions.organization_id')
            ->whereColumn('teacher_users.organization_id', 'sessions.organization_id')
            ->whereColumn('requester_users.organization_id', 'sessions.organization_id')
            ->whereNull('sessions.deleted_at')
            ->orderByDesc('postponement_requests.created_at')
            ->get([
                'postponement_requests.id as request_id',
                'postponement_requests.status as request_status',
                'postponement_requests.reason',
                'postponement_requests.proposed_start',
                'requester_users.id as requester_id',
                'requester_users.name as requester_name',
                'sessions.id',
                'sessions.title as session_title',
                'sessions.status',
                'sessions.scheduled_start',
                'sessions.scheduled_end',
                'groups.timezone as group_timezone',
                'courses.name as course_name',
                'teacher_users.id as teacher_id',
                'teacher_users.name as teacher_name',
            ]);

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->request_id,
            'session' => $this->mapSession($row, $locale),
            'requestedBy' => [
                'id' => (string) $row->requester_id,
                'name' => (string) $row->requester_name,
                'avatarUrl' => null,
            ],
            'reason' => (string) $row->reason,
            'requestedStartAt' => (string) $this->iso($row->proposed_start),
            'status' => (string) $row->request_status,
            'approveUrl' => '',
            'proposeAlternativeUrl' => '',
        ])->values()->all();
    }

    /**
     * @return array<string, string>
     */
    public function statusColors(): array
    {
        return [
            'draft' => 'neutral',
            'scheduled' => 'brand',
            'confirmed' => 'brand',
            'in_progress' => 'success',
            'awaiting_review' => 'warning',
            'completed' => 'neutral',
            'cancelled_by_student' => 'danger',
            'cancelled_by_teacher' => 'danger',
            'cancelled_by_school' => 'danger',
            'no_show' => 'danger',
            'excused' => 'brand',
            'postponed' => 'warning',
            'present' => 'success',
            'late' => 'warning',
            'partial' => 'warning',
            'left_early' => 'warning',
            'absent' => 'danger',
            'technical_issue' => 'warning',
            'not_held' => 'neutral',
            'pending' => 'neutral',
            'requested' => 'warning',
            'alternative_proposed' => 'brand',
            'fulfilled' => 'success',
            'rejected' => 'danger',
            'withdrawn' => 'neutral',
            'expired' => 'neutral',
        ];
    }

    /**
     * @return list<string>
     */
    public function attendanceStatuses(): array
    {
        return array_map(
            static fn (AttendanceStatus $status): string => $status->value,
            AttendanceStatus::cases(),
        );
    }

    private function baseSessionsQuery(string $organizationId): Builder
    {
        return DB::table('sessions')
            ->leftJoin('groups', 'groups.id', '=', 'sessions.group_id')
            ->join('courses', 'courses.id', '=', 'sessions.course_id')
            ->join('staff_profiles as teacher_profiles', 'teacher_profiles.id', '=', 'sessions.staff_profile_id')
            ->join('users as teacher_users', 'teacher_users.id', '=', 'teacher_profiles.user_id')
            ->where('sessions.organization_id', $organizationId)
            ->whereColumn('courses.organization_id', 'sessions.organization_id')
            ->whereColumn('teacher_profiles.organization_id', 'sessions.organization_id')
            ->whereColumn('teacher_users.organization_id', 'sessions.organization_id')
            ->where(function (Builder $groups): void {
                $groups->whereNull('sessions.group_id')
                    ->orWhereColumn('groups.organization_id', 'sessions.organization_id');
            })
            ->whereNull('sessions.deleted_at')
            ->select([
                'sessions.id',
                'sessions.title as session_title',
                'sessions.status',
                'sessions.scheduled_start',
                'sessions.scheduled_end',
                'groups.timezone as group_timezone',
                'courses.name as course_name',
                'teacher_users.id as teacher_id',
                'teacher_users.name as teacher_name',
            ]);
    }

    private function studentSessionsQuery(string $studentProfileId, string $organizationId): Builder
    {
        return $this->baseSessionsQuery($organizationId)
            ->join('session_participants', 'session_participants.session_id', '=', 'sessions.id')
            ->where('session_participants.student_profile_id', $studentProfileId)
            ->whereNull('session_participants.revoked_at')
            ->whereNull('session_participants.deleted_at');
    }

    private function teacherSessionsQuery(string $staffProfileId, string $organizationId): Builder
    {
        return $this->baseSessionsQuery($organizationId)
            ->where('sessions.staff_profile_id', $staffProfileId);
    }

    /**
     * @param iterable<object> $rows
     * @return list<array<string, mixed>>
     */
    private function mapSessions(iterable $rows, string $locale): array
    {
        $sessions = [];

        foreach ($rows as $row) {
            $sessions[] = $this->mapSession($row, $locale);
        }

        return $sessions;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSession(object $row, string $locale): array
    {
        $startsAt = CarbonImmutable::parse((string) $row->scheduled_start, 'UTC')->utc();
        $endsAt = CarbonImmutable::parse((string) $row->scheduled_end, 'UTC')->utc();
        $joinBefore = max(0, (int) config('virtual-classroom.join_window.before_minutes', 0));
        $canJoinAt = $startsAt->subMinutes($joinBefore);
        $canJoinUntil = $endsAt->addMinutes(
            max(0, (int) config('virtual-classroom.join_window.after_minutes', 0)),
        );
        $status = SessionStatus::tryFrom((string) $row->status);
        $canJoin = $status?->allowsJoining() === true
            && CarbonImmutable::now('UTC')->betweenIncluded(
                $canJoinAt,
                $canJoinUntil,
            );

        return [
            'id' => (string) $row->id,
            'title' => $this->localized($row->session_title, $locale),
            'subject' => $this->localized($row->course_name, $locale),
            'teacher' => [
                'id' => (string) $row->teacher_id,
                'name' => (string) $row->teacher_name,
                'avatarUrl' => null,
            ],
            'startsAt' => $startsAt->toIso8601String(),
            'endsAt' => $endsAt->toIso8601String(),
            'timezone' => $this->validTimezone((string) ($row->group_timezone ?? 'UTC')),
            'status' => (string) $row->status,
            'location' => null,
            'joinUrl' => null,
            'canJoinAt' => $canJoinAt->toIso8601String(),
            'canJoinUntil' => $canJoinUntil->toIso8601String(),
            'canJoin' => $canJoin,
            'recordingUrl' => null,
        ];
    }

    private function localized(mixed $value, string $locale): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (!is_array($decoded)) {
                return $value;
            }

            $value = $decoded;
        } elseif (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return '';
        }

        $fallback = (string) config('app.fallback_locale', 'en');

        foreach ([$locale, $fallback, 'ar', 'en'] as $candidate) {
            if (isset($value[$candidate]) && is_string($value[$candidate])) {
                return $value[$candidate];
            }
        }

        foreach ($value as $translation) {
            if (is_string($translation)) {
                return $translation;
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $replace
     */
    private function translation(string $key, string $locale, array $replace = []): string
    {
        $lines = Lang::get('portal', [], $locale);
        $line = is_array($lines) && is_string($lines[$key] ?? null)
            ? $lines[$key]
            : '';

        foreach ($replace as $placeholder => $value) {
            $line = str_replace(':'.$placeholder, $value, $line);
        }

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $metrics
     * @param list<string> $paths
     */
    private function numericMetric(array $metrics, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = data_get($metrics, $path);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'UTC')->utc()->toIso8601String();
    }

    private function validTimezone(string $timezone): string
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : 'UTC';
    }
}
