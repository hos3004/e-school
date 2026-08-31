<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Guardians\Domain\Contracts\GuardianQuery;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class SessionAccessDecision
{
    public function __construct(
        private StaffQueries $staff,
        private StudentDirectoryQueries $students,
        private GuardianQuery $guardians,
    ) {}

    public function canCreate(
        Authenticatable $user,
        string $staffProfileId,
    ): bool {
        if (!$user->can('session.create') || $this->organizationId($user) === '') {
            return false;
        }

        return $this->hasInstitutionWriteScope($user, 'session.create')
            || $this->staffProfileId($user) === $staffProfileId;
    }

    public function canView(
        Authenticatable $user,
        Session $session,
    ): bool {
        if (!$user->can('session.view') || !$this->sameOrganization($user, $session)) {
            return false;
        }

        if ($this->hasInstitutionReadScope($user) || $this->isAssignedTeacher($user, $session)) {
            return true;
        }

        $studentProfileId = $this->studentProfileId($user);
        if ($studentProfileId !== null && $this->hasActiveParticipant($session, [$studentProfileId])) {
            return true;
        }

        return $this->guardianStudentIds($user, $session, false) !== [];
    }

    public function canManageAssigned(
        Authenticatable $user,
        Session $session,
        string $permission,
    ): bool {
        return $this->sameOrganization($user, $session)
            && ($this->hasInstitutionWriteScope($user, $permission)
                || ($user->can($permission) && $this->isAssignedTeacher($user, $session)));
    }

    public function canPostpone(
        Authenticatable $user,
        Session $session,
    ): bool {
        if (!$user->can('session.postpone.request') || !$this->sameOrganization($user, $session)) {
            return false;
        }

        if ($this->hasInstitutionWriteScope($user, 'session.postpone.request')
            || $this->isAssignedTeacher($user, $session)) {
            return true;
        }

        $studentProfileId = $this->studentProfileId($user);
        if ($studentProfileId !== null && $this->hasActiveParticipant($session, [$studentProfileId])) {
            return true;
        }

        return $this->guardianStudentIds($user, $session, true) !== [];
    }

    /**
     * @param Builder<Session> $query
     * @return Builder<Session>
     */
    public function scopeVisible(
        Builder $query,
        Authenticatable $user,
    ): Builder {
        $organizationId = $this->organizationId($user);
        if ($organizationId === '' || !$user->can('session.view')) {
            return $query->whereRaw('1 = 0');
        }

        $query->forOrganization($organizationId);
        if ($this->hasInstitutionReadScope($user)) {
            return $query;
        }

        $staffProfileId = $this->staffProfileId($user);
        $studentProfileIds = array_values(array_unique(array_filter([
            $this->studentProfileId($user),
            ...$this->guardianStudentIds($user, null, false),
        ])));

        if ($staffProfileId === null && $studentProfileIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(static function (Builder $scope) use ($staffProfileId, $studentProfileIds): void {
            if ($staffProfileId !== null) {
                $scope->where('staff_profile_id', $staffProfileId);
            }

            if ($studentProfileIds !== []) {
                $participants = static fn (Builder $query): Builder => $query
                    ->whereNull('revoked_at')
                    ->whereIn('student_profile_id', $studentProfileIds);
                if ($staffProfileId === null) {
                    $scope->whereHas('participants', $participants);
                } else {
                    $scope->orWhereHas('participants', $participants);
                }
            }
        });
    }

    private function hasInstitutionReadScope(Authenticatable $user): bool
    {
        return $user->can('student.view.any');
    }

    private function hasInstitutionWriteScope(Authenticatable $user, string $permission): bool
    {
        return $user->can($permission) && $this->hasInstitutionReadScope($user);
    }

    private function sameOrganization(
        Authenticatable $user,
        Session $session,
    ): bool {
        $organizationId = $this->organizationId($user);

        return $organizationId !== '' && $organizationId === (string) $session->organization_id;
    }

    private function isAssignedTeacher(
        Authenticatable $user,
        Session $session,
    ): bool {
        $staffProfileId = $this->staffProfileId($user);

        return $staffProfileId !== null
            && $staffProfileId === (string) $session->staff_profile_id;
    }

    private function hasActiveParticipant(Session $session, array $studentProfileIds): bool
    {
        return $studentProfileIds !== []
            && $session->participants()
                ->whereNull('revoked_at')
                ->whereIn('student_profile_id', $studentProfileIds)
                ->exists();
    }

    private function staffProfileId(Authenticatable $user): ?string
    {
        $organizationId = $this->organizationId($user);
        $profile = $this->staff->findActiveProfileForUser((string) $user->getAuthIdentifier());
        $profileId = is_array($profile) ? (string) ($profile['id'] ?? '') : '';

        return $profileId !== ''
            && $this->staff->isActiveTeacherForOrganization($organizationId, $profileId)
                ? $profileId
                : null;
    }

    private function studentProfileId(Authenticatable $user): ?string
    {
        $student = $this->students->forUserIds(
            $this->organizationId($user),
            [(string) $user->getAuthIdentifier()],
        )[0] ?? null;

        return $student !== null && !$student->archived ? $student->id : null;
    }

    /**
     * @return list<string>
     */
    private function guardianStudentIds(
        Authenticatable $user,
        ?Session $session,
        bool $requireActingPermission,
    ): array {
        $organizationId = $this->organizationId($user);
        $userId = (string) $user->getAuthIdentifier();
        $studentProfileIds = $session === null
            ? SessionParticipant::query()
                ->activeInvitation()
                ->whereHas('session', static fn (Builder $query): Builder => $query
                    ->where('organization_id', $organizationId))
                ->distinct()
                ->pluck('student_profile_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all()
            : $session->participants()
                ->whereNull('revoked_at')
                ->pluck('student_profile_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

        return array_values(array_filter(
            $studentProfileIds,
            function (string $studentProfileId) use ($userId, $requireActingPermission): bool {
                foreach ($this->guardians->guardiansForStudent($studentProfileId) as $guardian) {
                    if ($guardian->userId !== $userId
                        || $guardian->verifiedAt === null
                        || !in_array('schedule', $guardian->visibleSections, true)) {
                        continue;
                    }

                    return !$requireActingPermission
                        || $this->guardians->userCanActForStudent($userId, $studentProfileId);
                }

                return false;
            },
        ));
    }

    private function organizationId(Authenticatable $user): string
    {
        return (string) $user->getAttribute('organization_id');
    }
}
