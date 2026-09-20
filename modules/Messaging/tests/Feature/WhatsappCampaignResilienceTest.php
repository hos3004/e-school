<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Messaging\Application\Actions\CreateWhatsappCampaignAction;
use Modules\Messaging\Application\Actions\StartWhatsappCampaignAction;
use Modules\Messaging\Application\Actions\StopWhatsappCampaignAction;
use Modules\Messaging\Application\Console\PruneWhatsappCampaignMedia;
use Modules\Messaging\Application\Console\SweepWhatsappCampaignDispatches;
use Modules\Messaging\Application\Jobs\SendWhatsappCampaignMessage;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignMedia;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Testing\Fixtures;

function resilienceChannel(bool $enabled = true): void
{
    $connections = Mockery::mock(GreenApiConnections::class);
    $connections->shouldReceive('isChannelEnabled')->andReturn($enabled);
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
function resilienceCampaign(array $rows, array $media = [], int $min = 5, int $max = 15): WhatsappCampaign
{
    return app(CreateWhatsappCampaignAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        actorId: Fixtures::userId(),
        name: 'حملة',
        body: 'نص',
        reason: 'اختبار',
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
        'messaging.campaigns.overdue_sweep_minutes' => 5,
        'notifications.channels.whatsapp.green_api.max_message_length' => 20000,
        'notifications.channels.whatsapp.green_api.timeout_seconds' => 5,
        'notifications.channels.whatsapp.green_api.retry_delays_milliseconds' => [],
    ]);
});

/*
 * سطر مات عامله بين نداء المزوّد وكتابة النتيجة يبقى محجوزًا sending. شبكة
 * الأمان لا تعيد إرساله — فالمزوّد ربما قبل رسالته — بل تغلقه interrupted،
 * وبذلك لا تبقى الحملة «جارية» إلى الأبد بانتظار سطر لن يتحرك.
 */
