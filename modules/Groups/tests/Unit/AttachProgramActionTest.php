<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Groups\Application\Actions\AttachProgramAction;
use Modules\Groups\Application\Actions\DetachProgramAction;
use Modules\Groups\Database\Factories\GroupFactory;
use Modules\Groups\Domain\Events\ProgramAttachedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = app(AttachProgramAction::class);
    $this->group = Group::factory()->create();
});

function programId(): string
{
    return GroupFactory::ensureProgram();
}

it('attaches a program to a group and publishes the event', function (): void {
    Event::fake([ProgramAttachedToGroup::class]);

    $programId = programId();

    $link = app(AttachProgramAction::class)->execute($this->group, $programId);

    expect((string) $link->group_id)->toBe((string) $this->group->getKey())
        ->and((string) $link->program_id)->toBe($programId);

    Event::assertDispatched(
        ProgramAttachedToGroup::class,
        fn (ProgramAttachedToGroup $event): bool => $event->groupId === (string) $this->group->getKey()
            && $event->programId === $programId,
    );
});

it('rejects attaching the same program twice', function (): void {
    $programId = programId();

    $this->action->execute($this->group, $programId);
    $this->action->execute($this->group, $programId);
})->throws(BusinessRuleViolation::class);

it('detaches without deleting group data', function (): void {
    $programId = programId();

    $link = $this->action->execute($this->group, $programId);
    app(DetachProgramAction::class)->execute($link, 'لم يعد البرنامج ضمن خطة المجموعة');

    expect(GroupProgram::query()->forGroup((string) $this->group->getKey())->exists())->toBeFalse()
        ->and($this->group->refresh()->exists)->toBeTrue();
});
