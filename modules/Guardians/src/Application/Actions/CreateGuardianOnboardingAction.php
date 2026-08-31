<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Enums\GuardianAccountMode;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;
use Shared\Support\Transaction;

/** رحلة إدارية ذرية لإنشاء حساب ولي الأمر وملفه وربطه الأولي بالطالب. */
final readonly class CreateGuardianOnboardingAction
{
    public function __construct(
        private UserAccountProvisioner $accounts,
        private UserAccountDirectory $directory,
        private UserQueryService $users,
        private StudentDirectoryQueries $students,
        private RoleAssignmentGateway $roles,
        private AuditRecorder $audit,
        private CreateGuardianProfile $createProfile,
        private LinkStudentToGuardian $linkStudent,
        private Transaction $transaction,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, string $organizationId, string $actorId): GuardianProfile
    {
        $validated = $this->validated($data);
        $mode = GuardianAccountMode::from((string) $validated['account_mode']);
        $studentProfileId = self::nullableString($validated['student_profile_id'] ?? null);

        if ($studentProfileId !== null && $this->students->find($organizationId, $studentProfileId) === null) {
            throw BusinessRuleViolation::make(
                'guardians.student_not_in_organization',
                'guardians::errors.student_not_in_organization',
            );
        }

        return $this->transaction->run(function () use ($validated, $mode, $studentProfileId, $organizationId, $actorId): GuardianProfile {
            if ($mode === GuardianAccountMode::ExistingAccount) {
                $account = $this->directory->find($organizationId, (string) $validated['existing_user_id']);

                if ($account === null) {
                    throw BusinessRuleViolation::make(
                        'guardians.existing_account_not_found',
                        'guardians::errors.existing_account_not_found',
                    );
                }

                $account = $this->accounts->confirmExistingAccount(
                    organizationId: $organizationId,
                    userId: $account->id,
                    email: $account->email,
                    phone: $account->phone,
                );
            } else {
                $account = $this->accounts->create(new CreateUserAccountData(
                    organizationId: $organizationId,
                    name: (string) $validated['full_name'],
                    email: self::nullableString($validated['email'] ?? null),
                    username: (string) $validated['username'],
                    phone: self::nullableString($validated['phone'] ?? null),
                    password: (string) $validated['password'],
                    locale: (string) $validated['locale'],
                    timezone: (string) $validated['timezone'],
                ));
            }

            if (GuardianProfile::query()->withTrashed()->where('user_id', $account->id)->exists()) {
                throw BusinessRuleViolation::make(
                    'guardians.profile_already_exists',
                    'guardians::errors.profile_already_exists',
                );
            }

            $profile = $this->createProfile->execute([
                'organization_id' => $organizationId,
                'user_id' => $account->id,
                'national_id_last4' => self::nullableString($validated['national_id_last4'] ?? null),
                'occupation' => self::nullableString($validated['occupation'] ?? null),
                'preferred_contact_channel' => self::nullableString($validated['preferred_contact_channel'] ?? null),
            ]);

            $reason = (string) $validated['onboarding_reason'];
            $guardianLinkId = null;

            if ($studentProfileId !== null) {
                $link = $this->linkStudent->execute(
                    guardianProfileId: (string) $profile->getKey(),
                    studentProfileId: $studentProfileId,
                    data: [
                        'relationship' => GuardianRelationship::from((string) $validated['relationship']),
                        'is_primary' => (bool) ($validated['is_primary'] ?? false),
                        'can_act_for' => (bool) ($validated['can_act_for'] ?? false),
                        'visible_sections' => self::stringList($validated['visible_sections'] ?? []),
                    ],
                    actorId: $actorId,
                    reason: $reason,
                );
                $guardianLinkId = (string) $link->getKey();
            }

            $roleName = (string) config('guardians.account.guardian_role');
            $modelType = $this->users->modelType();
            $roleAssigned = $this->roles->assignIfMissing(
                roleName: $roleName,
                modelType: $modelType,
                modelId: $account->id,
                organizationId: $organizationId,
                actorId: $actorId,
            );

            if ($roleAssigned) {
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'permissions.role_assigned',
                    auditableType: $modelType,
                    auditableId: $account->id,
                    oldValues: null,
                    newValues: ['role_name' => $roleName],
                    reason: $reason,
                );
            }

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'guardians.guardian_onboarded',
                auditableType: 'guardian_profile',
                auditableId: (string) $profile->getKey(),
                oldValues: null,
                newValues: [
                    'user_id' => $account->id,
                    'guardian_link_id' => $guardianLinkId,
                ],
                reason: $reason,
            );

            return $profile;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        $mode = GuardianAccountMode::tryFrom((string) ($data['account_mode'] ?? ''));
        $studentProfileId = self::nullableString($data['student_profile_id'] ?? null);
        $allowedSections = (array) config('guardians.links.allowed_visible_sections', []);
        $rules = [
            'account_mode' => ['required', Rule::enum(GuardianAccountMode::class)],
            'existing_user_id' => [$mode === GuardianAccountMode::ExistingAccount ? 'required' : 'nullable', 'ulid'],
            'full_name' => [$mode === GuardianAccountMode::NewAccount ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'username' => [$mode === GuardianAccountMode::NewAccount ? 'required' : 'nullable', 'string', 'max:100'],
            'password' => [$mode === GuardianAccountMode::NewAccount ? 'required' : 'nullable', Password::defaults(), 'confirmed'],
            'locale' => ['required', Rule::in(Locales::supported())],
            'timezone' => ['required', 'timezone:all'],
            'national_id_last4' => ['nullable', 'digits:4'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'preferred_contact_channel' => ['nullable', Rule::enum(ContactChannel::class)],
            'student_profile_id' => ['nullable', 'ulid'],
            'relationship' => [$studentProfileId === null ? 'nullable' : 'required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['nullable', 'boolean'],
            'can_act_for' => ['nullable', 'boolean'],
            'visible_sections' => ['nullable', 'array'],
            'visible_sections.*' => ['string', Rule::in($allowedSections)],
            'onboarding_reason' => ['required', 'string', 'max:2000'],
        ];

        if ($mode === GuardianAccountMode::NewAccount) {
            $rules['email'][] = 'required_without:phone';
            $rules['phone'][] = 'required_without:email';
        }

        return Validator::make($data, $rules)->validate();
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value,
        ))));
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
