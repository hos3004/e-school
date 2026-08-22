<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم اختباري بلا قاعدة بيانات — لمسارات الـ API فقط.
 *
 * الهوية تُحقن بالمعرّف مباشرة، والصلاحيات تُضبط في الاختبار عبر Gate
 * (لا أدوار نصّية).
 */
final class ApiUser extends Authenticatable
{
    public function __construct(
        private readonly string $identifier = '',
        public readonly ?string $organizationId = null,
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

    public function __get($key): mixed
    {
        if ($key === 'organization_id') {
            return $this->organizationId;
        }

        return parent::__get($key);
    }

    public function __isset($key): bool
    {
        if ($key === 'organization_id') {
            return $this->organizationId !== null;
        }

        return parent::__isset($key);
    }
}
