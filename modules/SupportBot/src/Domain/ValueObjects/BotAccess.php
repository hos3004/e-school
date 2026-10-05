<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\ValueObjects;

use Modules\SupportBot\Domain\Enums\BotAudience;

/**
 * هل يملك هذا المستخدم بوتًا أصلًا، وبأي شخصية يخاطبه.
 */
final readonly class BotAccess
{
    private function __construct(
        public bool $allowed,
        public ?BotAudience $audience,
        public ?string $denialReason,
    ) {}

    public static function granted(BotAudience $audience): self
    {
        return new self(true, $audience, null);
    }

    /**
     * السبب مفتاح داخلي للتسجيل والعرض في اللوحة، لا نص يُعرض للمستخدم.
     * ما يراه المستخدم نصّ معدّ قابل للتحرير.
     */
    public static function denied(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
