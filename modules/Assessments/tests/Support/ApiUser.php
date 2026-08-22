<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مستخدم اختباري بلا قاعدة بيانات — لمسارات الـ API وفحوص السياسات.
 *
 * لا يعتمد على أي موديول آخر: الصلاحيات تُضبط في الاختبار عبر Gate
 * (لا أدوار نصّية)، والمؤسسة تُحقن كسمة عادية ليقرأها مقياس الملكية.
 */
final class ApiUser extends Authenticatable
{
    public function __construct(
        string $organizationId = '',
        private readonly string $identifier = '',
    ) {
        parent::__construct();

        $this->syncOriginal();

        $this->forceFill(['organization_id' => $organizationId]);
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
