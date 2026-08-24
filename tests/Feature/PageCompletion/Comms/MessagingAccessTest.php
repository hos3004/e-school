<?php

declare(strict_types=1);

namespace Tests\Feature\PageCompletion\Comms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Models\User;
use Tests\TestCase;

final class MessagingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_conversations_api(): void
    {
        $response = $this->getJson('/api/conversations');

        $response->assertUnauthorized();
    }
}
