<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Modules\Recordings\Domain\Models\RecordingView;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;

final readonly class RecordingOperationsQueryService
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private StaffQueries $staff,
        private UserAccountDirectory $accounts,
        private AuditQueryService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function context(string $organizationId, Recording $recording): array
    {
        $this->assertOwned($organizationId, $recording);
        $session = $this->sessions->findForOrganization($organizationId, (string) $recording->session_id);
        if ($session === null) {
            return [
                'session' => __('recordings::messages.unavailable'),
                'course' => __('recordings::messages.unavailable'),
                'group' => __('recordings::messages.unavailable'),
                'teacher' => __('recordings::messages.unavailable'),
                'scheduled_start' => null,
            ];
        }

        $course = $this->academics->coursesByIds($organizationId, [$session->courseId])[$session->courseId] ?? null;
        $group = $session->groupId === ''
            ? null
            : ($this->groups->groupsByIds($organizationId, [$session->groupId])[$session->groupId] ?? null);
        $teacher = $this->staff->namesForProfiles($organizationId, [$session->staffProfileId])[$session->staffProfileId] ?? null;

        return [
            'session' => self::localized($session->title),
            'course' => $course === null ? __('recordings::messages.unavailable') : self::localized($course->name),
            'group' => $group === null ? __('recordings::messages.unavailable') : self::localized($group->name),
            'teacher' => $teacher ?? __('recordings::messages.unavailable'),
            'scheduled_start' => $session->scheduledStart,
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function hub(string $organizationId, Recording $recording): array
    {
        $context = $this->context($organizationId, $recording);
        $limit = max(1, min((int) config('recordings.admin_hub.max_items', 100), 200));
        $grants = RecordingAccessGrant::query()
            ->where('organization_id', $organizationId)
            ->where('recording_id', (string) $recording->getKey())
            ->latest('created_at')
            ->limit($limit)
            ->get();
        $views = RecordingView::query()
            ->forRecording((string) $recording->getKey())
            ->latest('viewed_at')
            ->limit($limit)
            ->get();
        $auditEntries = $this->audit->paginateForOrganization($organizationId, [
            'auditable_type' => 'recordings',
            'auditable_id' => (string) $recording->getKey(),
        ], $limit)->items();

        $userIds = array_values(array_unique(array_filter([
            ...$grants->pluck('granted_to_user_id')->map(static fn (mixed $id): ?string => is_string($id) ? $id : null)->all(),
            ...$grants->pluck('granted_by_user_id')->map(static fn (mixed $id): ?string => is_string($id) ? $id : null)->all(),
            ...$views->pluck('user_id')->map(static fn (mixed $id): string => (string) $id)->all(),
            ...array_map(static fn (mixed $entry): ?string => $entry->actorId, $auditEntries),
        ])));
        $users = $this->accounts->findMany($organizationId, $userIds);
        $groupIds = $grants->pluck('granted_to_group_id')
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $groupLabels = $this->groups->groupsByIds($organizationId, $groupIds);
        $now = now('UTC');

        return [
            'context' => [$context],
            'grants' => $grants->map(static function (RecordingAccessGrant $grant) use ($users, $groupLabels, $now): array {
                $userId = $grant->granted_to_user_id;
                $groupId = $grant->granted_to_group_id;
                $status = $grant->revoked_at !== null
                    ? 'revoked'
                    : ($grant->expires_at->lte($now) ? 'expired' : 'active');

                return [
                    'id' => (string) $grant->getKey(),
                    'target_type' => (string) ($userId === null ? __('recordings::fields.group') : __('recordings::fields.user')),
                    'target' => $userId !== null
                        ? ($users[(string) $userId]->name ?? (string) __('recordings::messages.unavailable'))
                        : (isset($groupLabels[(string) $groupId])
                            ? self::localized($groupLabels[(string) $groupId]->name)
                            : (string) __('recordings::messages.unavailable')),
                    'status' => (string) __('recordings::grant_status.'.$status),
                    'status_value' => $status,
                    'granted_by' => $users[(string) $grant->granted_by_user_id]->name ?? (string) __('recordings::messages.system_actor'),
                    'expires_at' => $grant->expires_at?->toIso8601String(),
                    'revoked_at' => $grant->revoked_at?->toIso8601String(),
                    'reason' => $grant->reason,
                ];
            })->values()->all(),
            'views' => $views->map(static fn (RecordingView $view): array => [
                'viewer' => $users[(string) $view->user_id]->name ?? (string) __('recordings::messages.unavailable'),
                'action' => (string) __('recordings::view_actions.'.(string) $view->action),
                'viewed_at' => $view->viewed_at->toIso8601String(),
                'ip_address' => $view->ip_address,
            ])->values()->all(),
            'audit' => array_values(array_map(static fn (mixed $entry): array => [
                'action' => (string) __('recordings::audit_actions.'.str_replace('.', '_', $entry->action)),
                'actor' => $entry->actorId === null
                    ? (string) __('recordings::messages.system_actor')
                    : ($users[$entry->actorId]->name ?? (string) __('recordings::messages.system_actor')),
                'reason' => $entry->reason,
                'created_at' => $entry->createdAt,
            ], $auditEntries)),
        ];
    }

    private function assertOwned(string $organizationId, Recording $recording): void
    {
        abort_unless($recording->organization_id === $organizationId, 404);
    }

    /** @param array<string, string> $value */
    private static function localized(array $value): string
    {
        return (string) ($value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value) ?: '');
    }
}
