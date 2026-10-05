<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Support;

use Modules\Identity\Domain\Models\User;
use Modules\Students\Application\Policies\StudentProfilePolicy;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

/**
 * Typed fixture context shared by the functional Student Pest tests.
 */
abstract class StudentsPestContext extends TestCase
{
    public User $actor;

    public User $owner;

    public User $stranger;

    public StudentProfile $student;

    public StudentProfilePolicy $policy;
}
