<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Listeners;

use Modules\Notifications\Application\Services\NotificationRecipientSilencer;
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
        private NotificationRecipientSilencer $silencer,
    ) {}

    public function handle(DomainEvent $event): void
    {
        $payload = $event->payload();

        foreach ($this->settingsFor($event, $payload) as [$eventKey, $settings]) {
            $audiences = array_values(array_filter(
                (array) ($settings['audiences'] ?? []),
                fn (mixed $audience): bool => is_string($audience) && !$this->silencer->isAudienceSilenced($audience),
            ));
            $recipientFields = array_values(array_filter(
                (array) ($settings['recipient_fields'] ?? []),
                fn (mixed $field): bool => is_string($field) && !$this->silencer->isRecipientFieldSilenced($field),
            ));

            $recipientIds = $this->recipients->resolve(
                eventKey: $eventKey,
                audiences: $audiences,
                recipientFields: $recipientFields,
                payload: $payload,
            );

            if ($recipientIds === []) {
                if ($this->silencer->silencedRoles() !== []) {
                    // لا تحذير: غياب المستلمين هنا قرار إداري متعمّد، لا عطل.
                    continue;
                }

                logger()->warning('notifications.event_has_no_recipient_user_ids', [
                    'event_key' => $eventKey,
                    'source_event' => $event::class,
                    'event_id' => $event->eventId,
                ]);

                continue;
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
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{string, array<string, mixed>}>
     */
    private function settingsFor(DomainEvent $event, array $payload): array
    {
        /** @var array<string, array<string, mixed>> $events */
        $events = (array) config('notifications.events', []);
        $resolved = [];

        foreach ($events as $eventKey => $settings) {
            foreach ((array) ($settings['source_events'] ?? []) as $sourceEvent) {
                if (!is_string($sourceEvent) || !is_a($event, $sourceEvent)) {
                    continue;
                }

                $matches = true;

                foreach ((array) ($settings['payload_match'] ?? []) as $field => $expected) {
                    if (!is_string($field) || data_get($payload, $field) !== $expected) {
                        $matches = false;
                        break;
                    }
                }

                if ($matches) {
                    $resolved[] = [$eventKey, $settings];
                }

                break;
            }
        }

        return $resolved;
    }
}
