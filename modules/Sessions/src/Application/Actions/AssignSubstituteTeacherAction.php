<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionSubstituteAssigned;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Services\SubstituteCandidateFinder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إسناد معلم بديل لحصة.
 *
 * ليس تعديلًا على حقل المعلم. الحصة تحتفظ بالمعلم **الأصلي** والمعلم
 * **الفعلي** معًا، ويُكتب سطر دائم في session_substitutions بمن غيّر
 * ومتى ولماذا — لأن على هذا السطر يُبنى لاحقًا:
 *   - صلاحية دخول الفصل (البديل مُشرف، والأصلي لا)
 *   - المستحقات (البديل بأجره هو، وخصم حصة من الأساسي)
 *   - بروفايل المعلم (الحصص التي نفّذها كبديل)
 *
 * التجاوز الإداري (إسناد غير مؤهل أو غير متاح) مسموح بصلاحية خاصة،
 * لكنه يُسجَّل بسببه ولا يمر صامتًا.
 */
final readonly class AssignSubstituteTeacherAction
{
    public function __construct(
        private Transaction $transaction,
        private SubstituteCandidateFinder $candidates,
    ) {}

    /**
     * @param array{override_reason?: string|null} $options
     */
    public function execute(
        string $sessionId,
        string $substituteTeacherId,
        string $assignedBy,
        string $reason,
        bool $allowOverride = false,
        array $options = [],
    ): Session {
        $session = Session::query()->findOrFail($sessionId);

        $this->assertSessionAcceptsSubstitute($session);

        $originalTeacherId = (string) $session->staff_profile_id;

        if ($originalTeacherId === $substituteTeacherId) {
            throw BusinessRuleViolation::make(
                'session.substitute_same_teacher',
                'sessions::errors.substitute_same_teacher',
            );
        }

        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'session.substitute_reason_required',
                'sessions::errors.substitute_reason_required',
            );
        }

        $evaluation = $this->candidates->evaluate($sessionId, $substituteTeacherId);
        $isOverride = !$evaluation['qualified'] || !$evaluation['available'];

        if ($isOverride && !$allowOverride) {
            throw BusinessRuleViolation::make(
                'session.substitute_not_eligible',
                $evaluation['qualified']
                    ? 'sessions::errors.substitute_not_available'
                    : 'sessions::errors.substitute_not_qualified',
                [
                    'conflicts' => $evaluation['conflicts'],
                    'on_leave' => $evaluation['on_leave'],
                ],
            );
        }

        if ($isOverride && trim((string) ($options['override_reason'] ?? '')) === '') {
            throw BusinessRuleViolation::make(
                'session.override_reason_required',
                'sessions::errors.override_reason_required',
            );
        }

        $now = CarbonImmutable::now('UTC');

        $updated = $this->transaction->run(function () use (
            $session, $originalTeacherId, $substituteTeacherId, $assignedBy,
            $reason, $evaluation, $isOverride, $options, $now,
        ): Session {
            DB::table('session_substitutions')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $session->organization_id,
                'session_id' => $session->getKey(),
                'original_teacher_id' => $originalTeacherId,
                'substitute_teacher_id' => $substituteTeacherId,
                'reason' => $reason,
                'was_qualified' => $evaluation['qualified'],
                'was_available' => $evaluation['available'],
                'is_override' => $isOverride,
                'override_reason' => $isOverride ? ($options['override_reason'] ?? null) : null,
                'assigned_by' => $assignedBy,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // المعلم الفعلي يصير البديل. المعلم الأصلي يُحفظ مرة واحدة فقط:
            // عند استبدال ثانٍ يبقى الأصلي هو الأول، لا الوسيط.
            $session->staff_profile_id = $substituteTeacherId;
            $session->substitute_for_staff_id = $session->substitute_for_staff_id ?? $originalTeacherId;
            $session->save();

            return $session;
        });

        // الإشعارات (البديل · الأصلي · الطلاب) وتحديث صلاحية الفصل
        // يتمّان عبر مستمعي هذا الحدث — لا نستدعي قناة إرسال هنا.
        event(new SessionSubstituteAssigned(
            sessionId: (string) $updated->getKey(),
            originalTeacherId: $originalTeacherId,
            substituteTeacherId: $substituteTeacherId,
            reason: $reason,
            isOverride: $isOverride,
            scheduledStart: $updated->scheduled_start->toIso8601String(),
            participantIds: $this->participantIds((string) $updated->getKey()),
            actorId: $assignedBy,
        ));

        return $updated;
    }

    /**
     * الحصة الجارية أو المنتهية أو الملغاة لا تقبل استبدالًا.
     */
    private function assertSessionAcceptsSubstitute(Session $session): void
    {
        $allowed = [SessionStatus::Draft, SessionStatus::Scheduled, SessionStatus::Confirmed];

        if (!in_array($session->status, $allowed, true)) {
            throw BusinessRuleViolation::make(
                'session.substitute_not_allowed_in_status',
                'sessions::errors.substitute_not_allowed_in_status',
                ['status' => $session->status->value],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function participantIds(string $sessionId): array
    {
        return DB::table('session_participants')
            ->where('session_id', $sessionId)
            ->pluck('student_profile_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }
}
