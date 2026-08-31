<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Organization\Application\Actions\CreateAcademicCalendar;
use Modules\Organization\Database\Factories\AcademicCalendarFactory;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Events\AcademicCalendarCreated;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Shared\Support\BusinessRuleViolation;

it('creates an academic calendar and dispatches the event', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake([AcademicCalendarCreated::class]);

    $organization = OrganizationFactory::new()->create();
    $action = app(CreateAcademicCalendar::class);

    $calendar = $action->execute(
        organization: $organization,
        name: ['ar' => 'عام 2027', 'en' => 'Year 2027'],
        startsOn: '2027-09-01',
        endsOn: '2028-05-31',
    );

    expect($calendar->exists)->toBeTrue()
        ->and($calendar->is_active)->toBeFalse()
        ->and(AcademicCalendar::query()->forOrganization($organization->id)->count())->toBe(1);

    Event::assertDispatched(AcademicCalendarCreated::class);
});

it('rejects a calendar whose end date is not after its start date', function (): void {
    /** @var \Tests\TestCase $this */
    $organization = OrganizationFactory::new()->create();
    $action = app(CreateAcademicCalendar::class);

    try {
        $action->execute(
            organization: $organization,
            name: ['ar' => 'مقلوب'],
            startsOn: '2027-09-01',
            endsOn: '2027-05-31',
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.calendar_range_invalid');
    }
});

it('rejects a calendar overlapping an already active one', function (): void {
    /** @var \Tests\TestCase $this */
    $organization = OrganizationFactory::new()->create();
    AcademicCalendarFactory::new()->active()->create([
        'organization_id' => $organization->id,
        'starts_on' => '2027-09-01',
        'ends_on' => '2028-05-31',
    ]);

    $action = app(CreateAcademicCalendar::class);

    try {
        $action->execute(
            organization: $organization,
            name: ['ar' => 'متقاطع'],
            startsOn: '2028-01-01',
            endsOn: '2028-12-31',
        );
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.calendar_overlaps_active');
    }

    expect(AcademicCalendar::query()->forOrganization($organization->id)->count())->toBe(1);
});

it('allows a non-overlapping calendar next to an active one', function (): void {
    /** @var \Tests\TestCase $this */
    $organization = OrganizationFactory::new()->create();
    AcademicCalendarFactory::new()->active()->create([
        'organization_id' => $organization->id,
        'starts_on' => '2027-09-01',
        'ends_on' => '2028-05-31',
    ]);

    $action = app(CreateAcademicCalendar::class);

    $calendar = $action->execute(
        organization: $organization,
        name: ['ar' => 'لاحق'],
        startsOn: '2028-09-01',
        endsOn: '2029-05-31',
    );

    expect($calendar->exists)->toBeTrue();
});
