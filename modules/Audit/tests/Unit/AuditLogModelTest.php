<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Unit;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Modules\Audit\Domain\Enums\AuditActorType;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Tests\TestCase;

/**
 * سلوك نموذج دفتر التدقيق — append-only.
 */
final class AuditLogModelTest extends TestCase
{
    use RefreshAuditDatabase;

    public function test_is_append_only_ledger_without_timestamps_or_soft_deletes(): void
    {
        $entry = AuditLog::factory()->create();

        $entry->update(['reason' => 'محاولة تعديل لا تُكتب في عمود تحديث']);

        self::assertFalse($entry->timestamps);
        self::assertFalse(Schema::hasColumn('audit_log', 'updated_at'));
        self::assertFalse(in_array(SoftDeletes::class, class_uses_recursive($entry), true));
        self::assertNull(data_get($entry->getDirty(), 'updated_at'));
    }

    public function test_casts_jsonb_to_arrays_and_actor_type_to_enum(): void
    {
        $entry = AuditLog::factory()->create([
            'actor_type' => 'system',
            'old_values' => ['status' => 'scheduled'],
            'new_values' => ['status' => 'completed'],
        ]);

        $entry->refresh();

        self::assertInstanceOf(AuditActorType::class, $entry->actor_type);
        self::assertSame(AuditActorType::System, $entry->actor_type);
        self::assertIsArray($entry->old_values);
        self::assertSame('scheduled', $entry->old_values['status']);
        self::assertSame('completed', $entry->new_values['status']);
    }

    public function test_scopes_entries_for_single_organization(): void
    {
        AuditLog::factory()->count(2)->create(['organization_id' => '01ORGAAAA00000000000000000']);
        AuditLog::factory()->create(['organization_id' => '01ORGBBBB00000000000000000']);

        $scoped = AuditLog::query()->forOrganization('01ORGAAAA00000000000000000')->get();

        self::assertCount(2, $scoped);
        self::assertTrue($scoped->every(
            fn (AuditLog $entry): bool => $entry->organization_id === '01ORGAAAA00000000000000000',
        ));

        self::assertSame(3, AuditLog::query()->forOrganization(null)->count());
    }

    public function test_scopes_entries_by_action_case_insensitively(): void
    {
        AuditLog::factory()->create(['action' => 'logged_in']);

        self::assertSame(1, AuditLog::query()->forAction('LOGGED_IN')->count());
    }
}
