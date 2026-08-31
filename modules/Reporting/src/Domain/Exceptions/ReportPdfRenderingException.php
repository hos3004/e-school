<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class ReportPdfRenderingException extends RuntimeException
{
    /** @param array<string, int|string|float|bool|null> $context */
    private function __construct(
        public readonly string $reason,
        string $translationKey,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(__($translationKey), previous: $previous);
    }

    /** @param array<string, int|string|float|bool|null> $context */
    public static function invalidConfiguration(array $context = []): self
    {
        return new self('invalid_configuration', 'reporting::pdf.errors.invalid_configuration', $context);
    }

    /** @param array<string, int|string|float|bool|null> $context */
    public static function temporaryDirectoryUnavailable(array $context = [], ?Throwable $previous = null): self
    {
        return new self('temporary_directory_unavailable', 'reporting::pdf.errors.temporary_directory_unavailable', $context, $previous);
    }

    /** @param array<string, int|string|float|bool|null> $context */
    public static function renderingFailed(array $context = [], ?Throwable $previous = null): self
    {
        return new self('rendering_failed', 'reporting::pdf.errors.rendering_failed', $context, $previous);
    }

    /** @param array<string, int|string|float|bool|null> $context */
    public static function outputInvalid(array $context = []): self
    {
        return new self('output_invalid', 'reporting::pdf.errors.output_invalid', $context);
    }
}
