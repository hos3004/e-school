<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Organization\Application\Actions\CreateOrganization;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Events\OrganizationCreated;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;

it('creates an organization and dispatches OrganizationCreated', function (): void {
    Event::fake([OrganizationCreated::class]);

    $action = app(CreateOrganization::class);

    $organization = $action->execute([
        'name' => ['ar' => 'مدرسة الأمل', 'en' => 'Hope School'],
        'slug' => 'hope-school',
        'default_timezone' => 'Africa/Cairo',
        'default_currency' => 'EGP',
        'default_locale' => 'ar',
        'supported_locales' => ['ar', 'en'],
        'week_starts_on' => 'saturday',
    ]);

    expect($organization->exists)->toBeTrue()
        ->and(Organization::query()->where('slug', 'hope-school')->exists())->toBeTrue();

    Event::assertDispatched(OrganizationCreated::class, static fn (OrganizationCreated $event): bool => $event->organizationId === $organization->id
        && $event->slug === 'hope-school'
        && $event->module() === 'organization');
});

it('rejects a duplicated slug with a business rule violation', function (): void {
    OrganizationFactory::new()->create(['slug' => 'taken-slug']);

    $action = app(CreateOrganization::class);

    try {
        $action->execute([
            'name' => ['ar' => 'مدرسة أخرى'],
            'slug' => 'taken-slug',
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
