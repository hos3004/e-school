<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Services\TemplateRenderer;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * محرر قوالب واتساب في الإعدادات: التعديل نسخة خاصة بالمؤسسة، والاستعادة
 * حذف هذه النسخة فيعود القالب العام كما هو دون أي مساس به.
 */
final class WhatsappTemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = ['admin.panel.access', 'organizations.view', 'settings.manage'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach (['admin.panel.access', 'organizations.view', 'settings.manage'] as $permission) {
            Gate::define($permission, fn (): bool => in_array($permission, $this->permissions, true));
        }
        NotificationTemplate::query()->where('event_key', 'session.approaching')->delete();
        NotificationTemplate::query()->create([
            'organization_id' => null,
            'event_key' => 'session.approaching',
            'channel' => 'whatsapp',
            'locale' => 'ar',
            'subject' => 'موعد الحصة يقترب',
            'body' => 'تبدأ حصة {{course_name}} في {{scheduled_start}}.',
            'provider_template_name' => 'session_approaching',
            'parameters' => ['course_name', 'scheduled_start'],
            'is_active' => true,
        ]);
    }

    public function test_admin_customizes_a_template_and_restores_the_original(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);

        $listed = $this->template();
        self::assertNull($listed['custom']);
        self::assertSame(['course_name', 'scheduled_start'], $listed['parameters']);
        self::assertSame('تبدأ حصة {{course_name}} في {{scheduled_start}}.', $listed['original']['body']);

        $this->post('/manage/notification-templates', $this->payload('تذكير ودي: حصة {{course_name}} تبدأ {{scheduled_start}}.'))
            ->assertSessionHasNoErrors();

        $custom = $this->template()['custom'];
        self::assertIsArray($custom);
        self::assertSame('تذكير ودي: حصة {{course_name}} تبدأ {{scheduled_start}}.', $custom['body']);
        self::assertSame('تذكير ودي: حصة القرآن تبدأ الآن.', $this->rendered($organization->id));
        self::assertSame(1, NotificationTemplate::query()->whereNull('organization_id')
            ->where('event_key', 'session.approaching')->where('channel', 'whatsapp')->count());

        $this->put('/manage/notification-templates/'.$custom['id'], $this->payload('حصة {{course_name}} بعد قليل.'))
            ->assertSessionHasNoErrors();
        self::assertSame('حصة القرآن بعد قليل.', $this->rendered($organization->id));

        $this->delete('/manage/notification-templates/'.$custom['id'])->assertSessionHasNoErrors();
        self::assertNull($this->template()['custom']);
        self::assertSame('تبدأ حصة القرآن في الآن.', $this->rendered($organization->id));
    }

    public function test_variable_the_event_does_not_provide_is_rejected(): void
    {
        [, $actor] = $this->context();
        $this->actingAs($actor);

        $this->post('/manage/notification-templates', $this->payload('حصة {{course_name}} مع {{teacher_name}}.'))
            ->assertSessionHasErrors('body');

        self::assertSame(0, NotificationTemplate::query()->whereNotNull('organization_id')->count());
    }

    public function test_other_organization_and_unauthorized_actor_cannot_touch_the_custom_template(): void
    {
        [, $actor] = $this->context();
        $this->actingAs($actor);
        $this->post('/manage/notification-templates', $this->payload('حصة {{course_name}}.'))->assertSessionHasNoErrors();
        $customId = (string) NotificationTemplate::query()->whereNotNull('organization_id')->value('id');

        [, $outsider] = $this->context();
        $this->actingAs($outsider);
        self::assertNull($this->template()['custom']);
        $this->delete('/manage/notification-templates/'.$customId)->assertForbidden();

        $this->actingAs($actor);
        $this->permissions = ['admin.panel.access', 'organizations.view'];
        $this->delete('/manage/notification-templates/'.$customId)->assertForbidden();
        self::assertSame(1, NotificationTemplate::query()->whereKey($customId)->count());
    }

    /** @return array{Organization, User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization($organization->id)->create();

        return [$organization, $actor];
    }

    /** @return array<string, mixed> */
    private function payload(string $body): array
    {
        return [
            'event_key' => 'session.approaching',
            'channel' => 'whatsapp',
            'locale' => 'ar',
            'subject' => 'موعد الحصة يقترب',
            'body' => $body,
            'is_active' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function template(): array
    {
        /** @var list<array<string, mixed>> $templates */
        $templates = $this->get('/manage/settings')->assertOk()->viewData('page')['props']['whatsappTemplates'];

        foreach ($templates as $template) {
            if ($template['event_key'] === 'session.approaching') {
                return $template;
            }
        }

        self::fail('session.approaching is missing from the WhatsApp templates list.');
    }

    private function rendered(string $organizationId): string
    {
        return app(TemplateRenderer::class)->render(
            'session.approaching', 'whatsapp', 'ar', $organizationId,
            ['course_name' => 'القرآن', 'scheduled_start' => 'الآن'],
        )['body'];
    }
}
