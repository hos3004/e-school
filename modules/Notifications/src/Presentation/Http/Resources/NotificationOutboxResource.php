<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Lang;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Http\Support\NotificationDeepLinkResolver;

/**
 * تمثيل رسالة صندوق الإرسال في الـ API.
 *
 * @mixin NotificationOutbox
 */
final class NotificationOutboxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = (string) ($request->user()?->getAttribute('locale') ?: $this->locale);

        /** @var NotificationOutbox $outbox */
        $outbox = $this->resource;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'category' => $this->category,
            'category_label' => $this->categoryLabel((string) $this->category, $locale),
            'channel' => $this->channel,
            'locale' => $this->locale,
            'event_name' => $this->event_name,
            'event_id' => $this->event_id,
            'correlation_id' => $this->correlation_id,
            'subject' => $this->localized($this->subject, $locale),
            'body' => $this->localized($this->body, $locale),
            'target_url' => app(NotificationDeepLinkResolver::class)->resolve($request, $outbox),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'last_error_retryable' => $this->last_error_retryable,
            'external_message_id' => $this->external_message_id,
            'provider_status' => $this->provider_status,
            'failure_reason' => $this->failure_reason,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed>|null $content */
    private function localized(?array $content, string $locale): string
    {
        if ($content === null || $content === []) {
            return '';
        }

        $value = $content[$locale]
            ?? $content[$this->locale]
            ?? $content[(string) config('notifications.localization.fallback_locale', 'ar')]
            ?? $content['ar']
            ?? $content['en']
            ?? reset($content);

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    private function categoryLabel(string $category, string $locale): string
    {
        $key = 'notifications::categories.'.$category;

        return Lang::has($key, $locale) ? (string) __($key, locale: $locale) : $category;
    }
}
