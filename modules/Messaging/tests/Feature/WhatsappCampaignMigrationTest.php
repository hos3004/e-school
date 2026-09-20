<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Messaging\Application\Services\CampaignPhoneNormalizer;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Testing\Fixtures;

/**
 * هجرات الحملات تُنشئ وتتراجع وتُعاد على قاعدة فيها بيانات، لا على فارغة فقط.
 *
 * الهجرات الثلاث هي الأحدث في المشروع، فالتراجع بثلاث خطوات يخصّها وحدها.
 */
it('rolls back and re-applies the campaign tables without harming existing rows', function (): void {
    $organizationId = Fixtures::organizationId();
    $actorId = Fixtures::userId();
    $existingUsers = DB::table('users')->count();

    $campaign = new WhatsappCampaign;
    $campaign->fill([
        'organization_id' => $organizationId,
        'created_by' => $actorId,
        'name' => 'حملة قبل التراجع',
        'body' => 'نص',
        'status' => WhatsappCampaignStatus::Draft,
        'reason' => 'اختبار الهجرة',
        'delay_min_seconds' => 5,
        'delay_max_seconds' => 15,
        'total_recipients' => 1,
    ]);
    $campaign->save();

    $recipient = new WhatsappCampaignRecipient;
    $recipient->fill([
        'campaign_id' => $campaign->getKey(),
        'organization_id' => $organizationId,
        'name' => 'أحمد',
        'phone_input' => '+201012345678',
        'phone' => '+201012345678',
        'status' => WhatsappCampaignRecipientStatus::Pending,
    ]);
    $recipient->save();

    Artisan::call('migrate:rollback', ['--step' => 3, '--force' => true]);

    expect(Schema::hasTable('whatsapp_campaigns'))->toBeFalse()
        ->and(Schema::hasTable('whatsapp_campaign_recipients'))->toBeFalse()
        ->and(Schema::hasTable('whatsapp_campaign_media'))->toBeFalse()
        // التراجع يمسّ جداول الحملات وحدها ولا يلمس بيانات المنصة.
        ->and(DB::table('users')->count())->toBe($existingUsers);

    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasTable('whatsapp_campaigns'))->toBeTrue()
        ->and(Schema::hasTable('whatsapp_campaign_recipients'))->toBeTrue()
        ->and(Schema::hasTable('whatsapp_campaign_media'))->toBeTrue()
        ->and(WhatsappCampaign::query()->count())->toBe(0);
});

/*
 * القيد يمنع رسالتين متطابقتين لنفس الرقم داخل الحملة، ولا يمنع تسجيل عدة
 * أرقام مرفوضة (phone = NULL) في الحملة نفسها.
 */
it('refuses the same number twice in one campaign but allows many rejected rows', function (): void {
    $organizationId = Fixtures::organizationId();

    $campaign = new WhatsappCampaign;
    $campaign->fill([
        'organization_id' => $organizationId,
        'name' => 'قيد التكرار',
        'body' => 'نص',
        'status' => WhatsappCampaignStatus::Draft,
        'reason' => 'اختبار القيد',
        'delay_min_seconds' => 5,
        'delay_max_seconds' => 15,
        'total_recipients' => 0,
    ]);
    $campaign->save();

    $add = function (?string $phone) use ($campaign, $organizationId): void {
        $recipient = new WhatsappCampaignRecipient;
        $recipient->fill([
            'campaign_id' => $campaign->getKey(),
            'organization_id' => $organizationId,
            'phone_input' => $phone ?? '01012345678',
            'phone' => $phone,
            'status' => $phone === null
                ? WhatsappCampaignRecipientStatus::Invalid
                : WhatsappCampaignRecipientStatus::Pending,
        ]);
        $recipient->save();
    };

    $add('+201012345678');
    $add(null);
    $add(null);

    expect(WhatsappCampaignRecipient::query()->where('campaign_id', $campaign->getKey())->count())->toBe(3);

    expect(fn () => $add('+201012345678'))->toThrow(QueryException::class);
});

it('stores a phone the normalizer accepts within the column width', function (): void {
    // أطول رقم يقبله E.164: 15 رقمًا وعلامة — والعمود 32 محرفًا.
    $phone = (new CampaignPhoneNormalizer)->normalize('+123456789012345')['phone'];

    expect($phone)->toBe('+123456789012345')
        ->and(strlen((string) $phone))->toBeLessThanOrEqual(32);
});
