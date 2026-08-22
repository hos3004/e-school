<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Domain\Events\GroupActivated;
use Modules\Groups\Domain\Models\Group;

uses(RefreshDatabase::class);

it('publishes GroupActivated with primitive payload only', function () {
    Event::fake([GroupActivated::class]);

    $group = app(ActivateGroupAction::class)->execute(Group::factory()->create());

    Event::assertDispatched(
        GroupActivated::class,
        function (GroupActivated $event) use ($group): bool {
            $payload = $event->payload();

            return $event->name() === 'groups.activated'
                && $event->module() === 'Groups'
                && $event->groupId === (string) $group->getKey()
                && $event->organizationId === (string) $group->organization_id
                && collect($payload)->every(fn ($value): bool => is_scalar($value) || $value === null);
        }
    );
});
