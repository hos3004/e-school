<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Students\Application\Actions\EnrollExistingStudentInWaitlistAction;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class EnrollExistingStudentInWaitlistActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{string, string}
     */
    private function programAndCourse(): array
    {
        $courseId = Fixtures::courseId();
        $programId = (string) DB::table('courses')
            ->join('levels', 'levels.id', '=', 'courses.level_id')
            ->where('courses.id', $courseId)
            ->value('levels.program_id');

        return [$programId, $courseId];
    }

    public function test_places_existing_student_directly_into_waiting_assignment(): void
    {
        Event::fake([RegistrationAccepted::class]);

        $organizationId = Fixtures::organizationId();
        $userId = Fixtures::userId();
        $studentProfileId = Fixtures::studentProfileForUser($userId);
        [$programId, $courseId] = $this->programAndCourse();

        $application = app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: Fixtures::userId(),
            reason: 'طالب مسجل مسبقًا، إضافة مباشرة بطلب المدير.',
        );

        $this->assertSame(RegistrationStatus::WaitingAssignment, $application->status);
        $this->assertSame($studentProfileId, $application->student_profile_id);
        $this->assertSame($userId, $application->user_id);
        $this->assertSame($courseId, $application->preferred_course_id);

        Event::assertDispatched(
            RegistrationAccepted::class,
            fn (RegistrationAccepted $event): bool => $event->applicationId === (string) $application->id
                && $event->studentProfileId === $studentProfileId,
        );

        $this->assertDatabaseHas('audit_log', [
            'auditable_type' => 'registration_application',
            'auditable_id' => (string) $application->id,
            'action' => 'academic_status.registration_accepted',
        ]);
    }

    public function test_rejects_empty_reason(): void
    {
        $organizationId = Fixtures::organizationId();
        $userId = Fixtures::userId();
        $studentProfileId = Fixtures::studentProfileForUser($userId);
        [$programId, $courseId] = $this->programAndCourse();

        $this->expectException(BusinessRuleViolation::class);

        app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: Fixtures::userId(),
            reason: '   ',
        );
    }

    public function test_rejects_unknown_student_profile(): void
    {
        $organizationId = Fixtures::organizationId();
        [$programId, $courseId] = $this->programAndCourse();

        $this->expectException(BusinessRuleViolation::class);

        app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: (string) Str::ulid(),
            programId: $programId,
            courseId: $courseId,
            actorId: Fixtures::userId(),
            reason: 'اختبار طالب غير موجود.',
        );
    }

    public function test_rejects_suspended_account(): void
    {
        $organizationId = Fixtures::organizationId();
        $userId = Fixtures::userId();
        DB::table('users')->where('id', $userId)->update(['status' => 'suspended']);
        $studentProfileId = Fixtures::studentProfileForUser($userId);
        [$programId, $courseId] = $this->programAndCourse();

        $this->expectException(BusinessRuleViolation::class);

        app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: Fixtures::userId(),
            reason: 'اختبار حساب موقوف.',
        );
    }

    public function test_calling_twice_for_same_course_reuses_the_same_application_and_fires_event_once(): void
    {
        Event::fake([RegistrationAccepted::class]);

        $organizationId = Fixtures::organizationId();
        $userId = Fixtures::userId();
        $studentProfileId = Fixtures::studentProfileForUser($userId);
        [$programId, $courseId] = $this->programAndCourse();
        $actorId = Fixtures::userId();

        $first = app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: $actorId,
            reason: 'أول إضافة.',
        );

        $second = app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: $actorId,
            reason: 'محاولة ثانية لنفس الكورس.',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            RegistrationApplication::query()
                ->where('student_profile_id', $studentProfileId)
                ->where('preferred_course_id', $courseId)
                ->count(),
        );
        Event::assertDispatchedTimes(RegistrationAccepted::class, 1);
    }

    public function test_pending_self_service_application_for_same_course_blocks_direct_enrollment(): void
    {
        $organizationId = Fixtures::organizationId();
        $userId = Fixtures::userId();
        $studentProfileId = Fixtures::studentProfileForUser($userId);
        [$programId, $courseId] = $this->programAndCourse();

        RegistrationApplication::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'status' => RegistrationStatus::Submitted,
            'full_name' => 'Pending Student',
            'preferred_program_id' => $programId,
            'preferred_course_id' => $courseId,
            'submitted_at' => now(),
        ]);

        $this->expectException(BusinessRuleViolation::class);

        app(EnrollExistingStudentInWaitlistAction::class)->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: Fixtures::userId(),
            reason: 'محاولة تسكين مباشر رغم وجود طلب سابق قيد المراجعة.',
        );
    }
}
