<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Tests\Support\ApiUser;

function organizationApiUser(): ApiUser
{
    return new ApiUser('01ORGUSER00000000000000000');
}

it('stores an organization over the api and returns 201', function (): void {
    Gate::after(fn (): bool => true);
    $slug = 'peace-'.strtolower((string) str()->ulid());

    $response = $this->actingAs(organizationApiUser())
        ->postJson('/api/organizations', [
            'name' => ['ar' => 'مدرسة السلام', 'en' => 'Peace School'],
            'slug' => $slug,
            'default_timezone' => 'Africa/Cairo',
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'supported_locales' => ['ar', 'en'],
            'week_starts_on' => 'saturday',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', $slug);

    expect(Organization::query()->where('slug', $slug)->exists())->toBeTrue();
});

it('rejects an invalid payload with a validation error', function (): void {
    Gate::after(fn (): bool => true);

    $this->actingAs(organizationApiUser())
        ->postJson('/api/organizations', [
            'name' => ['en' => 'No Arabic Name'],
            'slug' => 'Bad Slug!',
            'default_currency' => 'EGPX',
            'default_locale' => 'de',
            'week_starts_on' => 'funday',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name.ar', 'slug', 'default_timezone', 'default_currency', 'default_locale', 'week_starts_on']);
});

it('forbids storing an organization without permission', function (): void {
    Gate::define('organizations.create', fn (): bool => false);

    $this->actingAs(organizationApiUser())
        ->postJson('/api/organizations', [
            'name' => ['ar' => 'مدرسة'],
            'slug' => 'school',
            'default_timezone' => 'UTC',
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'week_starts_on' => 'saturday',
        ])
        ->assertForbidden();
});

it('shows an existing organization', function (): void {
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create([
        'slug' => 'visible-'.strtolower((string) str()->ulid()),
    ]);

    $this->actingAs(organizationApiUser())
        ->getJson("/api/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $organization->id);
});

it('rejects changing the immutable slug on update', function (): void {
    Gate::after(fn (): bool => true);

    $organization = OrganizationFactory::new()->create();

    $this->actingAs(organizationApiUser())
        ->patchJson("/api/organizations/{$organization->id}", [
            'slug' => 'new-slug',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);

    expect($organization->refresh()->slug)->not->toBe('new-slug');
});

it('requires authentication for organization routes', function (): void {
    $this->postJson('/api/organizations', [])->assertUnauthorized();
});
