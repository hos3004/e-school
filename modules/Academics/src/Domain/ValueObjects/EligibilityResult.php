<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\ValueObjects;

final readonly class EligibilityResult
{
    /**
     * @param  list<string>  $violations
     * @param  list<string>  $blocking
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $eligible,
        public array $violations = [],
        public bool $requiresManualApproval = true,
        public array $blocking = [],
        public array $warnings = [],
    ) {}
}
