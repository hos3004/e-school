<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\ValueObjects;

/**
 * النتيجة الموحدة لكل بوابات القنوات.
 *
 * isRetryable() هو المصدر الوحيد لقرار إعادة المحاولة في المستهلك.
 */
final readonly class GatewayResult
{
    /**
     * @param array<string, mixed> $providerResponse
     */
    private function __construct(
        private bool $accepted,
        private bool $retryable,
        private ?string $error,
        private array $providerResponse,
    ) {}

    /**
     * @param array<string, mixed> $providerResponse
     */
    public static function accepted(array $providerResponse = []): self
    {
        return new self(true, false, null, $providerResponse);
    }

    /**
     * @param array<string, mixed> $providerResponse
     */
    public static function rejected(
        string $error,
        bool $retryable,
        array $providerResponse = [],
    ): self {
        return new self(false, $retryable, $error, $providerResponse);
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function isRetryable(): bool
    {
        return !$this->accepted && $this->retryable;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerResponse(): array
    {
        return $this->providerResponse;
    }
}
