<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Messaging\Application\Actions\CreateWhatsappCampaignAction;
use Modules\Messaging\Application\Actions\StartWhatsappCampaignAction;
use Modules\Messaging\Application\Actions\StopWhatsappCampaignAction;
use Modules\Messaging\Application\Console\PruneWhatsappCampaignMedia;
use Modules\Messaging\Application\Jobs\SendWhatsappCampaignMessage;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignMedia;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

/**
 * اتصال واتساب مزيّف: القناة تعمل وبيانات الاعتماد موجودة، بلا أي نداء حقيقي.
 */
function fakeWhatsappConnection(bool $channelEnabled = true): void
{
    $connections = Mockery::mock(GreenApiConnections::class);
    $connections->shouldReceive('isChannelEnabled')->andReturn($channelEnabled);
    $connections->shouldReceive('credentials')->andReturn([
        'api_url' => 'https://api.green-api.com',
        'instance_id' => '1101',
        'token' => 'test-token',
    ]);

    app()->instance(GreenApiConnections::class, $connections);
}

/**
 * @param list<array{name: string|null, phone_input: string}> $rows
 * @param list<UploadedFile> $media
 */
function makeCampaign(array $rows, array $media = [], int $min = 5, int $max = 15): WhatsappCampaign
{
    return app(CreateWhatsappCampaignAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        actorId: Fixtures::userId(),
        name: 'دورة سبتمبر',
        body: 'أهلًا {الاسم}، الدورة بدأت',
        reason: 'دعوة المسجلين في الدورة',
        rows: $rows,
        media: $media,
        delayMinSeconds: $min,
        delayMaxSeconds: $max,
    );
}

beforeEach(function (): void {
    config([
        'messaging.campaigns.placeholders' => ['{الاسم}', '{name}'],
        'messaging.campaigns.media.disk' => 'local',
        'messaging.campaigns.media.retention_days' => 7,
        'notifications.channels.whatsapp.green_api.max_message_length' => 20000,
        'notifications.channels.whatsapp.green_api.timeout_seconds' => 5,
        'notifications.channels.whatsapp.green_api.retry_delays_milliseconds' => [],
    ]);
});

it('keeps the rejected numbers inside the campaign instead of dropping them', function (): void {
    $campaign = makeCampaign([
        ['name' => 'أحمد', 'phone_input' => '+201012345678'],
        ['name' => 'سارة', 'phone_input' => '01112223344'],
    ]);

    expect($campaign->status)->toBe(WhatsappCampaignStatus::Draft)
        ->and($campaign->total_recipients)->toBe(1);

    $rejected = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->where('status', WhatsappCampaignRecipientStatus::Invalid)
        ->first();

    expect($rejected?->name)->toBe('سارة')
        ->and($rejected?->phone_input)->toBe('01112223344')
        ->and($rejected?->failure_reason)->toBe('phone_missing_country_code');
});

it('refuses a campaign whose list holds no usable number', function (): void {
    expect(fn (): WhatsappCampaign => makeCampaign([
        ['name' => 'سارة', 'phone_input' => '01112223344'],
    ]))->toThrow(BusinessRuleViolation::class);
});

/*
 * جوهر الطلب: الرسائل لا تخرج دفعة واحدة. لكل مستلم موعد يبعد عن سابقه بمدة
 * داخل المدى الذي اختاره المرسِل، والأول وحده يخرج فورًا.
 */
it('spaces every recipient apart by a gap inside the chosen range', function (): void {
    Bus::fake();
    fakeWhatsappConnection();

    $campaign = makeCampaign([
        ['name' => 'أ', 'phone_input' => '+201000000001'],
        ['name' => 'ب', 'phone_input' => '+201000000002'],
        ['name' => 'ج', 'phone_input' => '+201000000003'],
        ['name' => 'د', 'phone_input' => '+201000000004'],
    ], min: 5, max: 9);

    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $moments = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->orderBy('dispatch_after')
        ->pluck('dispatch_after')
        ->all();

    expect($moments)->toHaveCount(4);

    for ($index = 1; $index < count($moments); $index++) {
        $gap = $moments[$index]->diffInSeconds($moments[$index - 1], absolute: true);

        expect($gap)->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(9);
    }

    Bus::assertDispatchedTimes(SendWhatsappCampaignMessage::class, 4);
    expect($campaign->refresh()->status)->toBe(WhatsappCampaignStatus::Running);
});

it('will not start while the whatsapp channel is switched off', function (): void {
    Bus::fake();
    fakeWhatsappConnection(channelEnabled: false);

    $campaign = makeCampaign([['name' => 'أ', 'phone_input' => '+201000000001']]);

    expect(fn (): WhatsappCampaign => app(StartWhatsappCampaignAction::class)->execute($campaign))
        ->toThrow(BusinessRuleViolation::class);

    Bus::assertNothingDispatched();
});

