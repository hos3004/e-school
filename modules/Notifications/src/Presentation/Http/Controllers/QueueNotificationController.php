<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Modules\Notifications\Application\Actions\QueueNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Presentation\Http\Requests\QueueNotificationRequest;
use Modules\Notifications\Presentation\Http\Resources\NotificationOutboxResource;

/**
 * قيد رسالة في صندوق الإرسال (عملية إدارية/نظامية).
 */
final class QueueNotificationController extends Controller
{
    public function __construct(
        private readonly QueueNotificationAction $action,
    ) {}

    public function __invoke(QueueNotificationRequest $request): mixed
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $outbox = $this->action->execute(
            organizationId: (string) $data['organization_id'],
            userId: (string) $data['user_id'],
            category: (string) $data['category'],
            channel: Channel::from((string) $data['channel']),
            eventName: (string) $data['event_name'],
            eventId: (string) $data['event_id'],
            subject: (array) $data['subject'],
            body: (array) $data['body'],
            payload: isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [],
            scheduledFor: isset($data['scheduled_for'])
                ? CarbonImmutable::parse((string) $data['scheduled_for'], 'UTC')
                : null,
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
            correlationId: isset($data['correlation_id']) ? (string) $data['correlation_id'] : null,
        );

        if ($outbox === null) {
            return response()->json([
                'message' => __('notifications::messages.opted_out'),
            ], 202);
        }

        if ($outbox->status === OutboxStatus::Suppressed) {
            return response()->json([
                'message' => __('notifications::messages.already_queued'),
                'data' => new NotificationOutboxResource($outbox),
            ], 202);
        }

        return new NotificationOutboxResource($outbox);
    }
}
