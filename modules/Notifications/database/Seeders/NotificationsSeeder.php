<?php

declare(strict_types=1);

namespace Modules\Notifications\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Testing\Fixtures;

/**
 * بيانات تجريبية لموديول Notifications: تفضيلات ورسائل بحالات متنوعة.
 */
final class NotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NotificationTemplateSeeder::class);

        $organizationId = Fixtures::organizationId();
        $categories = ['scheduling', 'discipline', 'billing', 'system'];

        foreach (range(1, 5) as $userIndex) {
            $userId = Fixtures::userId();

            foreach ($categories as $category) {
                foreach ([Channel::InApp, Channel::Email] as $channel) {
                    NotificationPreference::query()->create([
                        'organization_id' => $organizationId,
                        'user_id' => $userId,
                        'category' => $category,
                        'channel' => $channel->value,
                        'enabled' => !($category === 'billing' && $userIndex % 2 === 0),
                        'updated_at' => CarbonImmutable::now('UTC'),
                    ]);
                }
            }

            foreach (range(1, 4) as $notificationIndex) {
                $eventId = (string) Str::ulid();
                $channel = $notificationIndex % 2 === 0 ? Channel::Email : Channel::InApp;
                $category = $categories[$notificationIndex % count($categories)];
                $status = OutboxStatus::from(
                    ['queued', 'sent', 'failed', 'queued'][$notificationIndex - 1],
                );

                NotificationOutbox::query()->create([
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'category' => $category,
                    'channel' => $channel->value,
                    'locale' => 'ar',
                    'event_name' => 'notifications.queued',
                    'event_id' => $eventId,
                    'correlation_id' => (string) Str::ulid(),
                    'subject' => [
                        'ar' => 'إشعار: '.$this->categoryLabel($category),
                        'en' => 'Notification: '.$category,
                    ],
                    'body' => [
                        'ar' => 'هذا إشعار تجريبي في فئة '.$category.' عبر قناة '.$channel->value.'.',
                        'en' => 'Sample notification in category "'.$category.'" via channel "'.$channel->value.'".',
                    ],
                    'payload' => [],
                    'idempotency_key' => implode(':', [$eventId, $category, $channel->value]),
                    'scheduled_for' => CarbonImmutable::now('UTC')->subMinutes($notificationIndex),
                    'status' => $status,
                    'attempts' => match ($status) {
                        OutboxStatus::Sent => 1,
                        OutboxStatus::Failed => max(1, (int) config('notifications.dispatch.max_attempts', 3)),
                        default => 0,
                    },
                    'sent_at' => $status === OutboxStatus::Sent ? CarbonImmutable::now('UTC') : null,
                    'last_error' => $status === OutboxStatus::Failed
                        ? __('notifications::messages.seed_provider_error')
                        : null,
                ]);
            }
        }
    }

    private function categoryLabel(string $category): string
    {
        return __('notifications::categories.'.$category);
    }
}
