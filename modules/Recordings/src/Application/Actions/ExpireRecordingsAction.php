<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Recordings\Application\Concerns\TransitionsRecordingStatus;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingExpired;
use Modules\Recordings\Domain\Models\Recording;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * معالجة التسجيلات التي تجاوزت مدة الاحتفاظ.
 *
 * ماذا يحدث بعد الانتهاء يحدده config('recordings.on_expiry'):
 *  - archive_then_delete: يُؤرشف باردًا أولًا ثم يُعلن منتهيًا في مسح لاحق.
 *  - delete: يُعلن منتهيًا فورًا.
 */
final readonly class ExpireRecordingsAction
{
    use TransitionsRecordingStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private ArchiveRecordingAction $archive,
    ) {}

    /**
     * @return list<string> معرّفات التسجيلات التي عولجت
     */
    public function execute(?CarbonImmutable $at = null, ?int $limit = null): array
    {
        $at ??= CarbonImmutable::now('UTC');

        $query = Recording::query()
            ->pastRetention($at)
            ->orderBy('expires_at')
            ->select(['id']);

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var list<string> $ids */
        $ids = $query->pluck('id')->all();

        $processed = [];

        foreach ($ids as $id) {
            $recording = Recording::query()->findOrFail($id);
            $this->expireOne($recording, $at);
            $processed[] = $id;
        }

        return $processed;
    }

    private function expireOne(Recording $recording, CarbonImmutable $at): void
    {
        $onExpiry = (string) config('recordings.on_expiry');

        if ($onExpiry === 'archive_then_delete' && $recording->status === RecordingStatus::Ready) {
            try {
                $this->archive->execute($recording);
            } catch (BusinessRuleViolation) {
                // لا سائق أرشفة مضبوط — نُعلن الانتهاء مباشرة.
                $this->markExpired($recording, $at);
            }

            return;
        }

        $this->markExpired($recording, $at);
    }

    private function markExpired(Recording $recording, CarbonImmutable $at): void
    {
        $this->transaction->run(function () use ($recording): bool {
            $this->applyTransition($recording, RecordingStatus::Expired);

            return true;
        });

        $this->events->dispatch(new RecordingExpired(
            recordingId: $recording->id,
            organizationId: $recording->organization_id,
            sessionId: $recording->session_id,
            expiresAt: $at->toIso8601String(),
        ));
    }
}
