<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\StaffAccountMode;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;

/**
 * الرحلة الإدارية الذرية لإنشاء المعلم وحسابه وعقده ومؤهلاته.
 */
final readonly class CreateTeacherOnboardingAction
{
    public function __construct(
        private UserAccountProvisioner $accounts,
        private UserAccountDirectory $directory,
        private UserQueryService $users,
        private RoleAssignmentGateway $roles,
        private AuditRecorder $audit,
        private GeographyQueries $geography,
        private AcademicCatalogQueries $catalog,
        private CreateStaffProfile $createProfile,
        private CreateTeacherContract $createContract,
        private AddTeacherRate $addRate,
        private AssignTeacherQualificationsAction $assignQualifications,
        private Transaction $transaction,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, string $organizationId, string $actorId): StaffProfile
    {
        $validated = $this->validated($data);
        $mode = StaffAccountMode::from((string) $validated['account_mode']);
        $courseIds = self::stringList($validated['course_ids'] ?? []);

        if (!$this->geography->regionExistsIn(
            (string) $validated['region_id'],
            (string) $validated['country_id'],
        )) {
            throw BusinessRuleViolation::make(
                'staff.region_country_mismatch',
                'staff::validation.region_country_mismatch',
            );
        }

        if (count($this->catalog->coursesByIds($organizationId, $courseIds)) !== count($courseIds)) {
            throw BusinessRuleViolation::make(
                'staff.qualification_invalid_course',
                'staff::errors.qualification_invalid_course',
            );
        }

        return $this->transaction->run(function () use ($validated, $mode, $courseIds, $organizationId, $actorId): StaffProfile {
            if ($mode === StaffAccountMode::ExistingAccount) {
                $account = $this->directory->find($organizationId, (string) $validated['existing_user_id']);

                if ($account === null) {
                    throw BusinessRuleViolation::make(
                        'staff.existing_account_not_found',
                        'staff::errors.existing_account_not_found',
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

            if (StaffProfile::query()->withTrashed()->where('user_id', $account->id)->exists()) {
                throw BusinessRuleViolation::make(
                    'staff.profile_already_exists',
                    'staff::errors.profile_already_exists',
                );
            }

            $profile = $this->createProfile->execute(
                organizationId: $organizationId,
                userId: $account->id,
                staffCode: (string) $validated['staff_code'],
                employmentType: EmploymentType::from((string) $validated['employment_type']),
                gender: StaffGender::from((string) $validated['gender']),
                countryId: (string) $validated['country_id'],
                regionId: (string) $validated['region_id'],
                dateOfBirth: self::nullableString($validated['date_of_birth'] ?? null),
                phone: $account->phone,
                hiredAt: (string) $validated['hired_at'],
                bio: self::localizedBio($validated['bio'] ?? null, (string) $validated['locale']),
                specializations: self::stringList($validated['specializations'] ?? []),
            );

            $basis = ContractBasis::from((string) $validated['contract_basis']);
            $currency = strtoupper((string) $validated['currency']);
            $reason = (string) $validated['onboarding_reason'];
            $contract = $this->createContract->execute(
                profile: $profile,
                basis: $basis,
                effectiveFrom: (string) $validated['contract_effective_from'],
                effectiveTo: self::nullableString($validated['contract_effective_to'] ?? null),
                baseAmount: isset($validated['base_amount_major'])
                    ? Money::fromMajor((string) $validated['base_amount_major'], $currency)
                    : null,
                monthlyTargetSessions: self::nullableInt($validated['monthly_target_sessions'] ?? null),
                targetAdminTasks: self::nullableInt($validated['target_admin_tasks'] ?? null),
                targetTrainingSessions: self::nullableInt($validated['target_training_sessions'] ?? null),
                actorId: $actorId,
                reason: $reason,
            );

            if ($basis->requiresRates()) {
                $this->addRate->execute(
                    contract: $contract,
                    scope: RateScope::Default,
                    amount: Money::fromMajor((string) $validated['default_rate_major'], $currency),
                    effectiveFrom: (string) $validated['contract_effective_from'],
                    effectiveTo: self::nullableString($validated['contract_effective_to'] ?? null),
                    actorId: $actorId,
                    reason: $reason,
                );
            }

            $this->assignQualifications->execute(
                profile: $profile,
                courseIds: $courseIds,
                actorId: $actorId,
                reason: $reason,
                notes: self::nullableString($validated['qualification_notes'] ?? null),
            );

            $teacherRole = (string) config('staff.account.teacher_role');
            $modelType = $this->users->modelType();
            $roleAssigned = $this->roles->assignIfMissing(
                roleName: $teacherRole,
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
                    newValues: ['role_name' => $teacherRole],
                    reason: $reason,
                );
            }

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'staff.teacher_onboarded',
                auditableType: 'staff_profile',
                auditableId: (string) $profile->getKey(),
                oldValues: null,
                newValues: [
                    'user_id' => $account->id,
                    'contract_id' => (string) $contract->getKey(),
                    'course_ids' => $courseIds,
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
        $mode = StaffAccountMode::tryFrom((string) ($data['account_mode'] ?? ''));
        $basis = ContractBasis::tryFrom((string) ($data['contract_basis'] ?? ''));
        $currencyOptions = array_map('strtoupper', (array) config('staff.currency.supported', []));
        $rules = [
            'account_mode' => ['required', Rule::enum(StaffAccountMode::class)],
            'existing_user_id' => [$mode === StaffAccountMode::ExistingAccount ? 'required' : 'nullable', 'ulid'],
            'full_name' => [$mode === StaffAccountMode::NewAccount ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'username' => [$mode === StaffAccountMode::NewAccount ? 'required' : 'nullable', 'string', 'max:100'],
            'password' => [$mode === StaffAccountMode::NewAccount ? 'required' : 'nullable', Password::defaults(), 'confirmed'],
            'locale' => ['required', Rule::in(Locales::supported())],
            'timezone' => ['required', 'timezone:all'],
            'staff_code' => ['required', 'string', 'max:32', Rule::unique('staff_profiles', 'staff_code')],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'gender' => ['required', Rule::enum(StaffGender::class)],
            'country_id' => ['required', 'ulid'],
            'region_id' => ['required', 'ulid'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'hired_at' => ['required', 'date'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
            'contract_basis' => ['required', Rule::enum(ContractBasis::class)],
            'contract_effective_from' => ['required', 'date'],
            'contract_effective_to' => ['nullable', 'date', 'after:contract_effective_from'],
            'currency' => ['required', Rule::in($currencyOptions)],
            'base_amount_major' => [
                Rule::requiredIf($basis?->requiresBaseAmount() === true),
                Rule::prohibitedIf($basis === ContractBasis::PerSession),
                'nullable',
                'decimal:0,2',
                'min:0',
            ],
            'default_rate_major' => [
                Rule::requiredIf($basis?->requiresRates() === true),
                Rule::prohibitedIf($basis === ContractBasis::Salary),
                'nullable',
                'decimal:0,2',
                'gt:0',
            ],
            'monthly_target_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_admin_tasks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_training_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['ulid', 'distinct'],
            'qualification_notes' => ['nullable', 'string', 'max:2000'],
            'onboarding_reason' => ['required', 'string', 'max:2000'],
        ];

        if ($mode === StaffAccountMode::NewAccount) {
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

    /** @return array<string, string>|null */
    private static function localizedBio(mixed $value, string $locale): ?array
    {
        $value = self::nullableString($value);

        return $value === null ? null : [$locale => $value];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
