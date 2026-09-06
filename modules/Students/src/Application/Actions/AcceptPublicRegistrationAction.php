<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\Transaction;

/** Attaches a verified account to the original public request before the existing acceptance action. */
final readonly class AcceptPublicRegistrationAction
{
    public function __construct(
        private UserAccountProvisioner $accounts,
        private UserAccountDirectory $directory,
        private UserQueryService $users,
        private RoleAssignmentGateway $roles,
        private AuditRecorder $audit,
        private AcceptRegistrationApplicationAction $accept,
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(string $organizationId, string $id, string $actorId, array $data): string
    {
        return $this->transaction->run(function () use ($organizationId, $id, $actorId, $data): string {
            $application = RegistrationApplication::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($id);
            Gate::authorize('accept', $application);
            if ($application->status->isClearedForAssignment()) {
                return (string) $application->id;
            }
            if (!$application->status->canTransitionTo(RegistrationStatus::Accepted)) {
                throw ValidationException::withMessages(['decision' => __('console_registration.decision_unavailable')]);
            }
            if ($application->duplicate_of_application_id !== null && !($data['identity_confirmed'] ?? false)) {
                throw ValidationException::withMessages(['identity_confirmed' => __('console_registration.review_duplicate')]);
            }
            $beforeUserId = $application->user_id;
            if ($beforeUserId !== null || $data['account_mode'] === 'existing') {
                $idToConfirm = $beforeUserId ?? (string) $data['existing_user_id'];
                abort_unless($this->directory->find($organizationId, $idToConfirm) !== null, 404);
                $account = $this->accounts->confirmExistingAccount(
                    organizationId: $organizationId, userId: $idToConfirm,
                    email: $application->email, phone: $application->phone,
                );
            } else {
                $account = $this->accounts->create(new CreateUserAccountData(
                    organizationId: $organizationId, name: $application->full_name,
                    email: $application->email, username: (string) $data['username'],
                    phone: $application->phone, password: (string) $data['password'],
                    locale: 'ar', timezone: (string) $data['timezone'],
                ));
            }
            if (!$account->isActive()) {
                throw ValidationException::withMessages(['existing_user_id' => __('console_registration.existing_account_inactive')]);
            }
            $profile = StudentProfile::query()->withTrashed()->forOrganization($organizationId)
                ->where('user_id', $account->id)->lockForUpdate()->first();
            if ($profile?->trashed()) {
                throw ValidationException::withMessages(['existing_user_id' => __('students::errors.archived_read_only')]);
            }
            $application->user_id = $account->id;
            $application->save();
            $reason = __('console_registration.audit.accept');
            if ($profile === null) {
                $this->accept->execute($application, $actorId, $reason);
            } else {
                $this->reuseStudent($application, $profile, $actorId, $reason);
            }
            $modelType = $this->users->modelType();
            $role = (string) config('admission.account.student_role');
            if ($this->roles->assignIfMissing($role, $modelType, $account->id, $organizationId, $actorId)) {
                $this->audit->record(
                    organizationId: $organizationId, actorId: $actorId, actorType: 'user',
                    action: 'permissions.role_assigned', auditableType: $modelType, auditableId: $account->id,
                    oldValues: null, newValues: ['role_name' => $role], reason: $reason,
                );
            }
            if ($beforeUserId !== $account->id) {
                $this->audit->record(
                    organizationId: $organizationId, actorId: $actorId, actorType: 'user',
                    action: 'students.registration_account_linked', auditableType: 'registration_application',
                    auditableId: (string) $application->id, oldValues: ['user_id' => $beforeUserId],
                    newValues: ['user_id' => $account->id], reason: $reason,
                );
            }

            return (string) $application->id;
        });
    }

    /** Accept another course request without rewriting the student's original profile or enrollments. */
    private function reuseStudent(RegistrationApplication $application, StudentProfile $profile, string $actorId, string $reason): void
    {
        abort_unless($profile->organization_id === $application->organization_id && $profile->user_id === $application->user_id, 404);
        $from = $application->status;
        if (!$from->canTransitionTo(RegistrationStatus::Accepted) || !RegistrationStatus::Accepted->canTransitionTo(RegistrationStatus::WaitingAssignment)) {
            throw ValidationException::withMessages(['decision' => __('console_registration.decision_unavailable')]);
        }
        $application->forceFill(['status' => RegistrationStatus::Accepted, 'reviewed_by' => $actorId,
            'reviewed_at' => now()->utc(), 'decision_reason' => $reason])->save();
        $application->forceFill(['status' => RegistrationStatus::WaitingAssignment, 'student_profile_id' => $profile->id])->save();
        $this->audit->record(
            organizationId: $application->organization_id, actorId: $actorId, actorType: 'user',
            action: 'academic_status.registration_accepted', auditableType: 'registration_application', auditableId: $application->id,
            oldValues: ['status' => $from->value, 'student_profile_id' => null],
            newValues: ['status' => RegistrationStatus::WaitingAssignment->value, 'student_profile_id' => $profile->id, 'reused_profile' => true],
            reason: $reason,
        );
        $this->events->dispatch(new RegistrationAccepted($application->id, $application->organization_id, $profile->id, $profile->user_id, $actorId));
    }
}
