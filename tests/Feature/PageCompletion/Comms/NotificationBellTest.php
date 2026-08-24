<?php

declare(strict_types=1);

namespace Tests\Feature\PageCompletion\Comms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Models\User;
use Tests\TestCase;

final class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_unread_notification_count(): void
    {
        $user = User::factory()->create([
            'organization_id' => '01HKEYORGANIZATION00000000',
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications/unread-count');

        $response->assertOk();
    }

    public function test_authenticated_user_can_list_notifications(): void
    {
        $user = User::factory()->create([
            'organization_id' => '01HKEYORGANIZATION00000000',
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk();
    }
}
