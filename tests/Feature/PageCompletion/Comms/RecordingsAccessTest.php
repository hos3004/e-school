<?php

declare(strict_types=1);

namespace Tests\Feature\PageCompletion\Comms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecordingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_recordings(): void
    {
        $response = $this->get('/admin/recordings');

        $response->assertRedirect();
    }
}
