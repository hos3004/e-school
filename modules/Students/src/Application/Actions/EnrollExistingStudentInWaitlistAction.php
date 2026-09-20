<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Application\Services\RegistrationNotificationDetails;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * يضع طالبًا له حساب وملف بالفعل مباشرة في قائمة انتظار التسكين لكورس، دون
 * إعادة رحلة التسجيل الذاتي (نموذج عام + قبول). يُستخدم فقط حين يبدأ
 * الإداري التسكين بنفسه لطالب مسجَّل مسبقًا؛ التسكين الفعلي في مجموعة يبقى
 * عبر AssignStudentToGroupAction/BulkAssignStudentsToGroupAction كالمعتاد.
 */
final readonly class EnrollExistingStudentInWaitlistAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
        private UserQueryService $users,
        private RegistrationNotificationDetails $details,
    ) {}

    public function execute(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        string $courseId,
        string $actorId,
        string $reason,
    ): RegistrationApplication {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'registration.acceptance_reason_required',
                'students::errors.registration_acceptance_reason_required',
            );
        }

        /** @var array{0: RegistrationApplication, 1: bool, 2: string|null} $result */
        $result = $this->transaction->run(function () use (
            $organizationId,
            $studentProfileId,
            $programId,
            $courseId,
            $actorId,
            $reason,
        ): array {
            /** @var StudentProfile|null $profile */
            $profile = StudentProfile::query()
                ->where('organization_id', $organizationId)
                ->whereKey($studentProfileId)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw BusinessRuleViolation::make(
                    'registration.student_profile_not_found',
                    'students::errors.registration_student_profile_not_found',
                );
            }

            $user = $this->users->findSummary($profile->user_id);

            if ($user === null || !$user->isActive()) {
                throw BusinessRuleViolation::make(
                    'registration.user_account_required',
                    'students::errors.registration_user_account_required',
                );
            }

            // قبل التسكين، لا يحمل الطلب student_profile_id (قيد قاعدة البيانات
            // يمنعه)؛ الربط الموثوق بالطالب قبل ذلك هو حساب المستخدم نفسه.
            /** @var RegistrationApplication|null $existing */
            $existing = RegistrationApplication::query()
                ->forOrganization($organizationId)
                ->where('user_id', $profile->user_id)
                ->where('preferred_course_id', $courseId)
                ->whereNot('status', RegistrationStatus::Rejected->value)
                ->first();

            if ($existing !== null && $existing->status->isClearedForAssignment()) {
                return [$existing, false, null];
            }

            if ($existing !== null) {
                // طلب سابق لنفس الطالب والكورس ما زال قيد المراجعة عبر
                // المسار الذاتي — لا نُنشئ نسخة موازية منه.
                throw BusinessRuleViolation::make(
                    'registration.duplicate_blocked',
                    'students::errors.registration_duplicate_blocked',
                );
            }

            $application = new RegistrationApplication;
            $application->organization_id = $organizationId;
            $application->user_id = $profile->user_id;
            $application->student_profile_id = $profile->id;
            $application->full_name = $user->name;
            $application->date_of_birth = $profile->date_of_birth;
            $application->gender = $profile->gender;
            $application->country_id = $profile->country_id;
            $application->region_id = $profile->region_id;
            $application->preferred_program_id = $programId;
            $application->preferred_course_id = $courseId;
            $application->status = RegistrationStatus::WaitingAssignment;
            $application->reviewed_by = $actorId;
            $application->reviewed_at = now()->utc();
            $application->decision_reason = $reason;
            $application->save();

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'academic_status.registration_accepted',
                auditableType: 'registration_application',
                auditableId: (string) $application->getKey(),
                oldValues: ['status' => null, 'student_profile_id' => null],
                newValues: [
                    'status' => RegistrationStatus::WaitingAssignment->value,
                    'student_profile_id' => (string) $profile->getKey(),
                    'existing_profile' => true,
                ],
                reason: $reason,
            );

            return [$application, true, (string) $profile->student_code];
        });

        [$application, $created, $studentCode] = $result;

        if ($created) {
            $this->events->dispatch(new RegistrationAccepted(
                applicationId: (string) $application->id,
                organizationId: (string) $application->organization_id,
                studentProfileId: (string) $application->student_profile_id,
                studentUserId: (string) $application->user_id,
                studentName: $application->full_name,
                courseName: $this->details->courseName(
                    (string) $application->organization_id,
                    $application->preferred_course_id,
                ),
                studentCode: (string) $studentCode,
                actorId: $actorId,
            ));
        }

        return $application;
    }
}
