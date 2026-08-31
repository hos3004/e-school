<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\PlacementGroupData;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UsernameSuggestionGateway;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\ValueObjects\StudentDirectoryData;

/**
 * منسق قراءة لواجهات الإدارة المركبة.
 *
 * يجمع DTOs من الموديولات المالكة ولا يقرأ جدولًا أو نموذجًا عابرًا للحدود.
 */
final readonly class ProfileAdministrationQueryService
{
    public function __construct(
        private UserAccountDirectory $accounts,
        private UsernameSuggestionGateway $usernames,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private EnrollmentAdministrationQueries $enrollments,
        private SessionAdministrationQueries $sessions,
        private StaffAdministrationQueries $staffAdministration,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
        private StudentDirectoryQueries $students,
        private GuardianQuery $guardians,
    ) {}

    /** @return list<string> */
    public function usernameSuggestions(string $organizationId, string $fullName): array
    {
        return $this->usernames->suggest($fullName, $organizationId);
    }

    /**
     * @param list<string> $excludedUserIds
     * @return array<string, string>
     */
    public function accountOptions(string $organizationId, string $search, array $excludedUserIds = []): array
    {
        $excluded = array_fill_keys($excludedUserIds, true);

        return collect($this->accounts->search(
            $organizationId,
            $search,
            (int) config('identity.directory.max_results'),
        ))
            ->reject(static fn (UserAccountData $account): bool => isset($excluded[$account->id]))
            ->mapWithKeys(fn (UserAccountData $account): array => [
                $account->id => $this->accountLabel($account),
            ])
            ->all();
    }

    /**
     * خيارات حالة الحساب مترجمة.
     *
     * القائمة تُبنى هنا في جذر التركيب لأن `UserStatus` يخص موديول Identity
     * **المختوم**؛ شاشات Staff/Students لا تستورده وتستهلك قيَمًا نصية فقط.
     *
     * @return array<string, string>
     */
    public function accountStatusOptions(): array
    {
        return collect(UserStatus::cases())
            ->mapWithKeys(static fn (UserStatus $status): array => [
                $status->value => __('identity::status.'.$status->value),
            ])
            ->all();
    }

    /**
     * خيارات صلة ولي الأمر مترجمة.
     *
     * نفس السبب: `GuardianRelationship` يملكه موديول Guardians، وموديول
     * Students لا يعتمد عليه مباشرة.
     *
     * @return array<string, string>
     */
    public function guardianRelationshipOptions(): array
    {
        return collect(GuardianRelationship::cases())
            ->mapWithKeys(static fn (GuardianRelationship $relationship): array => [
                $relationship->value => __('guardians::relationship.'.$relationship->value),
            ])
            ->all();
    }

    public function accountOptionLabel(string $organizationId, string $userId): ?string
    {
        $account = $this->accounts->find($organizationId, $userId);

        return $account === null ? null : $this->accountLabel($account);
    }

    /** @return array<string, string> */
    public function studentOptions(string $organizationId, string $search): array
    {
        $accounts = $this->accounts->search(
            $organizationId,
            $search,
            (int) config('identity.directory.max_results'),
        );
        $accountsById = collect($accounts)->keyBy(static fn (UserAccountData $account): string => $account->id);

        return collect($this->students->forUserIds($organizationId, $accountsById->keys()->all()))
            ->mapWithKeys(function (StudentDirectoryData $student) use ($accountsById): array {
                /** @var UserAccountData|null $account */
                $account = $accountsById->get($student->userId);

                return [
                    $student->id => ($account === null ? $student->userId : $account->name).' · '.$student->studentCode,
                ];
            })
            ->all();
    }

    public function studentOptionLabel(string $organizationId, string $studentProfileId): ?string
    {
        $student = $this->students->find($organizationId, $studentProfileId);

        if ($student === null) {
            return null;
        }

        $account = $this->accounts->find($organizationId, $student->userId);

        return ($account === null ? $student->userId : $account->name).' · '.$student->studentCode;
    }

    /** @return array<string, string> */
    public function programOptions(string $organizationId): array
    {
        return collect($this->academics->programs($organizationId))
            ->mapWithKeys(fn (AcademicCatalogItemData $item): array => [$item->id => $this->catalogLabel($item)])
            ->all();
    }

    /** @return array<string, string> */
    public function courseOptions(string $organizationId, ?string $programId): array
    {
        if ($programId === null || $programId === '') {
            return [];
        }

        return collect($this->academics->courses($organizationId, $programId))
            ->mapWithKeys(fn (AcademicCatalogItemData $item): array => [$item->id => $this->catalogLabel($item)])
            ->all();
    }

    /** @return array<string, string> */
    public function allCourseOptions(string $organizationId): array
    {
        $options = [];

        foreach ($this->academics->programs($organizationId) as $program) {
            foreach ($this->academics->courses($organizationId, $program->id) as $course) {
                $options[$course->id] = $this->catalogLabel($program).' — '.$this->catalogLabel($course);
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    public function teacherOptions(string $organizationId): array
    {
        $ids = $this->staff->activeTeacherIdsForOrganization($organizationId);

        return $this->staff->namesForProfiles($organizationId, $ids);
    }

    /** @return array<string, string> */
    public function placementGroupOptions(
        string $organizationId,
        ?string $programId,
        ?string $courseId,
    ): array {
        if ($programId === null || $programId === '' || $courseId === null || $courseId === '') {
            return [];
        }

        $groups = $this->groups->availableForPlacement($organizationId, $programId, $courseId);
        $teacherIds = collect($groups)->flatMap(
            static fn (PlacementGroupData $group): array => $group->teacherProfileIds,
        )->unique()->values()->all();
        $teacherNames = $this->staff->namesForProfiles($organizationId, $teacherIds);

        return collect($groups)->mapWithKeys(function (PlacementGroupData $group) use ($teacherNames): array {
            $names = array_values(array_filter(array_map(
                static fn (string $id): ?string => $teacherNames[$id] ?? null,
                $group->teacherProfileIds,
            )));
            $capacity = __('students::admin.placement.capacity', [
                'used' => $group->occupiedSeats,
                'total' => $group->capacity ?? __('students::admin.common.not_available'),
            ]);
            $teachers = $names === []
                ? __('students::admin.common.not_available')
                : implode('، ', $names);

            return [
                $group->id => $this->localized($group->name).' · '.$capacity.' · '.$teachers,
            ];
        })->all();
    }

    /**
     * خيارات المجموعة في التسكين الجماعي — النشطة والمسودّات معًا.
     *
     * التسمية تحمل ما يلزم القرار: الاسم والرمز والحالة والمقاعد المتبقية
     * والمعلمون والمواعيد. الطلاب لا يرون معرّفات داخلية في أي منها.
     *
     * @return array<string, string>
     */
    public function bulkPlacementGroupOptions(
        string $organizationId,
        ?string $programId,
        ?string $courseId,
    ): array {
        if ($programId === null || $programId === '') {
            return [];
        }

        $groups = $this->groups->openForPlacement(
            $organizationId,
            $programId,
            $courseId === '' ? null : $courseId,
        );

        $teacherIds = collect($groups)->flatMap(
            static fn (PlacementGroupData $group): array => $group->teacherProfileIds,
        )->unique()->values()->all();
        $teacherNames = $this->staff->namesForProfiles($organizationId, $teacherIds);

        return collect($groups)->mapWithKeys(function (PlacementGroupData $group) use ($teacherNames): array {
            $names = array_values(array_filter(array_map(
                static fn (string $id): ?string => $teacherNames[$id] ?? null,
                $group->teacherProfileIds,
            )));

            $parts = [
                $this->localized($group->name).' · '.$group->code,
                __('groups::status.group.'.$group->status),
                __('students::admin.bulk_placement.remaining_seats', [
                    'count' => $group->remainingSeats,
                ]),
                $names === [] ? __('students::admin.common.not_available') : implode('، ', $names),
            ];

            if ($group->startsOn !== null) {
                $parts[] = $group->startsOn;
            }

            return [$group->id => implode(' · ', $parts)];
        })->all();
    }

    /** @return array<string, mixed> */
    public function studentHub(string $organizationId, string $studentProfileId, string $userId): array
    {
        $account = $this->accounts->find($organizationId, $userId);
        $enrollments = $this->enrollments->forStudent($organizationId, $studentProfileId);
        $memberships = $this->groups->membershipsForStudent($organizationId, $studentProfileId);
        $sessions = $this->sessions->forStudent(
            $organizationId,
            $studentProfileId,
            (int) config('sessions.admin_hub.max_items'),
        );
        $guardianLinks = $this->guardians->guardiansForStudent($studentProfileId);
        $guardianAccounts = $this->accounts->findMany(
            $organizationId,
            array_values(array_unique(array_map(
                static fn ($link): string => $link->userId,
                $guardianLinks,
            ))),
        );
        $programs = $this->academics->programsByIds(
            $organizationId,
            array_values(array_unique(array_map(static fn ($item): string => $item->programId, $enrollments))),
        );
        $courses = $this->academics->coursesByIds(
            $organizationId,
            array_values(array_unique(array_map(static fn ($item): string => $item->courseId, $sessions))),
        );
        $groups = collect($memberships)->keyBy(static fn ($item): string => $item->groupId);

        return [
            'account' => $account === null ? [] : [[
                'name' => $account->name,
                'username' => $account->username,
                'email' => $account->email,
                'phone' => $account->phone,
                'status' => __('identity::status.'.$account->status),
            ]],
            'enrollments' => array_map(fn ($item): array => [
                'id' => $item->id,
                'program' => isset($programs[$item->programId])
                    ? $this->catalogLabel($programs[$item->programId])
                    : $item->programId,
                'status' => __('enrollments::status.'.$item->status),
                'activated_at' => $item->activatedAt,
                'expected_return_date' => $item->expectedReturnDate,
            ], $enrollments),
            'groups' => array_map(fn ($item): array => [
                'id' => $item->membershipId,
                'group' => $this->localized($item->groupName),
                'code' => $item->groupCode,
                'status' => __('groups::status.membership.'.$item->membershipStatus),
                'joined_at' => $item->joinedAt,
                'left_at' => $item->leftAt,
            ], $memberships),
            'guardians' => array_map(function ($link) use ($guardianAccounts): array {
                /** @var UserAccountData|null $account */
                $account = $guardianAccounts[$link->userId] ?? null;

                return [
                    'id' => $link->guardianLinkId,
                    'name' => $account->name ?? $link->userId,
                    'phone' => $account?->phone,
                    'relationship' => __('guardians::relationship.'.$link->relationship->value),
                    'is_primary' => $link->isPrimary
                        ? __('guardians::admin.common.yes')
                        : __('guardians::admin.common.no'),
                    'verified_at' => $link->verifiedAt,
                ];
            }, $guardianLinks),
            'sessions' => array_map(fn ($item): array => [
                'id' => $item->id,
                'title' => $this->localized($item->title),
                'course' => isset($courses[$item->courseId])
                    ? $this->catalogLabel($courses[$item->courseId])
                    : $item->courseId,
                'group' => isset($groups[$item->groupId])
                    ? $this->localized($groups[$item->groupId]->groupName)
                    : $item->groupId,
                'status' => __('sessions::status.'.$item->status),
                'scheduled_start' => $item->scheduledStart,
                'attended_minutes' => $item->attendedMinutes,
            ], $sessions),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function groupHub(string $organizationId, string $groupId): array
    {
        $programIds = $this->groups->programIdsForGroup($organizationId, $groupId);
        $memberships = $this->groups->membershipsForGroup($organizationId, $groupId);
        $assignments = $this->groups->assignmentsForGroup($organizationId, $groupId);
        $sessions = $this->sessions->forGroup(
            $organizationId,
            $groupId,
            (int) config('sessions.admin_hub.max_items'),
        );
        $programs = $this->academics->programsByIds($organizationId, $programIds);
        $courseIds = array_values(array_unique(array_filter([
            ...array_map(static fn ($item): ?string => $item->courseId, $assignments),
            ...array_map(static fn ($item): string => $item->courseId, $sessions),
        ])));
        $courses = $this->academics->coursesByIds($organizationId, $courseIds);
        $studentIds = array_map(static fn ($item): string => $item->studentProfileId, $memberships);
        $students = $this->students->byIds($organizationId, $studentIds);
        $accounts = $this->accounts->findMany(
            $organizationId,
            array_values(array_map(static fn (StudentDirectoryData $student): string => $student->userId, $students)),
        );
        $teacherIds = array_values(array_unique(array_map(
            static fn ($item): string => $item->staffProfileId,
            $sessions,
        )));
        $teacherIds = array_values(array_unique([
            ...$teacherIds,
            ...array_map(static fn ($item): string => $item->staffProfileId, $assignments),
        ]));
        $teacherNames = $this->staff->namesForProfiles($organizationId, array_values(array_filter($teacherIds)));

        return [
            'programs' => array_values(array_map(fn (AcademicCatalogItemData $program): array => [
                'id' => $program->id,
                'program' => $this->catalogLabel($program),
                'code' => $program->code,
            ], $programs)),
            'students' => array_map(function ($membership) use ($students, $accounts): array {
                $student = $students[$membership->studentProfileId] ?? null;
                $account = $student === null ? null : ($accounts[$student->userId] ?? null);

                return [
                    'id' => $membership->membershipId,
                    'student' => $account->name ?? $membership->studentProfileId,
                    'student_code' => $student?->studentCode,
                    'status' => __('groups::status.membership.'.$membership->status),
                    'joined_at' => $membership->joinedAt,
                    'left_at' => $membership->leftAt,
                ];
            }, $memberships),
            'teachers' => array_map(fn ($assignment): array => [
                'id' => $assignment->assignmentId,
                'teacher' => $teacherNames[$assignment->staffProfileId]
                    ?? __('groups::filament.not_available'),
                'course' => $assignment->courseId !== null && isset($courses[$assignment->courseId])
                    ? $this->catalogLabel($courses[$assignment->courseId])
                    : null,
                'role' => __('groups::status.teacher_role.'.$assignment->role),
                'assigned_from' => $assignment->assignedFrom,
                'assigned_to' => $assignment->assignedTo,
            ], $assignments),
            'sessions' => array_map(fn ($session): array => [
                'id' => $session->id,
                'title' => $this->localized($session->title),
                'course' => isset($courses[$session->courseId])
                    ? $this->catalogLabel($courses[$session->courseId])
                    : $session->courseId,
                'teacher' => $teacherNames[$session->staffProfileId] ?? $session->staffProfileId,
                'status' => __('sessions::status.'.$session->status),
                'scheduled_start' => $session->scheduledStart,
                'scheduled_end' => $session->scheduledEnd,
            ], $sessions),
        ];
    }

    /** @return array<string, mixed> */
    public function teacherHub(string $organizationId, string $staffProfileId, string $userId): array
    {
        $account = $this->accounts->find($organizationId, $userId);
        $qualificationCourseIds = $this->qualifications->courseIdsForTeacher($staffProfileId);
        $assignments = $this->groups->assignmentsForTeacher($organizationId, $staffProfileId);
        $availability = $this->staffAdministration->availabilityForTeacher($organizationId, $staffProfileId);
        $contracts = $this->staffAdministration->contractsForTeacher($organizationId, $staffProfileId);
        $rates = $this->staffAdministration->ratesForTeacher($organizationId, $staffProfileId);
        $sessions = $this->sessions->forTeacher(
            $organizationId,
            $staffProfileId,
            (int) config('sessions.admin_hub.max_items'),
        );
        $courses = $this->academics->coursesByIds(
            $organizationId,
            array_values(array_unique([
                ...$qualificationCourseIds,
                ...array_values(array_filter(array_map(
                    static fn ($item): ?string => $item->courseId,
                    $assignments,
                ))),
                ...array_map(static fn ($item): string => $item->courseId, $sessions),
                ...array_values(array_filter(array_map(static fn ($item): ?string => $item->courseId, $rates))),
            ])),
        );
        $programs = $this->academics->programsByIds(
            $organizationId,
            array_values(array_unique(array_filter([
                ...array_map(static fn (AcademicCatalogItemData $course): ?string => $course->programId, $courses),
                ...array_map(static fn ($item): ?string => $item->programId, $rates),
            ]))),
        );
        $assignmentGroups = collect($assignments)->keyBy(static fn ($item): string => $item->groupId);

        return [
            'account' => $account === null ? [] : [[
                'name' => $account->name,
                'username' => $account->username,
                'email' => $account->email,
                'phone' => $account->phone,
                'status' => __('identity::status.'.$account->status),
            ]],
            'qualifications' => array_values(array_map(
                fn (string $courseId): array => [
                    'id' => $courseId,
                    'course' => isset($courses[$courseId])
                        ? $this->catalogLabel($courses[$courseId])
                        : $courseId,
                    'program' => isset($courses[$courseId])
                        && $courses[$courseId]->programId !== null
                        && isset($programs[$courses[$courseId]->programId])
                            ? $this->catalogLabel($programs[$courses[$courseId]->programId])
                            : __('staff::admin.common.not_available'),
                    'session_mode' => isset($courses[$courseId]) && $courses[$courseId]->sessionMode !== null
                        ? __('academics::filament.session_modes.'.$courses[$courseId]->sessionMode)
                        : __('students::admin.common.not_available'),
                ],
                $qualificationCourseIds,
            )),
            'contracts' => array_map(static fn ($item): array => [
                'id' => $item->id,
                'basis' => __('staff::enums.contract_basis.'.$item->basis),
                'basis_value' => $item->basis,
                'effective_from' => $item->effectiveFrom,
                'effective_to' => $item->effectiveTo,
                'base_amount' => $item->baseAmountMajor === null
                    ? __('staff::admin.common.not_available')
                    : $item->baseAmountMajor.' '.$item->currency,
                'monthly_target_sessions' => $item->monthlyTargetSessions,
                'target_admin_tasks' => $item->targetAdminTasks,
                'target_training_sessions' => $item->targetTrainingSessions,
            ], $contracts),
            'rates' => array_map(fn ($item): array => [
                'id' => $item->id,
                'scope' => __('staff::enums.rate_scope.'.$item->scope),
                'amount' => $item->amountMajor.' '.$item->currency,
                'program' => $item->programId !== null && isset($programs[$item->programId])
                    ? $this->catalogLabel($programs[$item->programId])
                    : null,
                'course' => $item->courseId !== null && isset($courses[$item->courseId])
                    ? $this->catalogLabel($courses[$item->courseId])
                    : null,
                'session_type' => $item->sessionType,
                'effective_from' => $item->effectiveFrom,
                'effective_to' => $item->effectiveTo,
            ], $rates),
            'groups' => array_map(fn ($item): array => [
                'id' => $item->assignmentId,
                'group' => $this->localized($item->groupName),
                'code' => $item->groupCode,
                'course' => $item->courseId !== null && isset($courses[$item->courseId])
                    ? $this->catalogLabel($courses[$item->courseId])
                    : $item->courseId,
                'role' => __('groups::status.teacher_role.'.$item->role),
                'assigned_from' => $item->assignedFrom,
                'assigned_to' => $item->assignedTo,
            ], $assignments),
            'availability' => array_map(static fn ($item): array => [
                'id' => $item->id,
                'weekday' => __('staff::admin.availability.weekdays.'.$item->weekday),
                'time' => $item->startTime.' – '.$item->endTime,
                'timezone' => $item->timezone,
                'status' => __('staff::admin.availability.approval_status.'.$item->approvalStatus),
                'decision_reason' => $item->decisionReason,
                'effective_from' => $item->effectiveFrom,
                'effective_to' => $item->effectiveTo,
            ], $availability),
            'sessions' => array_map(fn ($item): array => [
                'id' => $item->id,
                'title' => $this->localized($item->title),
                'course' => isset($courses[$item->courseId])
                    ? $this->catalogLabel($courses[$item->courseId])
                    : $item->courseId,
                'group' => isset($assignmentGroups[$item->groupId])
                    ? $this->localized($assignmentGroups[$item->groupId]->groupName)
                    : $item->groupId,
                'status' => __('sessions::status.'.$item->status),
                'scheduled_start' => $item->scheduledStart,
            ], $sessions),
        ];
    }

    private function accountLabel(UserAccountData $account): string
    {
        $contact = $account->email ?? $account->phone ?? $account->username;

        return $account->name.' · '.$contact.' · '.__('identity::status.'.$account->status);
    }

    private function catalogLabel(AcademicCatalogItemData $item): string
    {
        return $this->localized($item->name).' · '.$item->code;
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? (string) (reset($value) ?: '');
    }
}
