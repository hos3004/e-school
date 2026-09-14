<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل حصة أُقيمت فعلًا ولم تُفتح من المنصة، لتصبح جاهزة للاعتماد.
 *
 * آلة الحالات لا تسمح `scheduled → completed` عمدًا: الحصة المكتملة يجب أن
 * تكون مرّت بالفصل والمراجعة. لكن حصصًا كثيرة تُدرَّس خارج المنصة أو لا يدخل
 * المعلم غرفتها منها، فتبقى `scheduled` إلى الأبد وتساوي صفرًا في كل عدّاد
 * بينما العمل تم. بلا هذا المسار كان زر «اعتماد» في شاشة الاعتماد يرمي
 * `sessions.invalid_status_transition` على كل صف من هذا النوع.
 *
 * الحل ليس ثقبًا في آلة الحالات بل المرور بمسارها المشروع:
 * `scheduled|confirmed → in_progress → awaiting_review`، كل خطوة عبر
 * `canTransitionTo` ومسجَّلة في `session_status_history` بنفس السبب المكتوب.
 * الاعتماد النهائي يبقى مسؤولية `CompleteSessionAction` وحدها، فلا يوجد
 * طريقان لإقفال حصة.
 *
 * `actual_start` و`actual_end` تأخذان **الموعد المجدول** لا وقت الضغط على
 * الزر: لا دليل لدينا على وقت أدق، وختمها بلحظة التسجيل كان سيزعم أن حصة
 * الأسبوع الماضي أُقيمت الآن.
 *
 * لا أحداث وسيطة تُطلق هنا. `SessionStarted` و`SessionEndedForReview` يستهلكهما
 * منطق حيّ (إشعارات وتقارير)، وإطلاقها لحصة انتهت من زمن كان سيُخطر أطرافها
 * بحصة تبدأ الآن.
 */
final readonly class RecordOffPlatformSessionAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(Session $session, string $actorId, string $reason): Session
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'sessions.reason_required',
                'sessions::errors.reason_required',
            );
        }

        return $this->transaction->run(function () use ($session, $actorId, $reason): Session {
            /** @var Session $locked */
            $locked = Session::query()
                ->forOrganization((string) $session->organization_id)
                ->lockForUpdate()
                ->findOrFail((string) $session->getKey());

            $from = $locked->status;

            if ($from === SessionStatus::AwaitingReview) {
                return $locked;
            }

            if (!in_array($from, [SessionStatus::Scheduled, SessionStatus::Confirmed], true)) {
                throw BusinessRuleViolation::make(
                    'sessions.not_recordable_off_platform',
                    'sessions::errors.invalid_transition',
                    ['from' => $from->label(), 'to' => SessionStatus::AwaitingReview->label()],
                );
            }

            if (CarbonImmutable::parse((string) $locked->scheduled_end)->isFuture()) {
                throw BusinessRuleViolation::make(
                    'sessions.not_ended_yet',
                    'sessions::errors.session_not_ended',
                );
            }

            $this->applyTransition(
                $locked,
                SessionStatus::InProgress,
                ['actual_start' => $locked->scheduled_start],
                $reason,
                $actorId,
            );

            $this->applyTransition(
                $locked,
                SessionStatus::AwaitingReview,
                ['actual_end' => $locked->scheduled_end],
                $reason,
                $actorId,
            );

            $this->audit->record(
                organizationId: (string) $locked->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'sessions.session_recorded_off_platform',
                auditableType: 'sessions',
                auditableId: (string) $locked->getKey(),
                oldValues: ['status' => $from->value],
                newValues: [
                    'status' => SessionStatus::AwaitingReview->value,
                    'actual_start' => (string) $locked->scheduled_start,
                    'actual_end' => (string) $locked->scheduled_end,
                ],
                reason: $reason,
            );

            return $locked->refresh();
        });
    }
}
