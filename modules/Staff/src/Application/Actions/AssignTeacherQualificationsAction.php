<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherCourse;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class AssignTeacherQualificationsAction
{
    public function __construct(
        private AcademicCatalogQueries $catalog,
        private AuditRecorder $audit,
        private Transaction $transaction,
    ) {}

    /**
     * @param list<string> $courseIds
     * @return list<TeacherCourse>
     */
    public function execute(
        StaffProfile $profile,
        array $courseIds,
        string $actorId,
        string $reason,
        ?string $notes = null,
    ): array {
        $courseIds = array_values(array_unique(array_filter(
            $courseIds,
            static fn (mixed $courseId): bool => is_string($courseId) && $courseId !== '',
        )));

        if ($courseIds === []) {
            return [];
        }

        $courses = $this->catalog->coursesByIds((string) $profile->organization_id, $courseIds);

        if (count($courses) !== count($courseIds)) {
            throw BusinessRuleViolation::make(
                'staff.qualification_invalid_course',
                'staff::errors.qualification_invalid_course',
            );
        }

        return $this->transaction->run(function () use ($profile, $courseIds, $actorId, $reason, $notes): array {
            $assigned = [];

            foreach ($courseIds as $courseId) {
                /** @var TeacherCourse $qualification */
                $qualification = TeacherCourse::query()->firstOrCreate(
                    [
                        'staff_profile_id' => (string) $profile->getKey(),
                        'course_id' => $courseId,
                    ],
                    [
                        'qualified_at' => now()->utc(),
                        'qualified_by' => $actorId,
                        'notes' => $notes,
                    ],
                );

                // الاعتماد المجدد يعيد تفعيل سجل ملغى بدل إنشاء سجل جديد —
                // تاريخ الاعتماد الأصلي يبقى محفوظًا.
                if ($qualification->isRevoked()) {
                    $qualification->revoked_at = null;
                    $qualification->revoked_by = null;
                    $qualification->revocation_reason = null;

                    if ($notes !== null) {
                        $qualification->notes = $notes;
                    }

                    $qualification->save();
                }

                $assigned[] = $qualification;
            }

            $this->audit->record(
                organizationId: (string) $profile->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'staff.qualifications_assigned',
                auditableType: 'staff_profile',
                auditableId: (string) $profile->getKey(),
                oldValues: null,
                newValues: ['course_ids' => $courseIds],
                reason: $reason,
            );

            return $assigned;
        });
    }
}
