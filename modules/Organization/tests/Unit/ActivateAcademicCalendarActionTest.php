<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Organization\Application\Actions\ActivateAcademicCalendar;
use Modules\Organization\Database\Factories\AcademicCalendarFactory;
use Modules\Organization\Database\Factories\HolidayFactory;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Enums\HolidaySource;
use Modules\Organization\Domain\Events\AcademicCalendarActivated;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;

it('activates a calendar and deactivates the previously active one', function (): void {
    Event::fake([AcademicCalendarActivated::class]);

    $organization = OrganizationFactory::new()->create();
    $current = AcademicCalendarFactory::new()->active()->create([
        'organization_id' => $organization->id,
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-05-31',
    ]);
    $next = AcademicCalendarFactory::new()->create([
        'organization_id' => $organization->id,
        'starts_on' => '2027-09-01',
        'ends_on' => '2028-05-31',
    ]);

    $action = app(ActivateAcademicCalendar::class);
    $action->execute($next);

    expect($next->refresh()->is_active)->toBeTrue()
        ->and($current->refresh()->is_active)->toBeFalse();

    Event::assertDispatched(AcademicCalendarActivated::class, static fn (AcademicCalendarActivated $event): bool => $event->academicCalendarId === $next->id);
});

it('is idempotent when the calendar is already active', function (): void {
    Event::fake();

    $organization = OrganizationFactory::new()->create();
    $calendar = AcademicCalendarFactory::new()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $result = app(ActivateAcademicCalendar::class)->execute($calendar);

    expect($result->refresh()->is_active)->toBeTrue();

    Event::assertNotDispatched(AcademicCalendarActivated::class);
});

it('inherits organization level blocking holidays into the activated calendar', function (): void {
    $organization = OrganizationFactory::new()->create();
    $calendar = AcademicCalendarFactory::new()->create([
        'organization_id' => $organization->id,
        'starts_on' => '2027-09-01',
        'ends_on' => '2028-05-31',
    ]);

    HolidayFactory::new()->create([
        'organization_id' => $organization->id,
        'academic_calendar_id' => null,
        'starts_on' => '2028-01-01',
        'ends_on' => '2028-01-05',
        'source' => HolidaySource::Manual,
        'blocks_scheduling' => true,
    ]);

    app(ActivateAcademicCalendar::class)->execute($calendar);

    expect(Holiday::query()
        ->forOrganization($organization->id)
        ->where('academic_calendar_id', $calendar->id)
        ->count())->toBe(1);
});
