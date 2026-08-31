<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Codes\EntityCodeGenerator;
use Shared\Support\Transaction;

final readonly class ImportStaffAction
{
    public function __construct(
        private UserAccountProvisioner $accounts,
        private Transaction $transaction,
        private EntityCodeGenerator $codes,
    ) {}

    /**
     * Backend row importer only; existing accounts require an explicit `user_id`.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{imported_count: int, skipped_count: int, errors: array<int, array{row: int, errors: array<string, string>}>}
     */
    public function execute(array $rows, string $organizationId): array
    {
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $fullName = trim((string) ($row['full_name'] ?? ''));
            $email = $this->nullable($row['email'] ?? null);
            $phone = $this->nullable($row['phone'] ?? null);
            $existingUserId = $this->nullable($row['user_id'] ?? null);
            $employmentType = trim((string) ($row['employment_type'] ?? ''));
            $gender = $this->nullable($row['gender'] ?? null);
            $rowErrors = [];

            if ($fullName === '') {
                $rowErrors['full_name'] = __('staff::validation.full_name_required');
            }
            if ($email === null && $phone === null) {
                $rowErrors['contact'] = __('staff::validation.contact_required');
            }
            if (!in_array($employmentType, array_column(EmploymentType::cases(), 'value'), true)) {
                $rowErrors['employment_type'] = __('staff::validation.employment_type_invalid');
            }
            if ($gender !== null && !in_array($gender, array_column(StaffGender::cases(), 'value'), true)) {
                $rowErrors['gender'] = __('staff::validation.gender_invalid');
            }

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];

                continue;
            }

            try {
                $outcome = $this->transaction->run(function () use (
                    $organizationId,
                    $existingUserId,
                    $fullName,
                    $email,
                    $phone,
                    $employmentType,
                    $gender,
                    $row,
                ): string {
                    $account = $existingUserId !== null
                        ? $this->accounts->confirmExistingAccount($organizationId, $existingUserId, $email, $phone)
                        : $this->accounts->create(new CreateUserAccountData(
                            organizationId: $organizationId,
                            name: $fullName,
                            email: $email,
                            username: 'teacher.'.mb_strtolower(substr((string) Str::ulid(), -10)),
                            phone: $phone,
                            password: Str::password((int) config('admission.account.generated_password_length')),
                            locale: (string) config('app.locale'),
                            timezone: (string) config('app.timezone'),
                        ));

                    $exists = StaffProfile::query()
                        ->withTrashed()
                        ->forOrganization($organizationId)
                        ->where('user_id', $account->id)
                        ->exists();

                    if ($exists) {
                        return 'skipped';
                    }

                    StaffProfile::query()->create([
                        'organization_id' => $organizationId,
                        'user_id' => $account->id,
                        'staff_code' => $this->nullable($row['staff_code'] ?? null) ?? $this->codes->next('staff'),
                        'employment_type' => $employmentType,
                        'gender' => $gender,
                        'hired_at' => $this->nullable($row['hired_at'] ?? null) ?? now()->utc()->toDateString(),
                    ]);

                    return 'imported';
                });

                $outcome === 'skipped' ? $skippedCount++ : $importedCount++;
            } catch (\Throwable) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['row' => __('staff::validation.import_row_failed')],
                ];
            }
        }

        return [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'errors' => $errors,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
