<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Application\Actions\ArchiveGroupAction;
use Modules\Groups\Application\Actions\CompleteGroupAction;
use Modules\Groups\Application\Actions\UpdateGroupAction;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = app(UpdateGroupAction::class);
    $this->group = Group::factory()->create();
});

it('updates editable fields and never touches status or organization', function (): void {
    $originalOrganization = (string) $this->group->organization_id;

    $updated = $this->action->execute($this->group, [
        'name' => ['ar' => 'اسم جديد'],
        'capacity' => 20,
        'status' => GroupStatus::Completed->value,
        'organization_id' => (string) str()->ulid(),
    ]);

    expect($updated->refresh()->name['ar'])->toBe('اسم جديد')
        ->and($updated->capacity)->toBe(20)
        ->and($updated->status)->toBe(GroupStatus::Planning)
        ->and((string) $updated->organization_id)->toBe($originalOrganization);
});

it('rejects updates on an archived group', function (): void {
    app(ArchiveGroupAction::class)->execute($this->group, 'إغلاق تشغيلي');

    $this->action->execute($this->group, ['capacity' => 10]);
})->throws(BusinessRuleViolation::class);

it('rejects moving planning group directly to completed', function (): void {
    app(CompleteGroupAction::class)->execute($this->group);
})->throws(BusinessRuleViolation::class);

it('activates a planning group then completes it', function (): void {
    $activated = app(ActivateGroupAction::class)->execute($this->group);

    expect($activated->status)->toBe(GroupStatus::Active);

    $completed = app(CompleteGroupAction::class)->execute($activated->refresh());

    expect($completed->status)->toBe(GroupStatus::Completed)
        ->and(GroupStatus::Completed->isTerminal())->toBeTrue();
});

it('rejects activating an already active group', function (): void {
    $active = Group::factory()->active()->create();

    app(ActivateGroupAction::class)->execute($active);
})->throws(BusinessRuleViolation::class);

it('requires a reason to archive and soft deletes without destroying data', function (): void {
    app(ArchiveGroupAction::class)->execute($this->group, '   ');
})->throws(BusinessRuleViolation::class);

it('archives with a reason and keeps the row retrievable', function (): void {
    app(ArchiveGroupAction::class)->execute($this->group, 'انخفاض الطلب');

    expect($this->group->refresh()->trashed())->toBeTrue()
        ->and(Group::withTrashed()->find($this->group->getKey()))->not->toBeNull();
});
