<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Organization\Database\Factories\AcademicCalendarFactory;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Tests\Support\ApiUser;

function calendarApiUser(): ApiUser
{
    return new ApiUser('01CALUSER000000000000000000');
}

it('lists academic calendars of an organization', function (): void {
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();
    AcademicCalendarFactory::new()->count(2)->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs(calendarApiUser())
        ->getJson("/api/organizations/{$organization->id}/academic-calendars")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('stores an academic calendar over the api and returns 201', function (): void {
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();

    $response = $this->actingAs(calendarApiUser())
        ->postJson("/api/organizations/{$organization->id}/academic-calendars", [
            'name' => ['ar' => 'عام 2027 الدراسي', 'en' => 'School Year 2027'],
            'starts_on' => '2027-09-01',
            'ends_on' => '2028-05-31',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_active', false);

    expect(AcademicCalendar::query()->forOrganization($organization->id)->count())->toBe(1);
});

it('rejects a calendar whose end precedes its start with a validation error', function (): void {
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();

    $this->actingAs(calendarApiUser())
        ->postJson("/api/organizations/{$organization->id}/academic-calendars", [
            'name' => ['ar' => 'مقلوب'],
            'starts_on' => '2028-05-31',
            'ends_on' => '2027-09-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_on']);
});

it('activates an inactive calendar and closes the previous active one', function (): void {
    Gate::after(fn (): bool => true);

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

    $this->actingAs(calendarApiUser())
        ->patchJson("/api/academic-calendars/{$next->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($current->refresh()->is_active)->toBeFalse();
});

it('closes an active calendar', function (): void {
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();
    $calendar = AcademicCalendarFactory::new()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs(calendarApiUser())
        ->patchJson("/api/academic-calendars/{$calendar->id}/close")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($calendar->refresh()->is_active)->toBeFalse();
});

it('forbids activating without the activate ability', function (): void {
    Gate::define('academic_calendars.activate', fn (): bool => false);

    $organization = OrganizationFactory::new()->create();
    $calendar = AcademicCalendarFactory::new()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs(calendarApiUser())
        ->patchJson("/api/academic-calendars/{$calendar->id}/activate")
        ->assertForbidden();

    expect($calendar->refresh()->is_active)->toBeFalse();
});
