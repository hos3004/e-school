<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Organization\Application\Actions\UpsertOrganizationSetting;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Events\OrganizationSettingUpdated;
use Modules\Organization\Domain\Models\OrganizationSetting;
use Shared\Support\BusinessRuleViolation;

it('creates then updates the same setting key without duplicating rows', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake([OrganizationSettingUpdated::class]);

    $organization = OrganizationFactory::new()->create();
    $action = app(UpsertOrganizationSetting::class);

    $created = $action->execute($organization, 'attendance.grace_minutes', 10);
    $updated = $action->execute($organization, 'attendance.grace_minutes', 15);

    expect($updated->id)->toBe($created->id)
        ->and($updated->value)->toBe(15)
        ->and(OrganizationSetting::query()->forOrganization($organization->id)->count())->toBe(1);

    Event::assertDispatchedTimes(OrganizationSettingUpdated::class, 2);
});

it('rejects a setting key longer than the configured maximum', function (): void {
    /** @var \Tests\TestCase $this */
    config()->set('organization.limits.setting_key_max_length', 8);

    $organization = OrganizationFactory::new()->create();
    $action = app(UpsertOrganizationSetting::class);

    try {
        $action->execute($organization, 'a.very.long.setting.key', true);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.setting_key_too_long');
    }
});
