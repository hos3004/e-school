<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Organization\Application\Actions\CreateOrganization;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Events\OrganizationCreated;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;

it('creates an organization and dispatches OrganizationCreated', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake([OrganizationCreated::class]);

    $action = app(CreateOrganization::class);
    $slug = 'hope-'.strtolower((string) str()->ulid());

    $organization = $action->execute([
        'name' => ['ar' => 'مدرسة الأمل', 'en' => 'Hope School'],
        'slug' => $slug,
        'default_timezone' => 'Africa/Cairo',
        'default_currency' => 'EGP',
        'default_locale' => 'ar',
        'supported_locales' => ['ar', 'en'],
        'week_starts_on' => 'saturday',
    ]);

    expect($organization->exists)->toBeTrue()
        ->and(Organization::query()->where('slug', $slug)->exists())->toBeTrue();

    Event::assertDispatched(OrganizationCreated::class, static fn (OrganizationCreated $event): bool => $event->organizationId === $organization->id
        && $event->slug === $slug
        && $event->module() === 'organization');
});

it('rejects a duplicated slug with a business rule violation', function (): void {
    /** @var \Tests\TestCase $this */
    $slug = 'taken-'.strtolower((string) str()->ulid());
    OrganizationFactory::new()->create(['slug' => $slug]);

    $action = app(CreateOrganization::class);

    try {
        $action->execute([
            'name' => ['ar' => 'مدرسة أخرى'],
            'slug' => $slug,
            'default_timezone' => 'Africa/Cairo',
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'week_starts_on' => 'saturday',
        ]);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('organization.slug_taken');
    }
});
