<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** هوية اختبارية مستقلة عن نموذج Identity لاختبار حدود الـAPI والسياسات. */
final class ApiUser extends Authenticatable
{
    public function __construct(
        private readonly string $identifier = '',
        public readonly string $organization_id = '',
    ) {
        parent::__construct();
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->identifier;
    }
}
