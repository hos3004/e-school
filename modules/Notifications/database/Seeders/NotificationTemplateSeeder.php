<?php

declare(strict_types=1);

namespace Modules\Notifications\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Lang;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationTemplate;

/**
 * القوالب العامة للمرحلة الأولى؛ يمكن للمؤسسة إضافة نسخة خاصة لاحقًا.
 */
final class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ar', 'en'] as $locale) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);

            foreach ($templates as $eventKey => $template) {
                foreach ([Channel::InApp, Channel::Email, Channel::Whatsapp] as $channel) {
                    NotificationTemplate::query()->updateOrCreate(
                        [
                            'organization_id' => null,
                            'event_key' => $eventKey,
                            'channel' => $channel->value,
                            'locale' => $locale,
                        ],
                        [
                            'subject' => $template['subject'],
                            'body' => $template['body'],
                            'provider_template_name' => $channel === Channel::Whatsapp
                                ? str_replace('.', '_', $eventKey)
                                : null,
                            'parameters' => array_values($template['parameters'] ?? []),
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }
}
