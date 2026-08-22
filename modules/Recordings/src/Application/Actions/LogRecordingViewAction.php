<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingViewed;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingView;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل مشاهدة أو تنزيل — كل وصول يُدوَّن وفق config('recordings.privacy.log_every_view').
 *
 * القواعد:
 *  - لا وصول إلا للتسجيلات بحالة Ready وغير المحذوفة.
 *  - التنزيل ممنوع ما لم يسمح به config('recordings.access.allow_download').
 */
final readonly class LogRecordingViewAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        Recording $recording,
        string $userId,
        string $action = 'view',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $actorId = null,
    ): RecordingView {
        if ($recording->trashed() || $recording->status !== RecordingStatus::Ready) {
            throw BusinessRuleViolation::make(
                'recordings.not_watchable',
                'recordings::errors.not_watchable',
                ['status' => $recording->status->label()],
            );
        }

        if ($action === 'download' && !config('recordings.access.allow_download')) {
            throw BusinessRuleViolation::make(
                'recordings.download_not_allowed',
                'recordings::errors.download_not_allowed',
            );
        }

        $viewedAt = CarbonImmutable::now('UTC');

        /** @var RecordingView $view */
        $view = $this->transaction->run(fn (): RecordingView => RecordingView::query()->create([
            'recording_id' => $recording->id,
            'user_id' => $userId,
            'viewed_at' => $viewedAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'action' => $action,
        ]));

        $this->events->dispatch(new RecordingViewed(
            recordingId: $recording->id,
            organizationId: $recording->organization_id,
            sessionId: $recording->session_id,
            userId: $userId,
            action: $action,
            actorId: $actorId ?? $userId,
        ));

        return $view;
    }
}
