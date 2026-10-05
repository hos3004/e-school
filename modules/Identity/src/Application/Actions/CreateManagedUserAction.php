<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\AccessControl\Domain\ValueObjects\RoleData;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;
use Shared\Support\Transaction;

/** إنشاء حساب إداري قابل للاستخدام مع دور واضح وتدقيق داخل معاملة واحدة. */
final readonly class CreateManagedUserAction
{
    public function __construct(
        private RegisterUser $registerUser,
        private AccessControlQuerier $accessControl,
        private RoleAssignmentGateway $roles,
        private UserQueryService $users,
        private AuditRecorder $audit,
        private Transaction $transaction,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, string $organizationId, string $actorId): User
    {
        $availableRoles = collect($this->accessControl->rolesAvailableToOrganization($organizationId))
            ->reject(fn (RoleData $role): bool => in_array(
                $role->name,
                (array) config('identity.managed_accounts.excluded_roles', []),
                true,
            ))
            ->pluck('name')
            ->all();
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email:rfc', 'max:191', 'required_without:phone'],
            'username' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'locale' => ['required', Rule::in(Locales::supported())],
            'timezone' => ['required', 'timezone:all'],
            'role_name' => ['required', Rule::in($availableRoles)],
            'reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        if (in_array((string) $validated['role_name'], (array) config('identity.managed_accounts.excluded_roles', []), true)) {
            throw BusinessRuleViolation::make(
                'identity.managed_role_requires_profile',
                'identity::errors.managed_role_requires_profile',
            );
        }

        return $this->transaction->run(function () use ($validated, $organizationId, $actorId): User {
            $user = $this->registerUser->execute([
                ...$validated,
                'organization_id' => $organizationId,
            ]);
            $roleName = (string) $validated['role_name'];
            $reason = (string) $validated['reason'];

            $this->roles->assignIfMissing(
                roleName: $roleName,
                modelType: $this->users->modelType(),
                modelId: (string) $user->getKey(),
                organizationId: $organizationId,
                actorId: $actorId,
            );

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'identity.managed_user_created',
                auditableType: $this->users->modelType(),
                auditableId: (string) $user->getKey(),
                oldValues: null,
                newValues: ['role_name' => $roleName],
                reason: $reason,
            );

            return $user;
        });
    }
}
