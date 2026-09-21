<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Groups\Application\Actions\CloseGroupAction;
use Modules\Groups\Application\Actions\ReopenGroupAction;
use Modules\Groups\Domain\Events\GroupClosed;
use Modules\Groups\Domain\Models\Group;
use Shared\Archive\ClosureSnapshot;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

function groupClosureSnapshot(array $blockers = []): ClosureSnapshot
{
    return new ClosureSnapshot(
        summary: [
            'kind' => 'group',
            'captured_at' => '2026-09-21T00:00:00+00:00',
            'members_total' => 12,
            'sessions_total' => 30,
        ],
        blockers: $blockers,
    );
}

it('closes a group without deleting it or touching its status machine', function (): void {
    Event::fake([GroupClosed::class]);

    $actorId = (string) Str::ulid();
    $group = Group::factory()->create();
    $statusBefore = $group->status;

    $closed = app(CloseGroupAction::class)->execute(
        $group,
        'المجموعة أنهت مدتها',
        groupClosureSnapshot(),
        $actorId,
    );

    expect($closed->closed_at)->not->toBeNull()
        ->and($closed->closure_summary['members_total'])->toBe(12)
        ->and($closed->trashed())->toBeFalse()
        // الإقفال مستقل عن آلة حالات المجموعة؛ لا ينقلها ولا يطلق أحداث حالتها.
        ->and($closed->status)->toBe($statusBefore);

    Event::assertDispatched(GroupClosed::class);
});

it('refuses to close a group that still has active members, and leaves it open', function (): void {
    $group = Group::factory()->create();

    $attempt = fn (): mixed => app(CloseGroupAction::class)->execute(
        $group,
        'محاولة مبكرة',
        groupClosureSnapshot(['members_active' => 5]),
        (string) Str::ulid(),
    );

    expect($attempt)->toThrow(BusinessRuleViolation::class);

    expect($group->fresh()->closed_at)->toBeNull();
});

it('refuses to close a group without a reason', function (): void {
    $group = Group::factory()->create();

    app(CloseGroupAction::class)->execute($group, '', groupClosureSnapshot(), (string) Str::ulid());
})->throws(BusinessRuleViolation::class);

it('reopens a closed group and preserves the frozen summary in the audit trail', function (): void {
    $actorId = (string) Str::ulid();
    $group = Group::factory()->create();

    app(CloseGroupAction::class)->execute($group, 'انتهت', groupClosureSnapshot(), $actorId);
    $reopened = app(ReopenGroupAction::class)->execute($group->fresh(), 'دفعة جديدة', $actorId);

    expect($reopened->closed_at)->toBeNull();

    $entry = AuditLog::query()
        ->where('action', 'groups.group_reopened')
        ->where('auditable_id', (string) $group->getKey())
        ->sole();

    expect($entry->old_values['closure_summary']['sessions_total'])->toBe(30);
});
