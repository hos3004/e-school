<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Support;

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
        private readonly string $identifier,
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
