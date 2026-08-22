<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Notifications\Domain\Models\NotificationTemplate;
use Shared\Support\BusinessRuleViolation;

/**
 * يحل قالب القناة في لغة المستلم ثم العربية ثم الإنجليزية.
 *
 * لا يُخزّن نصًا ظاهرًا في الكود؛ النص يأتي كاملًا من جدول القوالب،
 * وتُحقن قيم الحدث فقط بعد التحقق من وجود كل بارامتر معلن.
 */
final class TemplateRenderer
{
    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     subject: string|null,
     *     body: string,
     *     locale: string,
     *     provider_template_name: string|null,
     *     template_parameters: list<string>
     * }
     */
    public function render(
        string $eventKey,
        string $channel,
        string $locale,
        ?string $organizationId,
        array $payload,
    ): array {
        $template = $this->resolve($eventKey, $channel, $locale, $organizationId);

        if ($template === null) {
            throw BusinessRuleViolation::make(
                'notifications.template_missing',
                'notifications::errors.template_missing',
                [
                    'event' => $eventKey,
                    'channel' => $channel,
                    'locale' => $locale,
                ],
            );
        }

        $values = [];

        foreach ($template->parameters as $parameter) {
            $value = data_get($payload, $parameter);

            if (!is_scalar($value) && !$value instanceof \Stringable) {
                throw BusinessRuleViolation::make(
                    'notifications.template_parameter_missing',
                    'notifications::errors.template_parameter_missing',
                    ['parameter' => $parameter, 'event' => $eventKey],
                );
            }

            $values[$parameter] = (string) $value;
        }

        return [
            'subject' => $template->subject === null
                ? null
                : $this->replace($template->subject, $values),
            'body' => $this->replace($template->body, $values),
            'locale' => $template->locale,
            'provider_template_name' => $template->provider_template_name,
            'template_parameters' => array_values($values),
        ];
    }

    /**
     * القالب اختياري للرسائل القديمة التي تحمل subject/body جاهزين.
     *
     * @param array<string, mixed> $payload
     * @return array{
     *     subject: string|null,
     *     body: string,
     *     locale: string,
     *     provider_template_name: string|null,
     *     template_parameters: list<string>
     * }|null
     */
    public function renderIfAvailable(
        string $eventKey,
        string $channel,
        string $locale,
        ?string $organizationId,
        array $payload,
    ): ?array {
        if ($this->resolve($eventKey, $channel, $locale, $organizationId) === null) {
            return null;
        }

        return $this->render($eventKey, $channel, $locale, $organizationId, $payload);
    }

    private function resolve(
        string $eventKey,
        string $channel,
        string $locale,
        ?string $organizationId,
    ): ?NotificationTemplate {
        $fallbacks = array_values(array_unique(array_filter([
            $locale,
            (string) config('notifications.localization.fallback_locale', 'ar'),
            'ar',
            'en',
        ])));

        foreach ($fallbacks as $candidate) {
            $query = NotificationTemplate::query()
                ->active()
                ->where('event_key', $eventKey)
                ->where('channel', $channel)
                ->where('locale', $candidate);

            if ($organizationId !== null) {
                $specific = (clone $query)
                    ->where('organization_id', $organizationId)
                    ->first();

                if ($specific !== null) {
                    return $specific;
                }
            }

            $global = $query->whereNull('organization_id')->first();

            if ($global !== null) {
                return $global;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $values
     */
    private function replace(string $template, array $values): string
    {
        $replacements = [];

        foreach ($values as $name => $value) {
            $replacements['{{'.$name.'}}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
