<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Events\AuditEntryRecorded;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Tests\Support\ApiUser;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Tests\TestCase;

/**
 * مسار POST /api/audit-entries.
 */
final class StoreAuditEntryRouteTest extends TestCase
{
    use RefreshAuditDatabase;

    private const ACTOR_ID = '01ACTOR0000000000000000000';

    public function test_stores_an_audit_entry_and_returns_201(): void
    {
        Event::fake([AuditEntryRecorded::class]);
        Gate::after(fn (): bool => true);

        $response = $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/audit-entries', [
                'organization_id' => '01ORGTEST00000000000000000',
                'action' => 'session.completed',
                'auditable_type' => 'Modules\Sessions\Domain\Models\Session',
                'auditable_id' => '01SESSID000000000000000000',
                'old_values' => ['status' => 'awaiting_review'],
                'new_values' => ['status' => 'completed'],
                'reason' => 'اعتماد بعد المراجعة',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.action', 'session.completed')
            ->assertJsonPath('data.actor_id', self::ACTOR_ID);

        self::assertTrue(AuditLog::query()->where('action', 'session.completed')->exists());

        Event::assertDispatched(AuditEntryRecorded::class);
    }

    public function test_rejects_sensitive_action_without_reason_with_validation_error(): void
    {
        Gate::after(fn (): bool => true);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/audit-entries', [
                'action' => 'payroll.entry_created',
                'auditable_type' => 'payroll_entry',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);

        self::assertSame(0, AuditLog::query()->count());
    }

    public function test_forbids_recording_when_user_lacks_audit_record_ability(): void
    {
        Gate::define('audit.record', fn (): bool => false);

        $this->actingAs(new ApiUser(self::ACTOR_ID))
            ->postJson('/api/audit-entries', [
                'action' => 'updated',
                'auditable_type' => 'anything',
            ])
            ->assertForbidden();

        self::assertSame(0, AuditLog::query()->count());
    }

    public function test_requires_authentication_for_the_store_route(): void
    {
        $this->postJson('/api/audit-entries', [
            'action' => 'updated',
            'auditable_type' => 'anything',
        ])->assertUnauthorized();
    }
}
