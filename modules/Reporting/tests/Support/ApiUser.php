<?php

declare(strict_types=1);

namespace Modules\Reporting\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم اختباري بلا قاعدة بيانات — لمسارات الـ API فقط.
 *
 * لا يعتمد على أي موديول آخر: الهوية والمؤسسة تُحقنان مباشرة،
 * والصلاحيات تُضبط في الاختبار عبر Gate (لا أدوار نصّية).
 */
final class ApiUser extends Authenticatable
{
    public function __construct(
        private readonly string $identifier = '',
        private readonly string $organizationId = '',
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

    public function getAttribute($key): mixed
    {
        return $key === 'organization_id' ? $this->organizationId : parent::getAttribute($key);
    }

    public function __get($key): mixed
    {
        return $this->getAttribute($key);
    }
}
