<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Listeners;

use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Shared\Domain\DomainEvent;

/**
 * يحوّل أحداث المرحلة الأولى إلى قيود Outbox من دون استيراد نماذج الموديولات.
 *
 * الحدث المالك يرسل معرّفات المستخدمين النهائية في payload؛ معرّفات الملفات
 * (student_profile_id مثلًا) لا تُحوَّل هنا كي لا يعرف Notifications جداول غيره.
 */
final readonly class QueueConfiguredDomainEventNotification
{
    public function __construct(
        private NotificationDispatcher $notifications,
        private DomainEventRecipientResolver $recipients,
    ) {}

    public function handle(DomainEvent $event): void
    {
        $resolved = $this->settingsFor($event);

        if ($resolved === null) {
            return;
        }

        [$eventKey, $settings] = $resolved;
        $payload = $event->payload();
        $recipientIds = $this->recipients->resolve(
            eventKey: $eventKey,
            audiences: array_values((array) ($settings['audiences'] ?? [])),
            recipientFields: array_values((array) ($settings['recipient_fields'] ?? [])),
            payload: $payload,
        );

        if ($recipientIds === []) {
            logger()->warning('notifications.event_has_no_recipient_user_ids', [
                'event_key' => $eventKey,
                'source_event' => $event::class,
                'event_id' => $event->eventId,
            ]);

            return;
        }

        $this->notifications->dispatch(
            category: (string) $settings['category'],
            recipientIds: $recipientIds,
            payload: [
                ...$payload,
                'event_name' => $eventKey,
                'event_id' => $event->eventId,
            ],
            correlationId: $event->correlationId,
        );
    }

    /**
     * @return array{string, array<string, mixed>}|null
     */
    private function settingsFor(DomainEvent $event): ?array
    {
        /** @var array<string, array<string, mixed>> $events */
        $events = (array) config('notifications.events', []);

        foreach ($events as $eventKey => $settings) {
            foreach ((array) ($settings['source_events'] ?? []) as $sourceEvent) {
                if (is_string($sourceEvent) && is_a($event, $sourceEvent)) {
                    return [$eventKey, $settings];
                }
            }
        }

        return null;
    }
}