it('closes a send that was interrupted mid-flight and then finishes the campaign', function (): void {
    Queue::fake();
    resilienceChannel();

    $campaign = resilienceCampaign([['name' => 'أحمد', 'phone_input' => '+201012345678']]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    // محاكاة الانقطاع: السطر محجوز منذ وقت طويل ولم تُكتب له نتيجة.
    DB::table('whatsapp_campaign_recipients')->where('id', $recipient->getKey())->update([
        'status' => WhatsappCampaignRecipientStatus::Sending->value,
        'attempts' => 1,
        'updated_at' => CarbonImmutable::now('UTC')->subMinutes(30),
    ]);

    expect(Artisan::call(SweepWhatsappCampaignDispatches::class))->toBe(0);

    expect($recipient->refresh()->status)->toBe(WhatsappCampaignRecipientStatus::Failed)
        ->and($recipient->failure_reason)->toBe('interrupted')
        ->and($campaign->refresh()->status)->toBe(WhatsappCampaignStatus::Completed)
        ->and($campaign->failed_count)->toBe(1)
        ->and($campaign->media_expires_at)->not->toBeNull();

    // مهمة البدء وحدها؛ شبكة الأمان لم تُعد توزيع المنقطع.
    Queue::assertPushed(SendWhatsappCampaignMessage::class, 1);
});

/*
 * سطر فات موعده ولم تبدأ محاولته قط فُقدت مهمته: يُعاد توزيعه — وبتباعد جديد،
 * لا دفعةً واحدة، فإغراق المزوّد بعد انقطاع هو الحظر الذي وُجدت المهلة لتفاديه.
 */
it('re-dispatches lost messages with a fresh gap instead of firing them at once', function (): void {
    Queue::fake();
    resilienceChannel();

    $rows = [];
    for ($index = 1; $index <= 4; $index++) {
        $rows[] = ['name' => 'م'.$index, 'phone_input' => '+20100000000'.$index];
    }

    $campaign = resilienceCampaign($rows, min: 5, max: 9);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    DB::table('whatsapp_campaign_recipients')
        ->where('campaign_id', $campaign->getKey())
        ->update(['dispatch_after' => CarbonImmutable::now('UTC')->subMinutes(30)]);

    expect(Artisan::call(SweepWhatsappCampaignDispatches::class))->toBe(0);

    $moments = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->orderBy('dispatch_after')
        ->pluck('dispatch_after')
        ->all();

    for ($index = 1; $index < count($moments); $index++) {
        $gap = $moments[$index]->diffInSeconds($moments[$index - 1], absolute: true);

        expect($gap)->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(9);
    }

    // أربع من البدء، وأربع أعادت شبكة الأمان توزيعها.
    Queue::assertPushed(SendWhatsappCampaignMessage::class, 8);
});

/*
 * سباق شبكة الأمان مع المهمة: إن أُغلق السطر interrupted بينما المهمة تنادي
 * المزوّد، فنجاح النداء لا يبعثه حيًّا ولا يزيد عدّاد الإرسال — وإلا تجاوز
 * مجموع العدّادات عدد المستلمين إلى الأبد.
 */
it('does not resurrect a recipient the sweep already closed', function (): void {
    /*
     * بلا تزييف الطابور يسلّم البدءُ الرسالةَ فورًا في بيئة الاختبار، فيسبق
     * إغلاقَ السطر ويفسد ما نقيسه هنا.
     */
    Queue::fake();
    resilienceChannel();
    Http::fake(['*/sendMessage/*' => Http::response(['idMessage' => 'MSG-1'], 200)]);

    $campaign = resilienceCampaign([['name' => 'أحمد', 'phone_input' => '+201012345678']]);
    app(StartWhatsappCampaignAction::class)->execute($campaign);

    $recipient = WhatsappCampaignRecipient::query()
        ->where('campaign_id', $campaign->getKey())
        ->firstOrFail();

    // السطر أُغلق قبل أن تكتب المهمة نتيجتها.
    DB::table('whatsapp_campaign_recipients')->where('id', $recipient->getKey())->update([
        'status' => WhatsappCampaignRecipientStatus::Failed->value,
        'failure_reason' => 'interrupted',
        'attempts' => 1,
    ]);

    app()->call([new SendWhatsappCampaignMessage((string) $recipient->getKey()), 'handle']);

    expect($recipient->refresh()->status)->toBe(WhatsappCampaignRecipientStatus::Failed)
        ->and($campaign->refresh()->sent_count)->toBe(0);
});

/*
 * وعد «تُحذف بعد أسبوع» لا يستثني ما لم يُرسَل: مسودّة أُنشئت بمرفقاتها ولم
 * تُبدأ قط ليس لها موعد انتهاء، فلولا هذا بقيت ملفاتها على الخادم إلى الأبد.
 */
it('deletes the attachments of a draft campaign that was never started', function (): void {
    Storage::fake('local');

    $campaign = resilienceCampaign(
        [['name' => 'أحمد', 'phone_input' => '+201012345678']],
        [UploadedFile::fake()->image('poster.jpg')],
    );

    $file = WhatsappCampaignMedia::query()->where('campaign_id', $campaign->getKey())->firstOrFail();
    Storage::disk('local')->assertExists($file->path);

    expect(Artisan::call(PruneWhatsappCampaignMedia::class))->toBe(0);
    Storage::disk('local')->assertExists($file->path);

    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addDays(8));
    expect(Artisan::call(PruneWhatsappCampaignMedia::class))->toBe(0);
    CarbonImmutable::setTestNow();

    Storage::disk('local')->assertMissing($file->path);
    expect($campaign->refresh()->status)->toBe(WhatsappCampaignStatus::Draft);
});

it('writes an audit entry for creating, starting and stopping a campaign', function (): void {
    Queue::fake();
    resilienceChannel();

    $campaign = resilienceCampaign([
        ['name' => 'أ', 'phone_input' => '+201000000001'],
        ['name' => 'ب', 'phone_input' => '+201000000002'],
    ]);

    app(StartWhatsappCampaignAction::class)->execute($campaign, Fixtures::userId());
    app(StopWhatsappCampaignAction::class)
        ->execute($campaign, 'غلط في النص', Fixtures::userId());

    $actions = DB::table('audit_log')
        ->where('auditable_type', 'whatsapp_campaign')
        ->where('auditable_id', $campaign->getKey())
        ->pluck('action')
        ->all();

    expect($actions)->toContain('messaging.whatsapp_campaign_created')
        ->toContain('messaging.whatsapp_campaign_started')
        ->toContain('messaging.whatsapp_campaign_stopped');
});
