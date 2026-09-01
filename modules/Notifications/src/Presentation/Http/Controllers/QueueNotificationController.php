<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
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
        private readonly UserAccountDirectory $accounts,
    ) {}

    public function __invoke(QueueNotificationRequest $request): mixed
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $organizationId = $request->user()?->getAttribute('organization_id');
        $actorId = $request->user()?->getAuthIdentifier();

        abort_unless(is_string($organizationId) && $organizationId !== '', 403);
        abort_unless(is_string($actorId) && $actorId !== '', 403);

        // لا نثق بمعرّف المستلم: يجب أن يعيده دليل Identity داخل مؤسسة المنفّذ.
        abort_if($this->accounts->find($organizationId, (string) $data['user_id']) === null, 404);

        $outbox = $this->action->execute(
            organizationId: $organizationId,
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
            actorId: $actorId,
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
