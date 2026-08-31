<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Organization\Application\Actions\AddHoliday;
use Modules\Organization\Database\Factories\HolidayFactory;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Enums\HolidaySource;
use Modules\Organization\Domain\Events\HolidayAdded;
use Modules\Organization\Domain\Models\Holiday;
use Shared\Support\BusinessRuleViolation;

it('adds a holiday and dispatches HolidayAdded', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake([HolidayAdded::class]);

    $organization = OrganizationFactory::new()->create();
    $action = app(AddHoliday::class);

    $holiday = $action->execute(
        organizationId: $organization->id,
        name: ['ar' => 'عطلة منتصف العام', 'en' => 'Mid-year break'],
        startsOn: '2027-01-15',
        endsOn: '2027-01-25',
    );

    expect($holiday->exists)->toBeTrue()
        ->and($holiday->source)->toBe(HolidaySource::Manual)
        ->and($holiday->blocks_scheduling)->toBeTrue();

    Event::assertDispatched(HolidayAdded::class, static fn (HolidayAdded $event): bool => $event->holidayId === $holiday->id
        && $event->startsOn === '2027-01-15');
});

it('rejects a holiday longer than the configured maximum', function (): void {
    /** @var \Tests\TestCase $this */
    config()->set('organization.rules.max_holiday_days', 5);

    $organization = OrganizationFactory::new()->create();
    $action = app(AddHoliday::class);

    try {
        $action->execute(
            organizationId: $organization->id,
            name: ['ar' => 'طويلة جدًا'],
            startsOn: '2027-01-01',
            endsOn: '2027-02-15',
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.holiday_too_long')
            ->and($violation->context['max_days'])->toBe(5);
    }

    expect(Holiday::query()->forOrganization($organization->id)->count())->toBe(0);
});

it('rejects a holiday overlapping an existing one for the same organization', function (): void {
    /** @var \Tests\TestCase $this */
    $organization = OrganizationFactory::new()->create();
    HolidayFactory::new()->create([
        'organization_id' => $organization->id,
        'academic_calendar_id' => null,
        'starts_on' => '2027-03-10',
        'ends_on' => '2027-03-20',
    ]);

    $action = app(AddHoliday::class);

    try {
        $action->execute(
            organizationId: $organization->id,
            name: ['ar' => 'متقاطعة'],
            startsOn: '2027-03-18',
            endsOn: '2027-03-28',
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.holiday_overlaps');
    }
});

it('rejects a holiday whose end precedes its start', function (): void {
    /** @var \Tests\TestCase $this */
    $organization = OrganizationFactory::new()->create();
    $action = app(AddHoliday::class);

    try {
        $action->execute(
            organizationId: $organization->id,
            name: ['ar' => 'مقلوبة'],
            startsOn: '2027-04-10',
            endsOn: '2027-04-01',
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.holiday_range_invalid');
    }
});
