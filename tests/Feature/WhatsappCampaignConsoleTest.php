<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Testing\Fixtures;

beforeEach(function (): void {
    // بوابة اللوحة تردّ 404 وهي مطفأة، فكل مسار تحت /manage يحتاج تشغيلها.
    config()->set('console.enabled', true);
});

function campaignAdmin(): User
{
    Gate::define('admin.panel.access', static fn (): bool => true);
    Gate::define('notifications.outbox.create', static fn (): bool => true);

    return User::query()->findOrFail(Fixtures::userId());
}

function campaignChannel(bool $enabled = true): void
{
    $connections = Mockery::mock(GreenApiConnections::class);
    $connections->shouldReceive('isChannelEnabled')->andReturn($enabled);
    $connections->shouldReceive('credentials')->andReturn([
        'api_url' => 'https://api.green-api.com',
        'instance_id' => '1101',
        'token' => 'test-token',
    ]);
    $connections->shouldReceive('view')->andReturn([]);

    app()->instance(GreenApiConnections::class, $connections);
}

it('checks a pasted list and reports what will and will not be sent', function (): void {
    $response = $this->actingAs(campaignAdmin())
        ->postJson('/manage/whatsapp/campaigns/preview', [
            'body' => 'أهلًا {الاسم}',
            'recipients_text' => "أحمد, +201012345678\nسارة, 01112223344\nمكرر, 00201012345678",
        ]);

    $response->assertOk()
        ->assertJsonPath('accepted_count', 1)
        ->assertJsonPath('rejected_count', 1)
        ->assertJsonPath('duplicates', 1)
        ->assertJsonPath('rejected.0.reason', 'phone_missing_country_code')
        ->assertJsonPath('sample_text', 'أهلًا أحمد');
});

