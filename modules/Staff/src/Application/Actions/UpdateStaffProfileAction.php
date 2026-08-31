<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Events\StaffProfileUpdated;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحديث بيانات ملف معلم من مسار الإدارة — whitelist صارم وتدقيق كامل.
 *
 * حقول الملكية (organization_id, user_id, terminated_at) لا تُعدَّل من هنا
 * أبدًا: انتماء الملف والحالة الوظيفية قرارات دورة حياة لها إجراءاتها الخاصة.
 */
final readonly class UpdateStaffProfileAction
{
    /** @var list<string> الأعمدة المسموح تعديلها عبر هذا الإجراء */
    private const EDITABLE = [
        'staff_code',
        'employment_type',
        'gender',
        'country_id',
        'region_id',
        'date_of_birth',
        'phone',
        'hired_at',
        'specializations',
        'bio',
    ];

    public function __construct(
        private GeographyQueries $geography,
        private UserQueryService $users,
        private AuditRecorder $audit,
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $changes
     */
    public function execute(
        StaffProfile $profile,
        array $changes,
        string $actorId,
        string $reason,
    ): StaffProfile {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'staff.update_reason_required',
                'staff::errors.update_reason_required',
            );
        }

        if ($profile->trashed()) {
            throw BusinessRuleViolation::make(
                'staff.archived_read_only',
                'staff::errors.archived_read_only',
                ['staff_id' => (string) $profile->getKey()],
            );
        }

        $this->assertActorInSameOrganization($profile, $actorId);

        $changes = collect($changes)->only(self::EDITABLE)->all();

        if ($changes === []) {
            return $profile;
        }

        $changes = $this->normalize($changes);
        $this->validate($profile, $changes);

        // النموذج يعيد إرسال كل الحقول، والمقارنة الخام تعتبر 'female' مختلفة عن
        // StaffGender::Female. نملأ النموذج أولًا ثم نسأل Eloquent وحده عمّا تغيّر
        // فعلًا، فهو الوحيد الذي يقارن بعد تطبيق التحويلات.
        $profile->fill($changes);

        $changes = collect($changes)
            ->filter(static fn (mixed $value, string $field): bool => $profile->isDirty($field))
            ->all();

        if ($changes === []) {
            return $profile;
        }

        /** @var array{changed: array<string, mixed>, old: array<string, mixed>} $result */
        $result = $this->transaction->run(function () use ($profile, $changes): array {
            $old = [];
            foreach ($changes as $field => $newValue) {
                $old[$field] = $this->toPrimitive($field, $profile->getOriginal($field));
            }

            $profile->save();

            return ['changed' => $changes, 'old' => $old];
        });

        $primitives = $this->toPrimitives($result['changed']);

        $this->audit->record(
            organizationId: (string) $profile->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'staff.profile_updated',
            auditableType: 'staff_profile',
            auditableId: (string) $profile->getKey(),
            oldValues: $result['old'],
            newValues: $primitives,
            reason: trim($reason),
        );

        $this->events->dispatch(new StaffProfileUpdated(
            staffProfileId: (string) $profile->getKey(),
            organizationId: (string) $profile->organization_id,
            changes: $primitives,
        ));

        return $profile;
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function normalize(array $changes): array
    {
        if (isset($changes['gender']) && is_string($changes['gender'])) {
            $changes['gender'] = StaffGender::from($changes['gender']);
        }

        if (isset($changes['employment_type']) && is_string($changes['employment_type'])) {
            $changes['employment_type'] = EmploymentType::from($changes['employment_type']);
        }

        if (isset($changes['specializations']) && is_array($changes['specializations'])) {
            $changes['specializations'] = array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $changes['specializations'],
            )));
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function validate(StaffProfile $profile, array $changes): void
    {
        if (array_key_exists('staff_code', $changes)) {
            $code = trim((string) $changes['staff_code']);

            if ($code === '') {
                throw BusinessRuleViolation::make(
                    'staff.staff_code_required',
                    'staff::validation.staff_code_required',
                );
            }

            $taken = StaffProfile::query()
                ->withTrashed()
                ->where('staff_code', $code)
                ->whereKeyNot((string) $profile->getKey())
                ->exists();

            if ($taken) {
                throw BusinessRuleViolation::make(
                    'staff.staff_code_taken',
                    'staff::validation.staff_code_unique',
                    ['staff_code' => $code],
                );
            }
        }

        $countryId = isset($changes['country_id'])
            ? (string) $changes['country_id']
            : (string) ($profile->country_id ?? '');
        $regionId = isset($changes['region_id'])
            ? (string) $changes['region_id']
            : (string) ($profile->region_id ?? '');

        if ($countryId !== '' && !$this->geography->regionExistsIn($regionId, $countryId)) {
            throw BusinessRuleViolation::make(
                'staff.region_country_mismatch',
                'staff::validation.region_country_mismatch',
            );
        }

        if (isset($changes['date_of_birth'], $changes['hired_at'])
            && strtotime((string) $changes['date_of_birth']) >= strtotime((string) $changes['hired_at'])) {
            throw BusinessRuleViolation::make(
                'staff.hire_before_birth_invalid',
                'staff::validation.hire_before_birth_invalid',
            );
        }
    }

    private function assertActorInSameOrganization(StaffProfile $profile, string $actorId): void
    {
        $actor = $this->users->findSummary($actorId);

        if ($actor === null
            || !hash_equals((string) $profile->organization_id, $actor->organizationId)) {
            throw BusinessRuleViolation::make(
                'staff.organization_mismatch',
                'staff::errors.organization_mismatch',
            );
        }
    }

    /**
     * الحمولة المنشورة قيَم بدائية فقط — الـ enums تتحول إلى قيمتها النصية.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function toPrimitives(array $values): array
    {
        $primitives = [];

        foreach ($values as $field => $value) {
            $primitives[$field] = $this->toPrimitive($field, $value);
        }

        return $primitives;
    }

    private function toPrimitive(string $field, mixed $value): mixed
    {
        if ($field === 'gender' && $value instanceof StaffGender) {
            return $value->value;
        }

        if ($field === 'employment_type' && $value instanceof EmploymentType) {
            return $value->value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
