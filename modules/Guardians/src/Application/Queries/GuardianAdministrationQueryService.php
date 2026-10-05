<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Queries;

use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** يكوّن بيانات Hub من عقود عامة وبيانات موديول Guardians فقط. */
final readonly class GuardianAdministrationQueryService
{
    public function __construct(
        private UserAccountDirectory $accounts,
        private StudentDirectoryQueries $students,
    ) {}

    /** @return array<string, mixed> */
    public function profileHub(string $organizationId, string $guardianProfileId): array
    {
        /** @var GuardianProfile $profile */
        $profile = GuardianProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($guardianProfileId)
            ->firstOrFail();

        $account = $this->accounts->find($organizationId, (string) $profile->user_id);
        $links = GuardianLink::query()
            ->forGuardian((string) $profile->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
        $students = $this->students->byIds(
            $organizationId,
            $links->pluck('student_profile_id')->map(static fn (mixed $id): string => (string) $id)->all(),
        );
        $studentAccounts = $this->accounts->findMany(
            $organizationId,
            array_values(array_unique(array_map(static fn ($student): string => $student->userId, $students))),
        );

        return [
            'account' => $account === null ? [] : [[
                'name' => $account->name,
                'username' => $account->username,
                'email' => $account->email,
                'phone' => $account->phone,
                'status' => __('identity::status.'.$account->status),
            ]],
            'students' => $links->map(function (GuardianLink $link) use ($students, $studentAccounts): array {
                $student = $students[(string) $link->student_profile_id] ?? null;
                $studentAccount = $student === null ? null : ($studentAccounts[$student->userId] ?? null);

                return [
                    'id' => (string) $link->getKey(),
                    'student' => $studentAccount === null ? (string) $link->student_profile_id : $studentAccount->name,
                    'student_code' => $student?->studentCode,
                    'relationship' => $link->relationship->label(),
                    'is_primary' => $link->is_primary
                        ? __('guardians::admin.common.yes')
                        : __('guardians::admin.common.no'),
                    'can_act_for' => $link->can_act_for
                        ? __('guardians::admin.common.yes')
                        : __('guardians::admin.common.no'),
                    'verified_at' => $link->verified_at,
                    'visible_sections' => implode('، ', array_map(
                        static fn (mixed $section): string => __('guardians::admin.sections.'.(string) $section),
                        $link->visible_sections ?? [],
                    )),
                ];
            })->values()->all(),
        ];
    }

    public function accountName(GuardianProfile $profile): string
    {
        $account = $this->accounts->find((string) $profile->organization_id, (string) $profile->user_id);

        return $account === null ? (string) $profile->user_id : $account->name;
    }

    public function accountContact(GuardianProfile $profile): ?string
    {
        $account = $this->accounts->find((string) $profile->organization_id, (string) $profile->user_id);

        return $account === null ? null : ($account->phone ?? $account->email);
    }
}
