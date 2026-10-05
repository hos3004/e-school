<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\Organization\Domain\Models\Organization;
use Modules\SupportBot\Database\Seeders\SupportBotContentSeeder;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Modules\SupportBot\Domain\Models\BotRule;

function consoleBotUser(string $roleName): User
{
    $organization = Organization::factory()->create();
    $organizationId = (string) $organization->id;

    /*
     * factory لا Fixtures: الأخيرة تتبنّى أقدم مؤسسة موجودة وتنتج حسابًا قد لا
     * يجتاز canLogIn، فيعيد EnsureConsoleEnabled الرمز 403 قبل أي فحص صلاحية —
     * وهو فشل يبدو كأنه نقص صلاحية وليس كذلك.
     */
    $user = User::factory()->inOrganization($organizationId)->create();

    $roleId = DB::table('roles')->where('name', $roleName)->whereNull('organization_id')->value('id');

    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => 'Modules\Identity\Domain\Models\User',
        'model_id' => (string) $user->getKey(),
    ]);

    $provider = IntegrationProvider::query()->updateOrCreate(
        ['key' => 'anthropic'],
        ['name' => ['ar' => 'أنثروبيك', 'en' => 'Anthropic'], 'category' => 'ai', 'is_active' => true],
    );

    IntegrationConnection::query()->updateOrCreate(
        ['organization_id' => $organizationId, 'provider_id' => (string) $provider->getKey()],
        [
            'status' => ConnectionStatus::Active,
            'credentials' => ['api_key' => 'test-key'],
            'settings' => ['base_url' => 'https://api.anthropic.com'],
            'activated_at' => now('UTC'),
        ],
    );

    return $user->refresh();
}

beforeEach(function (): void {
    $this->seed(AccessControlSeeder::class);
    $this->seed(SupportBotContentSeeder::class);
    $this->withoutVite();

    /*
     * البوابات تُبنى من جدول الصلاحيات وقت إقلاع التطبيق، والاختبار يزرع
     * الصفوف بعد الإقلاع — فصلاحية جديدة تبقى بلا Gate وتُرفض دائمًا.
     *
     * إعادة التسجيل هنا تختبر الربط الحقيقي بدل تزييفه بـGate::define.
     * ويلزم أن يقابلها في الإنتاج تشغيل البذرة ثم إعادة تحميل التطبيق.
     */
    app(PermissionGateRegistrar::class)->register();

    config(['console.enabled' => true, 'llm.driver' => 'null']);
});

it('renders the bot section for an administrator', function (): void {
    $user = consoleBotUser('platform_admin');

    $this->actingAs($user)
        ->get('/manage/bot')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Console/SupportBot')
            ->where('connection.enabled', true)
            ->where('abilities.readArchive', true)
            ->has('vocabulary.topics')
            ->has('rules')
            ->has('entries'));
});

it('keeps the section away from a teacher', function (): void {
    $user = consoleBotUser('teacher');

    $this->actingAs($user)->get('/manage/bot')->assertForbidden();
});

it('never exposes the provider key to the browser', function (): void {
    $user = consoleBotUser('platform_admin');

    $response = $this->actingAs($user)->get('/manage/bot')->assertOk();

    expect($response->getContent())->not->toContain('test-key');
});

it('refuses a settings change that carries no reason', function (): void {
    $user = consoleBotUser('platform_admin');

    $this->actingAs($user)
        ->post('/manage/bot/toggle', ['active' => false])
        ->assertSessionHasErrors('reason');
});

it('turns the bot off with a written reason', function (): void {
    $user = consoleBotUser('platform_admin');
    $organizationId = (string) $user->getAttribute('organization_id');

    $this->actingAs($user)
        ->post('/manage/bot/toggle', ['active' => false, 'reason' => 'إيقاف مؤقت للمراجعة'])
        ->assertRedirect();

    expect(app(LlmConnections::class)->isEnabled($organizationId))->toBeFalse();

    $this->assertDatabaseHas('audit_log', [
        'action' => 'support_bot.connection_toggled',
        'organization_id' => $organizationId,
    ]);
});

it('writes an academy rule row that shadows the shipped one', function (): void {
    $user = consoleBotUser('platform_admin');
    $organizationId = (string) $user->getAttribute('organization_id');

    $this->actingAs($user)
        ->post('/manage/bot/rule', [
            'topic' => BotTopic::Policy->value,
            'audience' => 'teacher',
            'mode' => TopicMode::Deny->value,
            'reason' => 'قرار إداري بعدم الرد على أسئلة السياسات مؤقتًا',
        ])
        ->assertRedirect();

    $rule = BotRule::query()
        ->where('organization_id', $organizationId)
        ->where('topic', BotTopic::Policy->value)
        ->where('audience', 'teacher')
        ->first();

    expect($rule)->not->toBeNull()
        ->and($rule->mode)->toBe(TopicMode::Deny);

    // الصف العام المشحون يبقى كما هو مرجعًا (القيمة تعود ككائن Enum بفعل الـcast).
    expect(BotRule::query()->whereNull('organization_id')
        ->where('topic', BotTopic::Policy->value)->where('audience', 'teacher')
        ->value('mode'))->toBe(TopicMode::Allow);
});

it('refuses to open the bot for an account in another organization', function (): void {
    $user = consoleBotUser('platform_admin');

    // مؤسسة أخرى بحسابها: الفحص يجب أن يمنع الكتابة عبر حدود المؤسسات.
    $outsider = User::factory()
        ->inOrganization((string) Organization::factory()->create()->id)
        ->create();

    $this->actingAs($user)
        ->post('/manage/bot/access', [
            'user_id' => (string) $outsider->getKey(),
            'enabled' => true,
            'reason' => 'محاولة فتح لحساب خارج المؤسسة',
        ])
        ->assertNotFound();
});
