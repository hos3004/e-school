<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Groups\Application\Actions\CreateGroupAction;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Events\GroupCreated;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

function createTestOrganization(): string
{
    $id = (string) str()->ulid();

    DB::table('organizations')->insert([
        'id' => $id,
        'name' => json_encode(['ar' => 'مدرسة الاختبار', 'en' => 'Test School'], JSON_UNESCAPED_UNICODE),
        'slug' => 'test-'.strtolower(substr($id, -10)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function groupData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => createTestOrganization(),
        'code' => 'GRP-0001',
        'name' => ['ar' => 'مجموعة الرياضيات', 'en' => 'Math Group'],
        'capacity' => 15,
        'timezone' => 'UTC',
        'starts_on' => '2026-02-01',
        'ends_on' => null,
    ], $overrides);
}

it('creates a group in planning status and publishes GroupCreated', function (): void {
    Event::fake([GroupCreated::class]);

    $group = app(CreateGroupAction::class)->execute(groupData());

    expect($group->exists)->toBeTrue()
        ->and($group->status)->toBe(GroupStatus::Planning)
        ->and($group->capacity)->toBe(15)
        ->and($group->name['ar'])->toBe('مجموعة الرياضيات');

    Event::assertDispatched(
        GroupCreated::class,
        fn (GroupCreated $event): bool => $event->groupId === (string) $group->getKey()
            && $event->status === GroupStatus::Planning->value
            && $event->payload()['code'] === 'GRP-0001',
    );
});

it('rejects a duplicate group code even across archived groups', function (): void {
    $action = app(CreateGroupAction::class);

    $first = $action->execute(groupData());
    $first->delete();

    $action->execute(groupData(['organization_id' => createTestOrganization()]));
})->throws(BusinessRuleViolation::class);

it('rejects an end date before the start date', function (): void {
    app(CreateGroupAction::class)->execute(groupData([
        'ends_on' => '2026-01-01',
    ]));
})->throws(BusinessRuleViolation::class);

it('accepts an end date equal to or after the start date', function (): void {
    $group = app(CreateGroupAction::class)->execute(groupData([
        'ends_on' => '2026-06-30',
    ]));

    expect($group->ends_on?->toDateString())->toBe('2026-06-30');
});
