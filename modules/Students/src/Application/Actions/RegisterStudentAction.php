<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Students\Domain\Events\StudentRegistered;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل طالب جديد في مؤسسة: إنشاء ملف الطالب ونشر حدث التسجيل.
 */
final readonly class RegisterStudentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات الملف بعد تحقّق FormRequest
     */
    public function execute(array $data): StudentProfile
    {
        $this->assertNotRegistered((string) $data['user_id']);
        $this->assertCodeAvailable((string) $data['student_code']);

        $student = $this->transaction->run(function () use ($data): StudentProfile {
            $profile = new StudentProfile;
            $profile->fill($data);
            $profile->save();

            return $profile;
        });

        $this->events->dispatch(new StudentRegistered(
            studentId: $student->getKey(),
            userId: (string) $student->user_id,
            organizationId: (string) $student->organization_id,
            studentCode: (string) $student->student_code,
        ));

        return $student;
    }

    private function assertNotRegistered(string $userId): void
    {
        $exists = StudentProfile::query()
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'students.already_registered',
                'students::errors.already_registered',
                ['user_id' => $userId],
            );
        }
    }

    private function assertCodeAvailable(string $code): void
    {
        $exists = StudentProfile::query()
            ->withTrashed()
            ->where('student_code', $code)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'students.code_taken',
                'students::errors.code_taken',
                ['student_code' => $code],
            );
        }
    }
}
