<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Events\PayrollDeferredEntriesReleased;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحرير القيود المؤجَّلة بعد إقامة حصة التلافي.
 *
 * قرار العميل: الحصة أُجّلت → مستحق مؤجَّل حتى تُقام؛
 * حصة التلافي أُقيمت → يتحرر المستحق المؤجَّل.
 * الانتقال Deferred → Released يمرّ بـ canTransitionTo دائمًا.
 */
final readonly class ReleaseDeferredEntriesAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private SessionAdministrationQueries $sessions,
        private StaffQueries $staff,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $makeupSessionId,
        string $staffProfileId,
        string $actorId,
        string $reason,
    ): void {
        [$entries, $releasedIds] = $this->transaction->run(function () use (
            $organizationId,
            $makeupSessionId,
            $staffProfileId,
            $actorId,
            $reason,
        ): array {
            if ($this->sessions->findForOrganization($organizationId, $makeupSessionId) === null
                || $this->staff->userIdForProfile($organizationId, $staffProfileId) === null) {
                throw BusinessRuleViolation::make(
                    'payroll.deferred.none',
                    'payroll::actions.release_deferred.none',
                    ['makeup_session_id' => $makeupSessionId],
                );
            }

            /** @var Collection<int, PayrollEntry> $entries */
            $entries = PayrollEntry::query()
                ->forOrganization($organizationId)
                ->where('staff_profile_id', $staffProfileId)
                ->where('deferred_until_session_id', $makeupSessionId)
                ->where('status', PayrollEntryStatus::Deferred)
                ->lockForUpdate()
                ->get();
            if ($entries->isEmpty()) {
                throw BusinessRuleViolation::make(
                    'payroll.deferred.none',
                    'payroll::actions.release_deferred.none',
                    ['makeup_session_id' => $makeupSessionId],
                );
            }

            $ids = [];

            foreach ($entries as $entry) {
                /** @var PayrollEntryStatus $status */
                $status = $entry->status;

                if (!$status->canTransitionTo(PayrollEntryStatus::Released)) {
                    throw BusinessRuleViolation::make(
                        'payroll.deferred.invalid_transition',
                        'payroll::actions.release_deferred.invalid_transition',
                        ['entry_id' => $entry->id, 'from' => $status->value],
                    );
                }

                $entry->forceFill(['status' => PayrollEntryStatus::Released])->save();
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'payroll.entry.released',
                    auditableType: 'payroll_entries',
                    auditableId: (string) $entry->getKey(),
                    oldValues: ['status' => PayrollEntryStatus::Deferred->value],
                    newValues: ['status' => PayrollEntryStatus::Released->value],
                    reason: trim($reason),
                );
                $ids[] = (string) $entry->id;
            }

            return [$entries, $ids];
        });

        $this->events->dispatch(new PayrollDeferredEntriesReleased(
            entryIds: $releasedIds,
            organizationId: $organizationId,
            payrollPeriodId: (string) $entries[0]->payroll_period_id,
            staffProfileId: $staffProfileId,
            makeupSessionId: $makeupSessionId,
        ));
    }
}
