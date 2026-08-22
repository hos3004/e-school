<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

enum ContractBasis: string
{
    /** راتب شهري ثابت. */
    case Salary = 'salary';

    /** أجر لكل حصة عبر teacher_rates. */
    case PerSession = 'per_session';

    /** راتب أساسي + مستحقات حصص. */
    case Hybrid = 'hybrid';

    public function requiresRates(): bool
    {
        return in_array($this, [self::PerSession, self::Hybrid], true);
    }

    public function label(): string
    {
        return __('staff::enums.contract_basis.'.$this->value);
    }
}
