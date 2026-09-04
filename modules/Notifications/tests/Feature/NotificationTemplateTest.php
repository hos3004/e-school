<?php

declare(strict_types=1);

use Modules\Notifications\Application\Services\TemplateRenderer;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use PHPUnit\Framework\Assert;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

it('seeds Arabic and English templates for all phase-one events and channels', function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    $configuredEvents = array_keys((array) config('notifications.events'));
    $eventCount = count($configuredEvents);
    sort($configuredEvents);
    $seededEvents = NotificationTemplate::query()
        ->distinct()
        ->pluck('event_key')
        ->all();
    sort($seededEvents);

    expect(NotificationTemplate::query()->distinct()->count('event_key'))->toBe($eventCount)
        ->and(NotificationTemplate::query()->distinct()->count('channel'))->toBe(3)
        ->and(NotificationTemplate::query()->distinct()->count('locale'))->toBe(2)
        ->and(NotificationTemplate::query()->count())->toBe($eventCount * 3 * 2)
        ->and($seededEvents)->toBe($configuredEvents);
});

it('keeps template placeholders declared and identical in Arabic and English', function (): void {
    /** @var array<string, array{body: string, parameters?: list<string>}> $arabic */
    $arabic = trans('notifications::templates', [], 'ar');
    /** @var array<string, array{body: string, parameters?: list<string>}> $english */
    $english = trans('notifications::templates', [], 'en');

    expect(array_keys($arabic))->toBe(array_keys($english));

    foreach ($arabic as $eventKey => $template) {
        preg_match_all('/\{\{([a-z0-9_.]+)}}/', $template['body'], $arabicMatches);
        preg_match_all('/\{\{([a-z0-9_.]+)}}/', $english[$eventKey]['body'], $englishMatches);

        expect($arabicMatches[1])->toBe($template['parameters'] ?? [])
            ->and($englishMatches[1])->toBe($english[$eventKey]['parameters'] ?? [])
            ->and($template['parameters'] ?? [])->toBe($english[$eventKey]['parameters'] ?? []);
    }
});

it('falls back from an unavailable locale to Arabic before English', function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    $rendered = app(TemplateRenderer::class)->render(
        eventKey: 'session.scheduled',
        channel: Channel::InApp->value,
        locale: 'fr',
        organizationId: Fixtures::organizationId(),
        payload: ['scheduled_start' => '2026-08-23T10:00:00Z'],
    );

    expect($rendered['locale'])->toBe('ar')
        ->and($rendered['body'])->toContain('2026-08-23T10:00:00Z')
        ->and($rendered['template_parameters'])->toBe(['2026-08-23T10:00:00Z']);
});

it('prefers an organization template over the global template in the same locale', function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);
    $organizationId = Fixtures::organizationId();

    NotificationTemplate::query()->create([
        'organization_id' => $organizationId,
        'event_key' => 'registration.approved',
        'channel' => Channel::Email->value,
        'locale' => 'en',
        'subject' => 'Organization subject',
        'body' => 'Organization body',
        'provider_template_name' => null,
        'parameters' => [],
        'is_active' => true,
    ]);

    $rendered = app(TemplateRenderer::class)->render(
        eventKey: 'registration.approved',
        channel: Channel::Email->value,
        locale: 'en',
        organizationId: $organizationId,
        payload: [],
    );

    expect($rendered['subject'])->toBe('Organization subject')
        ->and($rendered['body'])->toBe('Organization body');
});

it('rejects a template when an announced parameter is absent from the event payload', function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    try {
        app(TemplateRenderer::class)->render(
            eventKey: 'session.scheduled',
            channel: Channel::Email->value,
            locale: 'en',
            organizationId: Fixtures::organizationId(),
            payload: [],
        );
        Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.template_parameter_missing');
    }
});
