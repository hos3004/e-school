<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Services\UndeliverableEmailDomains;

/**
 * قنوات الإرسال المعروضة في نماذج المراسلة، وسببُ تعطيل ما لا يصلح منها.
 *
 * كانت الواجهة تعرض القنوات المفعّلة فقط، فتختفي «واتساب» بلا تفسير حين لا
 * تكون مفعّلة — والمستخدم لا يعرف أهي غير مدعومة أم معطّلة أم عُطل. القناة
 * تظهر الآن دائمًا، ومعها سبب تعطيلها، تمامًا كما تفعل صفحة الإعدادات.
 *
 * والتعطيل ليس حالة عامة فقط: واتساب بلا رقم هاتف مسجَّل، أو بريد على نطاق
 * محجوز، رسالة تفشل بعد الإرسال. إظهار السبب قبل الضغط أصدق من فشل صامت.
 */
final readonly class MessagingChannelOptions
{
    public function __construct(
        private UndeliverableEmailDomains $undeliverable,
    ) {}

    /**
     * @return list<array{value: string, enabled: bool, reason: string|null}>
     */
    public function all(?User $recipient = null): array
    {
        $options = [];

        foreach ((array) config('notifications.channels', []) as $name => $settings) {
            if (!is_string($name) || !is_array($settings)) {
                continue;
            }

            $options[] = $this->option($name, (bool) ($settings['enabled'] ?? false), $recipient);
        }

        return $options;
    }

    /**
     * القناة الافتراضية: واتساب إن صلحت، وإلا أول قناة صالحة.
     *
     * @param list<array{value: string, enabled: bool, reason: string|null}> $options
     */
    public function defaultFor(array $options): string
    {
        $usable = array_values(array_filter(
            $options,
            static fn (array $option): bool => $option['enabled'],
        ));

        foreach ($usable as $option) {
            if ($option['value'] === 'whatsapp') {
                return 'whatsapp';
            }
        }

        return (string) ($usable[0]['value'] ?? 'in_app');
    }

    /**
     * @return array{value: string, enabled: bool, reason: string|null}
     */
    private function option(string $name, bool $channelEnabled, ?User $recipient): array
    {
        if (!$channelEnabled) {
            return ['value' => $name, 'enabled' => false, 'reason' => 'channel_disabled'];
        }

        if ($recipient === null) {
            return ['value' => $name, 'enabled' => true, 'reason' => null];
        }

        $reason = match ($name) {
            'whatsapp' => $this->blank($recipient->getAttribute('phone')) ? 'no_phone' : null,
            'email' => $this->undeliverable->isUndeliverable(
                is_string($recipient->getAttribute('email')) ? $recipient->getAttribute('email') : null,
            ) ? 'no_email' : null,
            default => null,
        };

        return ['value' => $name, 'enabled' => $reason === null, 'reason' => $reason];
    }

    private function blank(mixed $value): bool
    {
        return !is_string($value) || trim($value) === '';
    }
}
