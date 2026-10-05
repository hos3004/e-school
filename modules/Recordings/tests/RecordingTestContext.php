<?php

declare(strict_types=1);

namespace Modules\Recordings\Tests;

use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Tests\TestCase;

/** Type of the generated Pest tests using CreatesRecordingContext. */
abstract class RecordingTestContext extends TestCase
{
    use CreatesRecordingContext;
}
