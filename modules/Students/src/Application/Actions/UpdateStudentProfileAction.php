<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Events\StudentProfileUpdated;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحديث بيانات ملف طالب قائم — whitelist صارم وتدقيق كامل.
 *
 * student_code/organization_id/user_id والحالة الأكاديمية لا تُعدَّل من هنا،
 * وjoined_at قرار دورة حياة له مساره الخاص. الملف المؤرشف للقراءة فقط.
 */
final readonly class UpdateStudentProfileAction
{
    /** @var list<string> الأعمدة المسموح تعديلها عبر هذا الإجراء */
    private const EDITABLE = [
        'date_of_birth',
        'gender',
        'nationality',
        'country_id',
        'region_id',
        'city',
        'preferred_language',
        'notes',
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
        StudentProfile $student,
        array $changes,
        string $actorId,
        string $reason,
    ): StudentProfile {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'students.update_reason_required',
                'students::errors.update_reason_required',
            );
        }

        if ($student->trashed()) {
            throw BusinessRuleViolation::make(
                'students.archived_read_only',
                'students::errors.archived_read_only',
                ['student_id' => (string) $student->getKey()],
            );
        }

        $this->assertActorInSameOrganization($student, $actorId);

        $changes = collect($changes)->only(self::EDITABLE)->all();

        if ($changes === []) {
            return $student;
        }

        $changes = $this->normalize($changes);
        $this->validateGeography($student, $changes);

        // النموذج يعيد إرسال كل الحقول، والمقارنة الخام تعتبر 'male' مختلفة عن
        // StudentGender::Male. نملأ النموذج أولًا ثم نسأل Eloquent وحده عمّا تغيّر
        // فعلًا، فهو الوحيد الذي يقارن بعد تطبيق التحويلات.
        $student->fill($changes);

        $changes = collect($changes)
            ->filter(static fn (mixed $value, string $field): bool => $student->isDirty($field))
            ->all();

        if ($changes === []) {
            return $student;
        }

        /** @var array{changed: array<string, mixed>, old: array<string, mixed>} $result */
        $result = $this->transaction->run(function () use ($student, $changes): array {
            $old = [];
            foreach ($changes as $field => $newValue) {
                $old[$field] = $student->getOriginal($field);
            }

            $student->save();

            return ['changed' => $changes, 'old' => $old];
        });

        $primitives = $this->toPrimitives($result['changed']);

        $this->audit->record(
            organizationId: (string) $student->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'students.profile_updated',
            auditableType: 'student_profile',
            auditableId: (string) $student->getKey(),
            oldValues: $this->toPrimitives($result['old']),
            newValues: $primitives,
            reason: trim($reason),
        );

        $this->events->dispatch(new StudentProfileUpdated(
            studentId: (string) $student->getKey(),
            organizationId: (string) $student->organization_id,
            changes: $primitives,
        ));

        return $student;
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function normalize(array $changes): array
    {
        if (isset($changes['gender']) && is_string($changes['gender'])) {
            $changes['gender'] = StudentGender::from($changes['gender']);
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function validateGeography(StudentProfile $student, array $changes): void
    {
        $countryId = isset($changes['country_id'])
            ? (string) ($changes['country_id'] ?? '')
            : (string) ($student->country_id ?? '');
        $regionId = isset($changes['region_id'])
            ? (string) ($changes['region_id'] ?? '')
            : (string) ($student->region_id ?? '');

        if ($countryId === '') {
            return;
        }

        if (!$this->geography->regionExistsIn($regionId, $countryId)) {
            throw BusinessRuleViolation::make(
                'students.region_country_mismatch',
                'students::validation.region_not_in_country',
            );
        }
    }

    private function assertActorInSameOrganization(StudentProfile $student, string $actorId): void
    {
        $actor = $this->users->findSummary($actorId);

        if ($actor === null
            || !hash_equals((string) $student->organization_id, $actor->organizationId)) {
            throw BusinessRuleViolation::make(
                'students.organization_mismatch',
                'students::errors.organization_mismatch',
            );
        }
    }

    /**
     * الحمولة قيَم بدائية فقط — الـ enums تتحول إلى قيمتها النصية.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function toPrimitives(array $values): array
    {
        return collect($values)->map(
            static fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value,
        )->all();
    }
}
