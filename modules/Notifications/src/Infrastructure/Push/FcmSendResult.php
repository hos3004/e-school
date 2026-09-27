<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Push;

/**
 * نتيجة إرسال FCM لرمز جهاز واحد.
 */
final readonly class FcmSendResult
{
    private function __construct(
        public bool $success,
        public ?string $error,
        public bool $retryable,
        public bool $unregistered,
    ) {}

    public static function accepted(): self
    {
        return new self(true, null, false, false);
    }

    public static function failed(string $error, bool $retryable, bool $unregistered = false): self
    {
        return new self(false, $error, $retryable, $unregistered);
    }
}
