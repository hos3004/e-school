<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Policies\NotificationTemplatePolicy;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Notifications\Presentation\Filament\Resources\NotificationTemplateResource;

uses(RefreshDatabase::class);

function templateOrganizationId(string $seed): string
{
    $id = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $id,
        'name' => json_encode(['ar' => $seed, 'en' => $seed], JSON_THROW_ON_ERROR),
        'slug' => 'tpl-'.strtolower($seed).'-'.strtolower(substr($id, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function makeTemplate(
    ?string $organizationId,
    string $eventKey = 'registration.approved',
    string $channel = 'email',
    string $locale = 'en',
): NotificationTemplate {
    return NotificationTemplate::query()->create([
        'organization_id' => $organizationId,
        'event_key' => $eventKey,
        'channel' => $channel,
        'locale' => $locale,
        'subject' => 'Subject',
        'body' => 'Body',
        'provider_template_name' => null,
        'parameters' => [],
        'is_active' => true,
    ]);
}

it('shows global and own-organization templates but never another organization', function (): void {
    $mineOrg = templateOrganizationId('MINE');
    $otherOrg = templateOrganizationId('OTHER');

    $global = makeTemplate(null, 'registration.approved');
    $mine = makeTemplate($mineOrg, 'registration.approved');
    $other = makeTemplate($otherOrg, 'registration.approved');

    $user = User::factory()->inOrganization($mineOrg)->create();
    $this->actingAs($user);

    $ids = NotificationTemplateResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($global->id)
        ->and($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

it('returns nothing when the session has no resolvable organization', function (): void {
    makeTemplate(null);

    expect(NotificationTemplateResource::getEloquentQuery()->count())->toBe(0);
});

it('denies template management without the settings.manage permission', function (): void {
    $org = templateOrganizationId('DENY');
    $user = User::factory()->inOrganization($org)->create();
    $template = makeTemplate($org);
    $policy = new NotificationTemplatePolicy;

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $template))->toBeFalse()
        ->and($policy->delete($user, $template))->toBeFalse();
});

it('lets a settings manager edit organization templates but never the shared global default', function (): void {
    Gate::before(static fn (): bool => true);

    $mineOrg = templateOrganizationId('EDIT');
    $otherOrg = templateOrganizationId('FOREIGN');
    $user = User::factory()->inOrganization($mineOrg)->create();

    $global = makeTemplate(null);
    $mine = makeTemplate($mineOrg);
    $foreign = makeTemplate($otherOrg);

    $policy = new NotificationTemplatePolicy;

    expect($policy->update($user, $mine))->toBeTrue()
        ->and($policy->delete($user, $mine))->toBeTrue()
        ->and($policy->update($user, $global))->toBeFalse()
        ->and($policy->delete($user, $global))->toBeFalse()
        ->and($policy->update($user, $foreign))->toBeFalse()
        ->and($policy->view($user, $global))->toBeTrue()
        ->and($policy->view($user, $foreign))->toBeFalse();
});

it('opens the create and edit form pages for a settings manager', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');

    $org = templateOrganizationId('PAGES');
    $user = User::factory()->inOrganization($org)->create();
    $this->actingAs($user);
    $template = makeTemplate($org);

    $this->get(NotificationTemplateResource::getUrl('create', panel: 'admin'))->assertOk();
    $this->get(NotificationTemplateResource::getUrl('edit', ['record' => $template], panel: 'admin'))
        ->assertOk();
});

it('marks templates without an organization as the shared global default', function (): void {
    $org = templateOrganizationId('SCOPE');

    expect(makeTemplate(null)->isGlobal())->toBeTrue()
        ->and(makeTemplate($org)->isGlobal())->toBeFalse();

    $visibleIds = NotificationTemplate::query()
        ->visibleToOrganization($org)
        ->pluck('organization_id')
        ->all();

    expect($visibleIds)->each->toBeIn([null, $org]);
});
