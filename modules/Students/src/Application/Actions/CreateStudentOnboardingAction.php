<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Contracts\RegistrationOfferingQueries;
use Modules\Students\Domain\Enums\StudentAccountMode;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;
use Shared\Support\Transaction;

/**
 * رحلة إنشاء الطالب الإداري: حساب موثوق ← طلب ← تقديم ← قبول ← ملف طالب.
 *
 * لا ينشئ هذا الإجراء ملفًا مباشرة، وكل الخطوات داخل معاملة واحدة حتى لا
 * يبقى حساب يتيم إن فشل القبول أو إسناد دور الطالب.
 */
final readonly class CreateStudentOnboardingAction
{
    public function __construct(
        private UserAccountProvisioner $accounts,
        private UserAccountDirectory $directory,
        private UserQueryService $users,
        private RoleAssignmentGateway $roles,
        private AuditRecorder $audit,
        private GeographyQueries $geography,
        private RegistrationOfferingQueries $offerings,
        private CreateRegistrationApplicationAction $createApplication,
        private SubmitRegistrationApplicationAction $submitApplication,
        private AcceptRegistrationApplicationAction $acceptApplication,
        private Transaction $transaction,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, string $organizationId, string $actorId): StudentProfile
    {
        $validated = $this->validated($data);
        $mode = StudentAccountMode::from((string) $validated['account_mode']);

        $countryId = self::nullableString($validated['country_id'] ?? null);
        $regionId = self::nullableString($validated['region_id'] ?? null);

        if ($countryId !== null && $regionId !== null && !$this->geography->regionExistsIn(
            $regionId,
            $countryId,
        )) {
            throw BusinessRuleViolation::make(
                'registration.region_not_in_country',
                'students::validation.region_not_in_country',
            );
        }

        if (!$this->offerings->isAvailable(
            $organizationId,
            (string) $validated['preferred_program_id'],
            (string) $validated['preferred_course_id'],
        )) {
            throw BusinessRuleViolation::make(
                'registration.offering_invalid',
                'students::validation.registration_offering_invalid',
            );
        }

        return $this->transaction->run(function () use ($validated, $mode, $organizationId, $actorId, $countryId, $regionId): StudentProfile {
            if ($mode === StudentAccountMode::ExistingAccount) {
                $account = $this->directory->find($organizationId, (string) $validated['existing_user_id']);

                if ($account === null) {
                    throw BusinessRuleViolation::make(
                        'students.existing_account_not_found',
                        'students::errors.existing_account_not_found',
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
                    contactOptional: true,
                ));
            }

            if (StudentProfile::query()->withTrashed()->where('user_id', $account->id)->exists()) {
                throw BusinessRuleViolation::make(
                    'registration.student_profile_exists',
                    'students::errors.registration_student_profile_exists',
                );
            }

            $application = $this->createApplication->execute([
                'full_name' => $account->name,
                'date_of_birth' => self::nullableString($validated['date_of_birth'] ?? null),
                'gender' => self::nullableString($validated['gender'] ?? null),
                'country_id' => $countryId,
                'region_id' => $regionId,
                'email' => $account->email,
                'phone' => $account->phone,
                'preferred_program_id' => (string) $validated['preferred_program_id'],
                'preferred_course_id' => (string) $validated['preferred_course_id'],
                'notes' => self::nullableString($validated['notes'] ?? null),
            ], $organizationId, $account->id);

            /** @var list<string> $requiredFields */
            $requiredFields = array_values((array) config('admission.admin_creation.required_fields', []));
            $application = $this->submitApplication->execute($application, $requiredFields);
            $application = $this->acceptApplication->execute(
                $application,
                $actorId,
                (string) $validated['acceptance_reason'],
            );

            $studentRole = (string) config('admission.account.student_role');
            $modelType = $this->users->modelType();
            $roleAssigned = $this->roles->assignIfMissing(
                roleName: $studentRole,
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
                    newValues: ['role_name' => $studentRole],
                    reason: (string) $validated['acceptance_reason'],
                );
            }

            /** @var StudentProfile $profile */
            $profile = StudentProfile::query()->findOrFail($application->student_profile_id);
            $profile->fill([
                'nationality' => self::nullableString($validated['nationality'] ?? null),
                'city' => self::nullableString($validated['city'] ?? null),
                'preferred_language' => self::nullableString($validated['preferred_language'] ?? null),
                'notes' => self::nullableString($validated['notes'] ?? null),
            ]);
            $profile->save();

            return $profile;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        $mode = StudentAccountMode::tryFrom((string) ($data['account_mode'] ?? ''));
        $rules = [
            'account_mode' => ['required', Rule::enum(StudentAccountMode::class)],
            'existing_user_id' => [$mode === StudentAccountMode::ExistingAccount ? 'required' : 'nullable', 'ulid'],
            'full_name' => [$mode === StudentAccountMode::NewAccount ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'username' => [
                $mode === StudentAccountMode::NewAccount ? 'required' : 'nullable',
                'string',
                'min:'.(int) config('admission.username.min_length'),
                'max:'.(int) config('admission.username.max_length'),
            ],
            'password' => [$mode === StudentAccountMode::NewAccount ? 'required' : 'nullable', Password::defaults(), 'confirmed'],
            'locale' => ['required', Rule::in(Locales::supported())],
            'timezone' => ['required', 'timezone:all'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(StudentGender::class)],
            'country_id' => ['nullable', 'ulid', 'required_with:region_id'],
            'region_id' => ['nullable', 'ulid', 'required_with:country_id'],
            'preferred_program_id' => ['required', 'ulid'],
            'preferred_course_id' => ['required', 'ulid'],
            'acceptance_reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['nullable', Rule::in(Locales::supported())],
        ];

        return Validator::make($data, $rules)->validate();
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
