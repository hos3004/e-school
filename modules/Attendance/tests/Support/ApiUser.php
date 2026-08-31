<?php

declare(strict_types=1);

namespace Modules\Attendance\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم اختباري بلا قاعدة بيانات — لمسارات الـ API فقط.
 *
 * لا يعتمد على أي موديول آخر: الهوية تُحقن بالمعرّف مباشرة،
 * والصلاحيات تُضبط في الاختبار عبر Gate (لا أدوار نصّية).
 */
final class ApiUser extends Authenticatable
{
    public function __construct(
        private readonly string $identifier = '',
        ?string $organizationId = null,
    ) {
        parent::__construct();
        $this->setAttribute('organization_id', $organizationId);
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
