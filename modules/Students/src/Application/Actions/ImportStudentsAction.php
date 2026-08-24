<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\Transaction;

final readonly class ImportStudentsAction
{
    public function __construct(
        private UserAccountProvisioner $accounts,
        private GeographyQueries $geography,
        private Transaction $transaction,
    ) {}

    /**
     * Backend row importer only; upload mapping/preview UI remains pending.
     * Existing accounts must be linked explicitly with `user_id`.
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
            $dateOfBirth = trim((string) ($row['date_of_birth'] ?? ''));
            $gender = trim((string) ($row['gender'] ?? ''));
            $countryId = trim((string) ($row['country_id'] ?? ''));
            $regionId = trim((string) ($row['region_id'] ?? ''));
            $email = $this->nullable($row['email'] ?? null);
            $phone = $this->nullable($row['phone'] ?? null);
            $existingUserId = $this->nullable($row['user_id'] ?? null);
            $rowErrors = [];

            if ($fullName === '') {
                $rowErrors['full_name'] = __('students::validation.full_name_required');
            }
            if ($dateOfBirth === '' || strtotime($dateOfBirth) === false) {
                $rowErrors['date_of_birth'] = __('students::validation.date_of_birth_required');
            }
            if (!in_array($gender, array_column(StudentGender::cases(), 'value'), true)) {
                $rowErrors['gender'] = __('students::validation.gender_invalid');
            }
            if ($email === null && $phone === null) {
                $rowErrors['contact'] = __('students::validation.contact_required');
            }
            if ($countryId === '' || $regionId === '' || !$this->geography->regionExistsIn($regionId, $countryId)) {
                $rowErrors['region_id'] = __('students::validation.region_not_in_country');
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
                    $dateOfBirth,
                    $gender,
                    $countryId,
                    $regionId,
                    $row,
                ): string {
                    $account = $existingUserId !== null
                        ? $this->accounts->confirmExistingAccount($organizationId, $existingUserId, $email, $phone)
                        : $this->accounts->create(new CreateUserAccountData(
                            organizationId: $organizationId,
                            name: $fullName,
                            email: $email,
                            username: 'student.'.mb_strtolower(substr((string) Str::ulid(), -10)),
                            phone: $phone,
                            password: Str::password((int) config('admission.account.generated_password_length')),
                            locale: (string) config('app.locale'),
                            timezone: (string) config('app.timezone'),
                        ));

                    $exists = StudentProfile::query()
                        ->withTrashed()
                        ->forOrganization($organizationId)
                        ->where('user_id', $account->id)
                        ->exists();

                    if ($exists) {
                        return 'skipped';
                    }

                    StudentProfile::query()->create([
                        'organization_id' => $organizationId,
                        'user_id' => $account->id,
                        'student_code' => $this->nullable($row['student_code'] ?? null) ?? 'STU-'.Str::upper(substr((string) Str::ulid(), -8)),
                        'date_of_birth' => $dateOfBirth,
                        'gender' => $gender,
                        'country_id' => $countryId,
                        'region_id' => $regionId,
                        'joined_at' => now()->utc()->toDateString(),
                    ]);

                    return 'imported';
                });

                $outcome === 'skipped' ? $skippedCount++ : $importedCount++;
            } catch (\Throwable) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['row' => __('students::validation.import_row_failed')],
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
