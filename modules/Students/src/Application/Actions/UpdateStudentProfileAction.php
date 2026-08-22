<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Events\StudentProfileUpdated;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحديث بيانات ملف طالب قائم — لا يُنشر حدث إن لم يتغيّر شيء.
 */
final readonly class UpdateStudentProfileAction
{
    /** @var list<string> الأعمدة المسموح تعديلها عبر هذا الإجراء */
    private const EDITABLE = [
        'date_of_birth',
        'gender',
        'nationality',
        'country',
        'city',
        'preferred_language',
        'notes',
    ];

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $changes
     */
    public function execute(StudentProfile $student, array $changes): StudentProfile
    {
        if ($student->trashed()) {
            throw BusinessRuleViolation::make(
                'students.archived_read_only',
                'students::errors.archived_read_only',
                ['student_id' => $student->getKey()],
            );
        }

        $changes = collect($changes)
            ->only(self::EDITABLE)
            ->filter(fn (mixed $value, string $key): bool => $value !== $student->getOriginal($key))
            ->all();

        if ($changes === []) {
            return $student;
        }

        $this->transaction->run(function () use ($student, $changes): void {
            $student->fill($changes);
            $student->save();
        });

        $this->events->dispatch(new StudentProfileUpdated(
            studentId: (string) $student->getKey(),
            organizationId: (string) $student->organization_id,
            changes: $this->toPrimitives($changes),
        ));

        return $student;
    }

    /**
     * الحمولة قيَم بدائية فقط — الـ enums تتحول إلى قيمتها النصية.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function toPrimitives(array $changes): array
    {
        return collect($changes)->map(
            static fn (mixed $value): mixed => $value instanceof StudentGender ? $value->value : $value,
        )->all();
    }
}
