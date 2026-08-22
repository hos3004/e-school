<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Contracts;

/**
 * منفذ القراءة العام الوحيد لإعدادات المؤسسة من خارج الموديول.
 */
interface OrganizationSettingQueries
{
    public function value(string $organizationId, string $key): mixed;
}
