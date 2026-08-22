<?php

declare(strict_types=1);

namespace Modules\Audit\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use Modules\Audit\Application\Policies\AuditLogPolicy;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Audit\Tests\Support\ApiUser;
use Modules\Audit\Tests\Support\RefreshAuditDatabase;
use Tests\TestCase;

/**
 * سياسة قيود التدقيق — الدفتر أقرّ، والصلاحيات عبر Gate فقط.
 */
final class AuditLogPolicyTest extends TestCase
{
    use RefreshAuditDatabase;

    public function test_never_allows_update_or_delete_even_with_full_grant(): void
    {
        Gate::after(fn (): bool => true);

        $policy = new AuditLogPolicy;
        $entry = AuditLog::factory()->make();
        $user = new ApiUser('01ACTOR0000000000000000000');

        self::assertFalse($policy->update($user, $entry));
        self::assertFalse($policy->delete($user, $entry));
    }

    public function test_grants_viewing_through_audit_view_any_ability(): void
    {
        Gate::define('audit.view_any', fn (): bool => true);
        Gate::define('audit.view', fn (): bool => false);

        $policy = new AuditLogPolicy;
        $entry = AuditLog::factory()->make(['actor_id' => '01OTHERACTOR00000000000000']);
        $user = new ApiUser('01ACTOR0000000000000000000');

        self::assertTrue($policy->viewAny($user));
        self::assertTrue($policy->view($user, $entry));
        self::assertFalse($policy->create($user));
        self::assertFalse($policy->prune($user));
        self::assertFalse($policy->export($user));
    }

    public function test_plain_viewer_sees_only_own_entries(): void
    {
        Gate::define('audit.view_any', fn (): bool => false);
        Gate::define('audit.view', fn (): bool => true);
        Gate::define('audit.record', fn (): bool => false);
        Gate::define('audit.prune', fn (): bool => false);
        Gate::define('audit.export', fn (): bool => false);

        $policy = new AuditLogPolicy;
        $mine = AuditLog::factory()->make(['actor_id' => '01MYID00000000000000000000']);
        $others = AuditLog::factory()->make(['actor_id' => '01OTHERID00000000000000000']);
        $user = new ApiUser('01MYID00000000000000000000');

        self::assertFalse($policy->viewAny($user));
        self::assertTrue($policy->view($user, $mine));
        self::assertFalse($policy->view($user, $others));
        self::assertFalse($policy->create($user));
        self::assertFalse($policy->prune($user));
        self::assertFalse($policy->export($user));
    }
}
