<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Enums;

/**
 * حراس المصادقة المعروفون في المنصة.
 *
 * كل دور أو صلاحية مرتبط بحارس واحد؛ لا يُكتب الحارس نصًا خامًا
 * في كود التطبيق — يُمرَّر عبر هذا الـ enum دائمًا.
 */
enum GuardName: string
{
    case Web = 'web';

    case Api = 'api';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::Web, self::Api];
    }

    public function label(): string
    {
        return __('accesscontrol::enums.guards.'.$this->value);
    }
}
