<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Discipline\Domain\Events\ViolationWaived;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * العفو عن مخالفة — قرار إداري موثَّق: مَن عفا ومتى ولماذا.
 *
 * العفو لا يحذف الحدث؛ يعيد احتساب عدّاد النافذة تلقائيًا لأنه
 * يُحسب من الأحداث غير المعفوّة في كل مرة.
 */
final readonly class WaiveViolationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data  reason إلزامي — قاعدة التدقيق
     */
    public function execute(ViolationEvent $violation, array $data): ViolationEvent
    {
        if ($violation->isWaived()) {
            throw BusinessRuleViolation::make(
                'discipline.already_waived',
                'discipline::errors.already_waived',
            );
        }

        $this->transaction->run(function () use ($violation, $data): void {
            $violation->forceFill([
                'waived_by' => auth()->id(),
                'waived_at' => CarbonImmutable::now('UTC'),
                'waiver_reason' => (string) $data['reason'],
            ])->save();
        });

        $countAfter = (int) ViolationEvent::query()
            ->where('enrollment_id', $violation->enrollment_id)
            ->where('window_key', $violation->window_key)
            ->countable()
            ->count();

        $this->events->dispatch(new ViolationWaived(
            violationId: (string) $violation->getKey(),
            organizationId: (string) $violation->organization_id,
            enrollmentId: (string) $violation->enrollment_id,
            countInWindowAfterWaiver: $countAfter,
            reason: (string) $data['reason'],
        ));

        return $violation;
    }
}
