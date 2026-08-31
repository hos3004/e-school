<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Organization\Database\Factories\HolidayFactory;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Tests\Support\ApiUser;

function holidayApiUser(): ApiUser
{
    return new ApiUser('01HOLUSER000000000000000000');
}

it('lists holidays of an organization', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();
    HolidayFactory::new()->count(3)->create(['organization_id' => $organization->id]);

    $this->actingAs(holidayApiUser())
        ->getJson("/api/organizations/{$organization->id}/holidays")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('stores a holiday over the api and returns 201', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();

    $response = $this->actingAs(holidayApiUser())
        ->postJson("/api/organizations/{$organization->id}/holidays", [
            'name' => ['ar' => 'عطلة الربيع', 'en' => 'Spring break'],
            'starts_on' => '2027-04-10',
            'ends_on' => '2027-04-20',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.starts_on', '2027-04-10')
        ->assertJsonPath('data.blocks_scheduling', true);

    expect(Holiday::query()->forOrganization($organization->id)->count())->toBe(1);
});

it('rejects a holiday ending before it starts', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();

    $this->actingAs(holidayApiUser())
        ->postJson("/api/organizations/{$organization->id}/holidays", [
            'name' => ['ar' => 'خاطئة'],
            'starts_on' => '2027-04-20',
            'ends_on' => '2027-04-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_on']);
});

it('removes a holiday over the api', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();
    $holiday = HolidayFactory::new()->create(['organization_id' => $organization->id]);

    $this->actingAs(holidayApiUser())
        ->deleteJson("/api/holidays/{$holiday->id}")
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(Holiday::query()->whereKey($holiday->id)->exists())->toBeFalse();
});

it('forbids removing a holiday without the delete ability', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::define('holidays.delete', fn (): bool => false);

    $organization = OrganizationFactory::new()->create();
    $holiday = HolidayFactory::new()->create(['organization_id' => $organization->id]);

    $this->actingAs(holidayApiUser())
        ->deleteJson("/api/holidays/{$holiday->id}")
        ->assertForbidden();

    expect(Holiday::query()->whereKey($holiday->id)->exists())->toBeTrue();
});