it('sends the text with the recipient name filled in', function (): void {
    fakeWhatsappConnection();
    Http::fake([
        '*/sendMessage/*' => Http::response(['idMessage' => 'MSG-1'], 200),
    ]);

    $campaign = makeCampaign([['name' => 'أحمد', 'phone_input' => '+201012345678']]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'sendMessage')
            && $request['chatId'] === '201012345678@c.us'
            && $request['message'] === 'أهلًا أحمد، الدورة بدأت';
    });

    expect($recipient->refresh()->status)->toBe(WhatsappCampaignRecipientStatus::Sent)
        ->and($recipient->external_message_id)->toBe('MSG-1')
        ->and($campaign->refresh()->sent_count)->toBe(1)
        ->and($campaign->status)->toBe(WhatsappCampaignStatus::Completed);
});

/*
 * المرفقات تُرفع رفعًا مباشرًا إلى المزوّد ثم يتبعها النص رسالةً مستقلة، فلا
 * يُقصّ نص الحملة في حقل تعليقٍ حدُّه أقصر.
 */
it('uploads each attachment before the text', function (): void {
    Storage::fake('local');
    fakeWhatsappConnection();
    Http::fake([
        '*/sendFileByUpload/*' => Http::response(['idMessage' => 'FILE-1'], 200),
        '*/sendMessage/*' => Http::response(['idMessage' => 'MSG-1'], 200),
    ]);

    $campaign = makeCampaign(
        [['name' => 'أحمد', 'phone_input' => '+201012345678']],
        [UploadedFile::fake()->image('poster.jpg'), UploadedFile::fake()->image('second.png')],
    );

    expect(WhatsappCampaignMedia::query()->where('campaign_id', $campaign->getKey())->count())->toBe(2);

    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    Http::assertSentCount(3);
    expect($recipient->refresh()->status)->toBe(WhatsappCampaignRecipientStatus::Sent);
});

it('cancels a recipient whose turn arrives after the channel was switched off', function (): void {
    Bus::fake();
    fakeWhatsappConnection();

    $campaign = makeCampaign([['name' => 'أحمد', 'phone_input' => '+201012345678']]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    // المفتاح ضُغط بعد بدء الحملة وقبل أن يأتي دور هذا المستلم.
    fakeWhatsappConnection(channelEnabled: false);
    Http::fake();

    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    Http::assertNothingSent();
    expect($recipient->refresh()->status)->toBe(WhatsappCampaignRecipientStatus::Cancelled)
        ->and($recipient->failure_reason)->toBe('channel_disabled');
});

it('records a provider rejection against the recipient without failing the rest', function (): void {
    fakeWhatsappConnection();
    Http::fake([
        '*/sendMessage/*' => Http::response(['message' => 'rate limited'], 429),
    ]);

    $campaign = makeCampaign([['name' => 'أحمد', 'phone_input' => '+201012345678']]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    expect($recipient->refresh()->status)->toBe(WhatsappCampaignRecipientStatus::Failed)
        ->and($recipient->failure_reason)->toBe('rate limited')
        ->and($campaign->refresh()->failed_count)->toBe(1);
});

/*
 * توزيعان لنفس المستلم — من شبكة الأمان ومن مهمته الأصلية — يجب ألا يعنيا
 * رسالتين لنفس الشخص.
 */
it('never sends twice when the same recipient is dispatched twice', function (): void {
    fakeWhatsappConnection();
    Http::fake([
        '*/sendMessage/*' => Http::response(['idMessage' => 'MSG-1'], 200),
    ]);

    $campaign = makeCampaign([['name' => 'أحمد', 'phone_input' => '+201012345678']]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);
    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    Http::assertSentCount(1);
});

it('cancels whoever is still waiting when the campaign is stopped', function (): void {
    Bus::fake();
    fakeWhatsappConnection();

    $campaign = makeCampaign([
        ['name' => 'أ', 'phone_input' => '+201000000001'],
        ['name' => 'ب', 'phone_input' => '+201000000002'],
    ]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $campaign = app(StopWhatsappCampaignAction::class)->execute($campaign, 'غلط في النص');

    expect($campaign->status)->toBe(WhatsappCampaignStatus::Stopped)
        ->and(WhatsappCampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('status', WhatsappCampaignRecipientStatus::Cancelled)
            ->count())->toBe(2);
});

it('deletes the attachments once the retention period has passed', function (): void {
    Storage::fake('local');
    fakeWhatsappConnection();
    Http::fake(['*' => Http::response(['idMessage' => 'MSG-1'], 200)]);

    $campaign = makeCampaign(
        [['name' => 'أحمد', 'phone_input' => '+201012345678']],
        [UploadedFile::fake()->image('poster.jpg')],
    );
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();
    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    $file = WhatsappCampaignMedia::query()->where('campaign_id', $campaign->getKey())->firstOrFail();
    Storage::disk('local')->assertExists($file->path);

    // اليوم السابع لم يحن بعد: المرفق يبقى.
    expect(Artisan::call(PruneWhatsappCampaignMedia::class))->toBe(0);
    Storage::disk('local')->assertExists($file->path);

    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addDays(8));
    expect(Artisan::call(PruneWhatsappCampaignMedia::class))->toBe(0);
    CarbonImmutable::setTestNow();

    Storage::disk('local')->assertMissing($file->path);
    expect($file->refresh()->deleted_file_at)->not->toBeNull()
        ->and($campaign->refresh()->media_pruned_at)->not->toBeNull();
});
