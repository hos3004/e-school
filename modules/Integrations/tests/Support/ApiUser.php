<?php

declare(strict_types=1);

namespace Modules\Integrations\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم اختباري بلا قاعدة بيانات — لمسارات الـ API واختبارات السياسات.
 *
 * لا يعتمد على أي موديول آخر: الهوية والمؤسسة يُحقنان مباشرة،
 * والصلاحيات تُضبط في الاختبار عبر Gate (لا أدوار نصّية).
 */
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
