<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Exceptions;

use RuntimeException;

final class ClassroomProviderException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    private function __construct(
        public readonly string $reason,
        string $translationKey,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(__($translationKey, $context), previous: $previous);
    }

    /** @param array<string, mixed> $context */
    public static function configuration(array $context = []): self
    {
        return new self('configuration', 'virtualclassroom::errors.provider_configuration', $context);
    }

    /** @param array<string, mixed> $context */
    public static function unavailable(array $context = [], ?\Throwable $previous = null): self
    {
        return new self('unavailable', 'virtualclassroom::errors.provider_unavailable', $context, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function rejected(array $context = []): self
    {
        return new self('rejected', 'virtualclassroom::errors.provider_rejected', $context);
    }

    /** @param array<string, mixed> $context */
    public static function unsupported(array $context = []): self
    {
        return new self('unsupported_capability', 'virtualclassroom::errors.unsupported_capability', $context);
    }

    public static function invalidWebhookSignature(): self
    {
        return new self('invalid_webhook_signature', 'virtualclassroom::errors.invalid_webhook_signature');
    }
}
