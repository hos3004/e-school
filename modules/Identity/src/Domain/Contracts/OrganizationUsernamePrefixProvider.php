<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

/**
 * منفذ Identity لقراءة بادئة اسم المستخدم دون الاعتماد على موديول آخر.
 */
interface OrganizationUsernamePrefixProvider
{
    public function forOrganization(string $organizationId): ?string;
}
