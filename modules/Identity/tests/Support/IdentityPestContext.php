<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Support;

use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Concerns\UsesRealAccessControl;
use Tests\TestCase;

/**
 * Static-analysis context matching the traits bound to Identity Pest closures.
 */
abstract class IdentityPestContext extends TestCase
{
    use CreatesTestOrganization;
    use UsesRealAccessControl;

    public FakePhonePasswordResetOtpDelivery $phoneDelivery;
}
