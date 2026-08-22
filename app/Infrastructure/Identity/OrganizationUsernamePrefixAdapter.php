<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use Modules\Identity\Domain\Contracts\OrganizationUsernamePrefixProvider;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;

/** طبقة تركيب تربط منفذ Identity بعقد الإعدادات العام للمؤسسات. */
final readonly class OrganizationUsernamePrefixAdapter implements OrganizationUsernamePrefixProvider
{
    public function __construct(
        private OrganizationSettingQueries $organizationSettings,
    ) {}

    public function forOrganization(string $organizationId): ?string
    {
        $value = $this->organizationSettings->value(
            $organizationId,
            (string) config('admission.username.organization_setting_key'),
        );

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
