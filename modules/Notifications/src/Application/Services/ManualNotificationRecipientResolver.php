<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\ValueObjects\ManualRecipientResolution;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\ValueObjects\StudentDirectoryData;
use Shared\Support\BusinessRuleViolation;

/**
 * يحوّل اختيار الواجهة إلى user IDs موثوقة ومحصورة بالمؤسسة.
 * لا يعرف Notifications أي نموذج Eloquent من الموديولات المالكة.
 */
final readonly class ManualNotificationRecipientResolver
{
    public function __construct(
        private UserAccountDirectory $accounts,
        private StudentDirectoryQueries $students,
        private StaffQueries $staff,
        private GroupAdministrationQueries $groups,
    ) {}

    /** @return array<string, string> */
    public function search(
        string $organizationId,
        ManualRecipientType $type,
        string $term,
        int $limit = 25,
    ): array {
        $limit = max(1, min($limit, 50));

        return match ($type) {
            ManualRecipientType::Student => $this->studentOptions($organizationId, $term, $limit),
            ManualRecipientType::Teacher => $this->teacherOptions($organizationId, $term, $limit),
            ManualRecipientType::Group => $this->groupOptions($organizationId, $term, $limit),
        };
    }

    public function label(
        string $organizationId,
        ManualRecipientType $type,
        string $targetId,
    ): ?string {
        try {
            return $this->resolve($organizationId, $type, $targetId)->label;
        } catch (BusinessRuleViolation) {
            return null;
        }
    }

    public function resolve(
        string $organizationId,
        ManualRecipientType $type,
        string $targetId,
    ): ManualRecipientResolution {
        if ($organizationId === '' || $targetId === '') {
            $this->recipientNotFound();
        }

        return match ($type) {
            ManualRecipientType::Student => $this->resolveStudent($organizationId, $targetId),
            ManualRecipientType::Teacher => $this->resolveTeacher($organizationId, $targetId),
            ManualRecipientType::Group => $this->resolveGroup($organizationId, $targetId),
        };
    }

    /** @return array<string, string> */
    private function studentOptions(string $organizationId, string $term, int $limit): array
    {
        $accounts = array_values(array_filter(
            $this->accounts->search($organizationId, $term, $limit),
            static fn (UserAccountData $account): bool => $account->status === 'active',
        ));
        $profiles = $this->students->forUserIds(
            $organizationId,
            array_map(static fn (UserAccountData $account): string => $account->id, $accounts),
        );
        $profilesByUser = [];

        foreach ($profiles as $profile) {
            if (!$profile->archived) {
                $profilesByUser[$profile->userId] = $profile;
            }
        }

        $options = [];

        foreach ($accounts as $account) {
            $profile = $profilesByUser[$account->id] ?? null;

            if ($profile !== null) {
                $options[$account->id] = $account->name.' · '.$profile->studentCode;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function teacherOptions(string $organizationId, string $term, int $limit): array
    {
        $needle = mb_strtolower(trim($term));
        $options = [];

        foreach ($this->staff->activeTeacherSummariesForOrganization($organizationId) as $teacher) {
            $haystack = mb_strtolower($teacher['name'].' '.$teacher['staff_code']);

            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }

            $userId = $this->staff->userIdForProfile($organizationId, $teacher['staff_profile_id']);

            if ($userId !== null) {
                $options[$userId] = $teacher['name'].' · '.$teacher['staff_code'];
            }

            if (count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function groupOptions(string $organizationId, string $term, int $limit): array
    {
        $needle = mb_strtolower(trim($term));
        $options = [];

        foreach ($this->groups->activeGroupsForScheduling($organizationId) as $group) {
            $label = $this->groupLabel($group);

            if ($needle !== '' && !str_contains(mb_strtolower($label), $needle)) {
                continue;
            }

            $options[$group->id] = $label;

            if (count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    private function resolveStudent(string $organizationId, string $userId): ManualRecipientResolution
    {
        $account = $this->accounts->find($organizationId, $userId);
        $profile = $this->students->forUserIds($organizationId, [$userId])[0] ?? null;

        if ($account === null || $account->status !== 'active' || $profile === null || $profile->archived) {
            $this->recipientNotFound();
        }

        return new ManualRecipientResolution(
            type: ManualRecipientType::Student,
            targetId: $userId,
            label: $account->name.' · '.$profile->studentCode,
            userIds: [$userId],
        );
    }

    private function resolveTeacher(string $organizationId, string $userId): ManualRecipientResolution
    {
        $account = $this->accounts->find($organizationId, $userId);
        $profile = $this->staff->findActiveProfileForUser($userId);

        if ($account === null
            || $account->status !== 'active'
            || $profile === null
            || !$this->staff->isActiveTeacherForOrganization($organizationId, $profile['id'])) {
            $this->recipientNotFound();
        }

        return new ManualRecipientResolution(
            type: ManualRecipientType::Teacher,
            targetId: $userId,
            label: $account->name.' · '.$profile['staff_code'],
            userIds: [$userId],
        );
    }

    private function resolveGroup(string $organizationId, string $groupId): ManualRecipientResolution
    {
        $group = collect($this->groups->activeGroupsForScheduling($organizationId))
            ->first(static fn (SchedulingGroupData $candidate): bool => $candidate->id === $groupId);

        if (!$group instanceof SchedulingGroupData) {
            $this->recipientNotFound();
        }

        $profileIds = collect($this->groups->membershipsForGroup($organizationId, $groupId))
            ->filter(static fn (mixed $membership): bool => $membership->status === 'active'
                && $membership->leftAt === null)
            ->pluck('studentProfileId')
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
        $profiles = $this->students->byIds($organizationId, $profileIds);
        $userIds = array_values(array_unique(array_map(
            static fn (StudentDirectoryData $profile): string => $profile->userId,
            array_filter(
                array_values($profiles),
                static fn (StudentDirectoryData $profile): bool => !$profile->archived,
            ),
        )));
        $accounts = $this->accounts->findMany($organizationId, $userIds);
        $userIds = array_values(array_filter(
            $userIds,
            static fn (string $userId): bool => isset($accounts[$userId])
                && $accounts[$userId]->status === 'active',
        ));

        if ($userIds === []) {
            throw BusinessRuleViolation::make(
                'notifications.manual_empty_audience',
                'notifications::errors.manual_empty_audience',
            );
        }

        return new ManualRecipientResolution(
            type: ManualRecipientType::Group,
            targetId: $groupId,
            label: $this->groupLabel($group),
            userIds: $userIds,
        );
    }

    private function groupLabel(SchedulingGroupData $group): string
    {
        $locale = app()->getLocale();
        $translatedNames = array_values($group->name);
        $name = $group->name[$locale]
            ?? $group->name[(string) config('app.fallback_locale')]
            ?? ($translatedNames[0] ?? null)
            ?: $group->code;

        return (string) $name.' · '.$group->code;
    }

    private function recipientNotFound(): never
    {
        throw BusinessRuleViolation::make(
            'notifications.manual_recipient_not_found',
            'notifications::errors.manual_recipient_not_found',
        );
    }
}