it('creates a draft campaign from an uploaded list and an attachment', function (): void {
    Storage::fake('local');
    config(['messaging.campaigns.media.disk' => 'local']);

    $file = UploadedFile::fake()->createWithContent(
        'list.csv',
        "الاسم,الرقم\nأحمد,+201012345678\nسارة,+201112223344\n",
    );

    $this->actingAs(campaignAdmin())
        ->post('/manage/whatsapp/campaigns', [
            'name' => 'دورة سبتمبر',
            'body' => 'أهلًا {الاسم}',
            'reason' => 'دعوة المسجلين',
            'recipients_file' => $file,
            'media' => [UploadedFile::fake()->image('poster.jpg')],
            'delay_min_seconds' => 5,
            'delay_max_seconds' => 15,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $campaign = WhatsappCampaign::query()->firstOrFail();

    expect($campaign->status)->toBe(WhatsappCampaignStatus::Draft)
        ->and($campaign->total_recipients)->toBe(2)
        ->and($campaign->media()->count())->toBe(1);
});

it('refuses a campaign with neither typed numbers nor a file', function (): void {
    $this->actingAs(campaignAdmin())
        ->post('/manage/whatsapp/campaigns', [
            'name' => 'بلا قائمة',
            'body' => 'نص',
            'reason' => 'اختبار',
            'delay_min_seconds' => 5,
            'delay_max_seconds' => 15,
        ])
        ->assertSessionHasErrors('recipients_text');

    expect(WhatsappCampaign::query()->count())->toBe(0);
});

it('refuses a shortest gap longer than the longest gap', function (): void {
    $this->actingAs(campaignAdmin())
        ->post('/manage/whatsapp/campaigns', [
            'name' => 'مدى مقلوب',
            'body' => 'نص',
            'reason' => 'اختبار',
            'recipients_text' => '+201012345678',
            'delay_min_seconds' => 30,
            'delay_max_seconds' => 5,
        ])
        ->assertSessionHasErrors('delay_max_seconds');
});

it('starts and stops a campaign over http', function (): void {
    Queue::fake();
    campaignChannel();

    $admin = campaignAdmin();

    $this->actingAs($admin)
        ->post('/manage/whatsapp/campaigns', [
            'name' => 'حملة',
            'body' => 'نص',
            'reason' => 'اختبار',
            'recipients_text' => "+201012345678\n+201112223344",
            'delay_min_seconds' => 5,
            'delay_max_seconds' => 15,
        ]);

    $campaign = WhatsappCampaign::query()->firstOrFail();

    $this->actingAs($admin)
        ->post("/manage/whatsapp/campaigns/{$campaign->getKey()}/start")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    /*
     * الأثر الباقي هو ما يُختبر هنا: الحالة صارت جارية، ولكل مستلم موعد مخزَّن
     * يبعد عن سابقه بمدة داخل المدى. توزيع المهام نفسه يغطّيه اختبار الإجراء.
     */
    expect($campaign->refresh()->status)->toBe(WhatsappCampaignStatus::Running);

    $moments = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->orderBy('dispatch_after')
        ->pluck('dispatch_after')
        ->all();

    expect($moments)->toHaveCount(2)
        ->and($moments[0])->not->toBeNull()
        ->and($moments[1]->diffInSeconds($moments[0], absolute: true))
        ->toBeGreaterThanOrEqual(5)
        ->toBeLessThanOrEqual(15);

    $this->actingAs($admin)
        ->post("/manage/whatsapp/campaigns/{$campaign->getKey()}/stop", ['reason' => 'غلط في النص'])
        ->assertRedirect();

    expect($campaign->refresh()->status)->toBe(WhatsappCampaignStatus::Stopped)
        ->and(WhatsappCampaignRecipient::query()
            ->where('status', WhatsappCampaignRecipientStatus::Cancelled)
            ->count())->toBe(2);
});

it('turns away a user who cannot create outbound messages', function (): void {
    Gate::define('admin.panel.access', static fn (): bool => true);
    Gate::define('notifications.outbox.create', static fn (): bool => false);

    $user = User::query()->findOrFail(Fixtures::userId());

    $this->actingAs($user)
        ->post('/manage/whatsapp/campaigns', [
            'name' => 'حملة',
            'body' => 'نص',
            'reason' => 'اختبار',
            'recipients_text' => '+201012345678',
            'delay_min_seconds' => 5,
            'delay_max_seconds' => 15,
        ])
        ->assertForbidden();
});

/*
 * عزل المؤسسات: حملة مؤسسة أخرى لا تُقرأ ولا تُبدأ من حساب هذه المؤسسة، ولو
 * ملك صاحبه صلاحية الإرسال كاملة.
 */
it('hides a campaign that belongs to another organization', function (): void {
    /*
     * مؤسسة الاختبار تُنشأ أولًا: Fixtures تتبنّى أقدم مؤسسة موجودة، فإدخال
     * المؤسسة الغريبة قبلها كان يجعلها هي مؤسسة المستخدم نفسه.
     */
    $admin = campaignAdmin();

    $otherOrganizationId = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $otherOrganizationId,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other'], JSON_UNESCAPED_UNICODE),
        'slug' => 'other-'.strtolower(substr($otherOrganizationId, -10)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $foreign = new WhatsappCampaign;
    $foreign->fill([
        'organization_id' => $otherOrganizationId,
        'name' => 'حملة مؤسسة أخرى',
        'body' => 'نص',
        'status' => WhatsappCampaignStatus::Draft,
        'reason' => 'اختبار',
        'delay_min_seconds' => 5,
        'delay_max_seconds' => 15,
        'total_recipients' => 0,
    ]);
    $foreign->save();

    expect($foreign->organization_id)->not->toBe($admin->organization_id);

    $this->actingAs($admin)
        ->getJson("/manage/whatsapp/campaigns/{$foreign->getKey()}")
        ->assertForbidden();

    $this->actingAs($admin)
        ->post("/manage/whatsapp/campaigns/{$foreign->getKey()}/start")
        ->assertForbidden();

    expect($foreign->refresh()->status)->toBe(WhatsappCampaignStatus::Draft);
});
