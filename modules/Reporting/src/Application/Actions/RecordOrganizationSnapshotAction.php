<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Reporting\Domain\Enums\SnapshotType;
use Modules\Reporting\Domain\Events\OrganizationSnapshotRecorded;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;
use Shared\Support\Transaction;

/**
 * تسجيل لقطة تنظيمية لليوم — upsert idempotent على مفتاح
 * (مؤسسة، تاريخ، نوع فترة).
 *
 * الترتيب: حراس ← معاملة ← نشر الحدث بعد النجاح.
 */
final readonly class RecordOrganizationSnapshotAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data organization_id · snapshot_date ·
     *                                   students_active · students_frozen ·
     *                                   teachers_active · sessions_held ·
     *                                   sessions_cancelled · attendance_rate_bp؟
     */
    public function execute(array $data): OrganizationSnapshot
    {
        $snapshotDate = $data['snapshot_date'] instanceof \DateTimeInterface
            ? CarbonImmutable::instance($data['snapshot_date'])
            : CarbonImmutable::parse((string) $data['snapshot_date'], 'UTC');

        [$snapshot, $event] = $this->transaction->run(function () use ($data, $snapshotDate): array {
            /** @var OrganizationSnapshot|null $snapshot */
            $snapshot = OrganizationSnapshot::query()
                ->forOrganization((string) $data['organization_id'])
                ->whereDate('snapshot_date', $snapshotDate->toDateString())
                ->where('period_type', SnapshotType::Daily)
                ->first();

            if ($snapshot === null) {
                $snapshot = new OrganizationSnapshot;
                $snapshot->fill([
                    'organization_id' => (string) $data['organization_id'],
                    'snapshot_date' => $snapshotDate,
                    'period_type' => SnapshotType::Daily,
                ]);
            }

            $snapshot->fill([
                'students_active' => max(0, (int) ($data['students_active'] ?? $snapshot->students_active ?? 0)),
                'students_frozen' => max(0, (int) ($data['students_frozen'] ?? $snapshot->students_frozen ?? 0)),
                'teachers_active' => max(0, (int) ($data['teachers_active'] ?? $snapshot->teachers_active ?? 0)),
                'sessions_held' => max(0, (int) ($data['sessions_held'] ?? $snapshot->sessions_held ?? 0)),
                'sessions_cancelled' => max(0, (int) ($data['sessions_cancelled'] ?? $snapshot->sessions_cancelled ?? 0)),
                'attendance_rate_bp' => max(0, min(10000, (int) ($data['attendance_rate_bp'] ?? $snapshot->attendance_rate_bp ?? 0))),
            ]);
            $snapshot->save();

            return [$snapshot, new OrganizationSnapshotRecorded(
                snapshotId: (string) $snapshot->getKey(),
                organizationId: (string) $snapshot->organization_id,
                snapshotDate: $snapshot->snapshot_date->toDateString(),
                sessionsHeld: (int) $snapshot->sessions_held,
                attendanceRateBp: (int) $snapshot->attendance_rate_bp,
            )];
        });

        $this->events->dispatch($event);

        return $snapshot;
    }
}
