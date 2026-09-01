<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Application\Services\ManualNotificationChannelResolver;
use Modules\Notifications\Application\Services\ManualNotificationRecipientResolver;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\ValueObjects\ManualNotificationDispatchResult;
use Shared\Support\BusinessRuleViolation;

/** إرسال إداري يدوي مع عزل المؤسسة والتدقيق وعدم التكرار. */
final readonly class QueueManualNotificationAction
{
    public function __construct(
        private ManualNotificationRecipientResolver $recipients,
        private ManualNotificationChannelResolver $channels,
        private QueueNotificationAction $queue,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $actorId,
        ManualRecipientType $recipientType,
        string $targetId,
        Channel $channel,
        string $subject,
        string $body,
        string $reason,
        string $requestId,
        string $locale,
    ): ManualNotificationDispatchResult {
        return $this->dispatch(
            $organizationId,
            $actorId,
            $recipientType,
            $targetId,
            $channel,
            trim($subject),
            trim($body),
            trim($reason),
            $requestId,
            $locale,
        );
    }

    private function dispatch(
        string $organizationId,
        string $actorId,
        ManualRecipientType $recipientType,
        string $targetId,
        Channel $channel,
        string $subject,
        string $body,
        string $reason,
        string $requestId,
        string $locale,
    ): ManualNotificationDispatchResult {
        if ($organizationId === '' || $actorId === '' || $subject === '' || $body === '' || $reason === '') {
            throw BusinessRuleViolation::make(
                'notifications.manual_fields_required',
                'notifications::errors.manual_fields_required',
            );
        }

        if (!Str::isUlid($requestId)) {
            throw BusinessRuleViolation::make(
                'notifications.manual_request_invalid',
                'notifications::errors.manual_request_invalid',
            );
        }

        if (!$this->channels->allows($channel)) {
            throw BusinessRuleViolation::make(
                'notifications.channel_disabled',
                'notifications::errors.channel_disabled',
                ['channel' => $channel->label()],
            );
        }

        $resolution = $this->recipients->resolve($organizationId, $recipientType, $targetId);
        $existing = NotificationOutbox::query()
            ->forOrganization($organizationId)
            ->where('event_name', 'notifications.manual')
            ->where('event_id', $requestId)
            ->where('channel', $channel->value)
            ->get();

        if ($existing->isNotEmpty()) {
            return $this->resultFromExisting($existing);
        }

        return $this->queueResolved(
            $organizationId,
            $actorId,
            $recipientType,
            $targetId,
            $channel,
            $subject,
            $body,
            $reason,
            $requestId,
            $locale,
            $resolution->userIds,
        );
    }

    /** @param list<string> $userIds */
    private function queueResolved(
        string $organizationId,
        string $actorId,
        ManualRecipientType $recipientType,
        string $targetId,
        Channel $channel,
        string $subject,
        string $body,
        string $reason,
        string $requestId,
        string $locale,
        array $userIds,
    ): ManualNotificationDispatchResult {
        $counts = ['queued' => 0, 'suppressed' => 0, 'skipped' => 0];

        foreach ($userIds as $userId) {
            $result = $this->queueOne(
                $organizationId, $actorId, $recipientType, $targetId, $channel,
                $subject, $body, $requestId, $locale, $userId,
            );
            $counts[$result]++;
        }

        return $this->recordResult(
            $organizationId, $actorId, $recipientType, $targetId, $channel,
            $reason, $requestId, count($userIds), $counts,
        );
    }

    private function queueOne(
        string $organizationId,
        string $actorId,
        ManualRecipientType $recipientType,
        string $targetId,
        Channel $channel,
        string $subject,
        string $body,
        string $requestId,
        string $locale,
        string $userId,
    ): string {
        $outbox = $this->queue->execute(
            organizationId: $organizationId,
            userId: $userId,
            category: 'system_alert',
            channel: $channel,
            eventName: 'notifications.manual',
            eventId: $requestId,
            subject: [$locale => $subject],
            body: [$locale => $body],
            payload: [
                'manual' => true,
                'recipient_type' => $recipientType->value,
                'recipient_target_id' => $targetId,
                'manual_request_id' => $requestId,
            ],
            locale: $locale,
            correlationId: $requestId,
            actorId: $actorId,
        );

        return match ($outbox?->status) {
            null => 'skipped',
            OutboxStatus::Suppressed => 'suppressed',
            default => 'queued',
        };
    }

    /**
     * @param array{queued: int, suppressed: int, skipped: int} $counts
     */
    private function recordResult(
        string $organizationId,
        string $actorId,
        ManualRecipientType $recipientType,
        string $targetId,
        Channel $channel,
        string $reason,
        string $requestId,
        int $recipientCount,
        array $counts,
    ): ManualNotificationDispatchResult {
        $this->audit->record(
            organizationId: $organizationId,
            actorId: $actorId,
            actorType: 'user',
            action: 'notifications.manual_dispatched',
            auditableType: 'notification_outbox_batch',
            auditableId: $requestId,
            oldValues: null,
            newValues: [
                'recipient_type' => $recipientType->value,
                'recipient_target_id' => $targetId,
                'recipient_count' => $recipientCount,
                'channel' => $channel->value,
                'queued_count' => $counts['queued'],
                'suppressed_count' => $counts['suppressed'],
                'skipped_count' => $counts['skipped'],
            ],
            reason: $reason,
            correlationId: $requestId,
        );

        return new ManualNotificationDispatchResult(
            recipientCount: $recipientCount,
            queuedCount: $counts['queued'],
            suppressedCount: $counts['suppressed'],
            skippedCount: $counts['skipped'],
            alreadyProcessed: false,
            statusCounts: [
                OutboxStatus::Queued->value => $counts['queued'],
                OutboxStatus::Suppressed->value => $counts['suppressed'],
            ],
        );
    }

    /** @param Collection<int, NotificationOutbox> $rows */
    private function resultFromExisting(Collection $rows): ManualNotificationDispatchResult
    {
        $statusCounts = [];

        foreach ($rows as $row) {
            $status = $row->status->value;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        return new ManualNotificationDispatchResult(
            recipientCount: $rows->pluck('user_id')->unique()->count(),
            queuedCount: (int) ($statusCounts[OutboxStatus::Queued->value] ?? 0),
            suppressedCount: (int) ($statusCounts[OutboxStatus::Suppressed->value] ?? 0),
            skippedCount: 0,
            alreadyProcessed: true,
            statusCounts: $statusCounts,
        );
    }
}
