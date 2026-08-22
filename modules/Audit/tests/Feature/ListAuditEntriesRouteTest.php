<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Tests\Support\ApiUser;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Tests\TestCase;

/**
 * مسار GET /api/audit-entries.
 */
final class ListAuditEntriesRouteTest extends TestCase
{
    use RefreshAuditDatabase;

    private const ACTOR_ID = '01ACTOR0000000000000000000';

    public function test_lists_audit_entries_for_authorized_viewer(): void
    {
        Gate::after(fn (): bool => true);

        $org = '01ORGLIST00000000000000000';

        AuditLog::factory()->create([
            'organization_id' => $org,
            'action' => 'logged_in',
            'created_at' => now()->utc()->subMinutes(5),
        ]);
        AuditLog::factory()->create([
            'organization_id' => $org,
            'action' => 'updated',
            'created_at' => now()->utc(),
        ]);
        AuditLog::factory()->create(['organization_id' => '01ORGELSE00000000000000000']);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->getJson('/api/audit-entries?organization_id='.$org)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'updated');
    }

    public function test_filters_entries_by_action(): void
    {
        Gate::after(fn (): bool => true);

        AuditLog::factory()->count(3)->create(['action' => 'logged_in']);
        AuditLog::factory()->create(['action' => 'created']);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->getJson('/api/audit-entries?action=logged_in')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_forbids_listing_when_viewer_lacks_audit_view_any(): void
    {
        Gate::define('audit.view_any', fn (): bool => false);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->getJson('/api/audit-entries')
            ->assertForbidden();
    }

    public function test_requires_authentication_for_the_list_route(): void
    {
        $this->getJson('/api/audit-entries')->assertUnauthorized();
    }
}
