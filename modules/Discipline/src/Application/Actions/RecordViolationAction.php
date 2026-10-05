<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Discipline\Domain\Events\ViolationRecorded;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Domain\Services\EscalationLadder;
use Modules\Discipline\Domain\ValueObjects\DisciplineWindow;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل مخالفة طالب ثم تشغيل محرّك التصعيد.
 *
 * الترتيب الإلزامي: حراس ← معاملة ← نشر الأحداث بعد النجاح.
 * العدّاد لا يُخزَّن — يُحسب من violation_events داخل نافذة الطالب.
 */
final readonly class RecordViolationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data organization_id · enrollment_id ·
     *                                   student_profile_id · type · occurred_at؟
     *                                   session_id؟ — بعد تحقّق FormRequest
     */
    public function execute(array $data): ViolationEvent
    {
        $type = $data['type'] instanceof ViolationType
            ? $data['type']
            : ViolationType::from((string) $data['type']);

        if (!array_key_exists($type->value, (array) config('discipline.countable_events', []))) {
            throw BusinessRuleViolation::make(
                'discipline.unknown_violation_type',
                'discipline::errors.unknown_violation_type',
                ['type' => $type->value],
            );
        }

        $occurredAt = isset($data['occurred_at'])
            ? CarbonImmutable::parse((string) $data['occurred_at'], 'UTC')
            : CarbonImmutable::now('UTC');

        $sourceEventId = isset($data['source_event_id'])
            ? trim((string) $data['source_event_id'])
            : null;
        if ($sourceEventId === '') {
            $sourceEventId = null;
        }

        $window = DisciplineWindow::forDate($occurredAt);

        [$violation, $created] = $this->transaction->run(function () use ($data, $type, $occurredAt, $sourceEventId, $window): array {
            $attributes = [
                'organization_id' => $data['organization_id'],
                'enrollment_id' => $data['enrollment_id'],
                'student_profile_id' => $data['student_profile_id'],
                'session_id' => $data['session_id'] ?? null,
                'type' => $type,
                'occurred_at' => $occurredAt,
                'window_key' => $window->key,
                'is_countable' => $type->isCountable(),
            ];

            if ($sourceEventId !== null) {
                $violation = ViolationEvent::query()->firstOrCreate(
                    ['source_event_id' => $sourceEventId],
                    $attributes,
                );

                return [$violation, $violation->wasRecentlyCreated];
            }

            $violation = new ViolationEvent;
            $violation->fill($attributes);
            $violation->save();

            return [$violation, true];
        });

        if (!$created) {
            return $violation;
        }

        $count = $this->countInWindow($violation);

        $this->escalateIfThresholdReached($violation, $count);

        $this->events->dispatch(new ViolationRecorded(
            violationId: (string) $violation->getKey(),
            organizationId: (string) $violation->organization_id,
            enrollmentId: (string) $violation->enrollment_id,
            studentProfileId: (string) $violation->student_profile_id,
            type: $violation->type,
            windowKey: (string) $violation->window_key,
            countInWindow: $count,
            sessionId: $violation->session_id !== null ? (string) $violation->session_id : null,
        ));

        return $violation;
    }

    /**
     * عدد المخالفات القابلة للعدّ لطلبه داخل نافذته الحالية.
     */
    public function countInWindow(ViolationEvent $violation): int
    {
        $range = DisciplineWindow::rangeEndingAt($violation->occurred_at);

        return (int) ViolationEvent::query()
            ->where('enrollment_id', $violation->enrollment_id)
            ->where('occurred_at', '>=', $range['start'])
            ->where('occurred_at', '<=', $violation->occurred_at)
            ->countable()
            ->count();
    }

    private function escalateIfThresholdReached(ViolationEvent $violation, int $count): void
    {
        if (!$violation->is_countable) {
            return;
        }

        $resolved = (new EscalationLadder)->resolveForCount($count);

        if ($resolved === null) {
            return;
        }

        $alreadyApplied = DisciplineAction::query()
            ->where('enrollment_id', $violation->enrollment_id)
            ->where('window_key', $violation->window_key)
            ->where('action', $resolved['action']->value)
            ->exists();

        if ($alreadyApplied) {
            return; // الإجراء مُطبَّق سابقًا في نفس النافذة — لا تكرار.
        }

        $action = new DisciplineAction;
        $action->fill([
            'organization_id' => $violation->organization_id,
            'enrollment_id' => $violation->enrollment_id,
            'triggered_by_event_id' => $violation->getKey(),
            'action' => $resolved['action'],
            'threshold_reached' => $resolved['threshold_reached'],
            'window_key' => $violation->window_key,
            'is_automatic' => $resolved['automatic'],
            'applied_at' => CarbonImmutable::now('UTC'),
            'applied_by' => auth()->id(),
        ]);
        $action->save();

        $this->events->dispatch(new DisciplineActionApplied(
            disciplineActionId: (string) $action->getKey(),
            organizationId: (string) $action->organization_id,
            enrollmentId: (string) $action->enrollment_id,
            action: $action->action,
            thresholdReached: (int) $action->threshold_reached,
            windowKey: (string) $action->window_key,
            isAutomatic: (bool) $action->is_automatic,
        ));
    }
}
