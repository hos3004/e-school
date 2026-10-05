<?php

declare(strict_types=1);

use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\SupportBot\Database\Seeders\SupportBotContentSeeder;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Shared\Testing\Fixtures;

/*
| نقطة الاتصال من الواجهة.
|
| أهم ما تثبته هنا: أن المستخدم لا يرى خطأً أبدًا. عطل المزوّد وإيقاف البوت
| والموضوع المحظور، كلها تنتهي بردّ 200 ونصّ مهذّب — لا 500 في وجه طالب.
*/

function botHttpActor(string $roleName): User
{
    $organizationId = Fixtures::organizationId();
    $userId = Fixtures::userId();

    $roleId = DB::table('roles')->where('name', $roleName)->whereNull('organization_id')->value('id');

    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => 'Modules\\Identity\\Domain\\Models\\User',
        'model_id' => $userId,
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

    return User::query()->findOrFail($userId);
}

beforeEach(function (): void {
    $this->seed(AccessControlSeeder::class);
    $this->seed(SupportBotContentSeeder::class);

    config([
        'llm.driver' => 'null',
        'console.enabled' => true,
        'support_bot.default_audiences' => ['teacher', 'supervisor', 'student', 'administrator', 'guardian'],
        'llm.providers.null.classify' => BotTopic::PlatformHelp->value,
        'llm.providers.null.answer' => 'تجد صفحة الجدول من القائمة الجانبية.',
    ]);
});

it('answers an authenticated teacher', function (): void {
    $user = botHttpActor('teacher');

    $this->actingAs($user)
        ->postJson('/bot/ask', ['message' => 'أين أجد جدولي؟'])
        ->assertOk()
        ->assertJsonPath('withheld', false)
        ->assertJsonPath('topic', BotTopic::PlatformHelp->value)
        ->assertJsonStructure(['reply', 'conversationId', 'topic', 'withheld']);
});

it('turns a money question into a prepared reply, not an error', function (): void {
    $user = botHttpActor('teacher');

    config(['llm.providers.null.classify' => BotTopic::PayrollDues->value]);

    $response = $this->actingAs($user)
        ->postJson('/bot/ask', ['message' => 'كم مستحقاتي؟'])
        ->assertOk()
        ->assertJsonPath('withheld', true);

    expect($response->json('reply'))->toContain('صفحة المستحقات');
});

it('answers politely instead of failing when the bot is switched off', function (): void {
    $user = botHttpActor('teacher');
    $organizationId = (string) $user->getAttribute('organization_id');

    app(LlmConnections::class)->setActive($organizationId, false, (string) $user->getKey(), 'اختبار');

    $this->actingAs($user)
        ->postJson('/bot/ask', ['message' => 'أين أجد جدولي؟'])
        ->assertOk()
        ->assertJsonPath('withheld', true);
});

it('rejects an empty or oversized message at the edge', function (): void {
    $user = botHttpActor('teacher');

    $this->actingAs($user)->postJson('/bot/ask', ['message' => ''])->assertStatus(422);
    $this->actingAs($user)->postJson('/bot/ask', ['message' => str_repeat('أ', 5000)])->assertStatus(422);
});

it('refuses anonymous callers', function (): void {
    $this->postJson('/bot/ask', ['message' => 'مرحبا'])->assertStatus(401);
});

it('returns the running session transcript', function (): void {
    $user = botHttpActor('teacher');

    $this->actingAs($user)->postJson('/bot/ask', ['message' => 'أين أجد جدولي؟'])->assertOk();

    $response = $this->actingAs($user)->getJson('/bot/history')->assertOk();

    expect($response->json('available'))->toBeTrue()
        ->and($response->json('messages'))->toHaveCount(2)
        ->and($response->json('messages.0.role'))->toBe('user')
        ->and($response->json('messages.1.role'))->toBe('bot');
});

it('tells the widget it is unavailable for a blocked audience', function (): void {
    $user = botHttpActor('student');

    config(['support_bot.default_audiences' => ['teacher']]);

    $this->actingAs($user)
        ->getJson('/bot/history')
        ->assertOk()
        ->assertJsonPath('available', false);
});
