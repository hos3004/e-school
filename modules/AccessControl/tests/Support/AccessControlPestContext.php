<?php

declare(strict_types=1);

namespace Modules\AccessControl\Tests\Support;

use Modules\Identity\Domain\Models\User;
use Tests\TestCase;

/**
 * Typed fixture context populated by AccessControlRoutesTest::beforeEach().
 */
abstract class AccessControlPestContext extends TestCase
{
    public string $organizationId;

    public string $otherOrganizationId;

    public User $actor;
}
