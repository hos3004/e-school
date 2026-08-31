<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class RecordingAccessDecision
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private StaffQueries $staff,
        private StudentDirectoryQueries $students,
        private GroupAdministrationQueries $groups,
        private EnrollmentAdministrationQueries $enrollments,
        private ProgramRulesQueries $programs,
    ) {}

    public function canView(
        Authenticatable $user,
        Recording $recording,
    ): bool {
        $organizationId = $this->organizationId($user);
        if ($organizationId === ''
            || $organizationId !== (string) $recording->organization_id
            || (!$user->can('recording.view') && !$user->can('recording.view.any'))) {
            return false;
        }

        if ($recording->trashed()
            || $recording->status !== RecordingStatus::Ready
            || !$recording->expires_at->isFuture()) {
            return false;
        }

        if ($user->can('recording.view.any')) {
            return true;
        }

        $session = $this->sessions->findForOrganization(
            $organizationId,
            (string) $recording->session_id,
        );
        if ($session === null) {
            return false;
        }

        $userId = (string) $user->getAuthIdentifier();
        $staffProfile = $this->staff->findActiveProfileForUser($userId);
        $staffProfileId = is_array($staffProfile) ? (string) ($staffProfile['id'] ?? '') : '';
        if ((bool) config('recordings.access.teacher_of_session')
            && $staffProfileId !== ''
            && $this->staff->isActiveTeacherForOrganization($organizationId, $staffProfileId)
            && $staffProfileId === $session->staffProfileId) {
            return true;
        }

        $student = $this->students->forUserIds($organizationId, [$userId])[0] ?? null;
        if ($student === null) {
            return false;
        }

        if ($student->archived || !$this->studentHasCourseAccess(
            $organizationId,
            $student->id,
            $session->courseId,
        )) {
            return false;
        }

        return $this->hasActiveGrant(
            $organizationId,
            $recording->id,
            $userId,
            $this->activeGroupIds($organizationId, $student->id),
        );
    }

    public function canDownload(
        Authenticatable $user,
        Recording $recording,
    ): bool {
        return $user->can('recording.download') && $this->canView($user, $recording);
    }

    /**
     * @param Builder<Recording> $query
     * @return Builder<Recording>
     */
    public function scopeVisible(
        Builder $query,
        Authenticatable $user,
    ): Builder {
        $organizationId = $this->organizationId($user);
        if ($organizationId === ''
            || (!$user->can('recording.view') && !$user->can('recording.view.any'))) {
            return $query->whereRaw('1 = 0');
        }

        $query->forOrganization($organizationId)
            ->active()
            ->where('expires_at', '>', now('UTC'));
        if ($user->can('recording.view.any')) {
            return $query;
        }

        $userId = (string) $user->getAuthIdentifier();
        $candidateIds = RecordingAccessGrant::query()
            ->where('organization_id', $organizationId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->where('granted_to_user_id', $userId)
            ->pluck('recording_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $student = $this->students->forUserIds($organizationId, [$userId])[0] ?? null;
        if ($student !== null && !$student->archived) {
            $groupIds = $this->activeGroupIds($organizationId, $student->id);
            if ($groupIds !== []) {
                $candidateIds = [
                    ...$candidateIds,
                    ...RecordingAccessGrant::query()
                        ->where('organization_id', $organizationId)
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now('UTC'))
                        ->whereIn('granted_to_group_id', $groupIds)
                        ->pluck('recording_id')
                        ->map(static fn (mixed $id): string => (string) $id)
                        ->all(),
                ];
            }
        }

        $staffProfile = $this->staff->findActiveProfileForUser($userId);
        $staffProfileId = is_array($staffProfile) ? (string) ($staffProfile['id'] ?? '') : '';
        if ($staffProfileId !== ''
            && $this->staff->isActiveTeacherForOrganization($organizationId, $staffProfileId)) {
            $sessionIds = $this->sessions->sessionIdsForTeacher($organizationId, $staffProfileId);
            if ($sessionIds !== []) {
                $candidateIds = [
                    ...$candidateIds,
                    ...Recording::query()
                        ->forOrganization($organizationId)
                        ->whereIn('session_id', $sessionIds)
                        ->pluck('id')
                        ->map(static fn (mixed $id): string => (string) $id)
                        ->all(),
                ];
            }
        }

        $candidateIds = array_values(array_unique($candidateIds));
        if ($candidateIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $visibleIds = Recording::query()
            ->forOrganization($organizationId)
            ->whereKey($candidateIds)
            ->get()
            ->filter(fn (Recording $recording): bool => $this->canView($user, $recording))
            ->modelKeys();

        return $visibleIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereKey($visibleIds);
    }

    /**
     * @param list<string> $groupIds
     */
    private function hasActiveGrant(
        string $organizationId,
        string $recordingId,
        string $userId,
        array $groupIds,
    ): bool {
        return RecordingAccessGrant::query()
            ->where('organization_id', $organizationId)
            ->where('recording_id', $recordingId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->where(static function (Builder $query) use ($userId, $groupIds): void {
                $query->where('granted_to_user_id', $userId);
                if ($groupIds !== []) {
                    $query->orWhereIn('granted_to_group_id', $groupIds);
                }
            })
            ->exists();
    }

    /** @return list<string> */
    private function activeGroupIds(string $organizationId, string $studentProfileId): array
    {
        return array_values(array_map(
            static fn (mixed $membership): string => $membership->groupId,
            array_filter(
                $this->groups->membershipsForStudent($organizationId, $studentProfileId),
                static fn (mixed $membership): bool => $membership->leftAt === null,
            ),
        ));
    }

    private function studentHasCourseAccess(
        string $organizationId,
        string $studentProfileId,
        string $courseId,
    ): bool {
        if (!(bool) config('recordings.access.blocked_for_frozen_enrollment', true)) {
            return true;
        }

        $programIds = $this->programs->programIdsOfCourse($courseId);

        return collect($this->enrollments->forStudent($organizationId, $studentProfileId))
            ->contains(static fn (mixed $enrollment): bool => in_array($enrollment->programId, $programIds, true)
                && $enrollment->status === 'active');
    }

    private function organizationId(Authenticatable $user): string
    {
        return (string) $user->getAttribute('organization_id');
    }
}
