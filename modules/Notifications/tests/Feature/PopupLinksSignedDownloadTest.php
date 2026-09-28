<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * الثغرة التي يغطيها هذا الملف: تطبيق الموبايل (Phase 3، مبنيّ خارج هذا
 * المستودع) يفتح كل links[].url بوضع خارجي (url_launcher external mode) عبر
 * مدير تنزيل نظام أندرويد مباشرة — قرار متعمد كي تمر الملفات عبر مدير
 * التنزيل القياسي لا جلبًا داخليًا موثقًا. رابط خارجي كهذا لا يحمل ترويسة
 * Authorization ولا جلسة، فكان أي popup-media:{id} (ملف قابل للتنزيل حصرًا
 * وفق تحقق SavePopupCampaignAction) يُحل قبل هذا الإصلاح إلى مسار
 * popups.media.show المحمي بـSanctum/جلسة — يفشل حتمًا بـ401 عند الضغط
 * الفعلي من المستخدم، ويُبطل ميزة "كلمة قابلة للنقر تُنزّل ملفًا" بالكامل.
 *
 * الإصلاح: EloquentPopupQueryService::resolveLinks() يبني الآن رابط تنزيل
 * موقَّعًا قصير العمر (نفس Modules\Notifications\Application\Services\
 * PopupMediaDownloadUrlSigner ونفس route popups.media.download خلف
 * middleware `signed` التي يستخدمها مسار media[] القائم أصلًا) بدل الرابط
 * المحمي بجلسة/Sanctum — يعمل مع فتح خارجي بلا أي ترويسة.
 */
final class PopupLinksSignedDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_popup_media_link_resolves_to_a_signed_url_requiring_no_authentication(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/doc.pdf';
        Storage::disk($disk)->put($path, 'pdf-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 9,
            'position' => 1,
        ]);

        $campaign->forceFill([
            'links' => [['text' => 'حمّل الملف', 'url' => 'popup-media:'.$media->getKey()]],
        ])->save();

        $popup = app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $user->getKey(),
            userAudiences: ['student'],
            placement: PopupPlacement::AfterLogin->value,
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        self::assertNotNull($popup);
        self::assertCount(1, $popup->links);

        $url = $popup->links[0]['url'];

        // شكل الرابط الجديد: مسار popups.media.download الموقَّع، وليس
        // popups.media.show المحمي بجلسة/Sanctum.
        self::assertStringContainsString('/media/'.$media->getKey().'/download/'.$user->getKey(), $url);
        self::assertStringContainsString('signature=', $url);

        // بلا actingAs عمدًا: يحاكي فتحًا خارجيًا بلا جلسة أو Authorization.
        $this->get($url)->assertOk();
    }

    public function test_a_popup_media_link_url_rejects_a_swapped_user_id(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $owner = User::factory()->inOrganization((string) $organization->id)->create();
        $attacker = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/doc.pdf';
        Storage::disk($disk)->put($path, 'pdf-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 9,
            'position' => 1,
        ]);

        $campaign->forceFill([
            'links' => [['text' => 'حمّل الملف', 'url' => 'popup-media:'.$media->getKey()]],
        ])->save();

        $popup = app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $owner->getKey(),
            userAudiences: ['student'],
            placement: PopupPlacement::AfterLogin->value,
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        self::assertNotNull($popup);
        $url = $popup->links[0]['url'];

        // استبدال معرّف المستخدم فقط — التوقيع محسوب على القيمة الأصلية،
        // فيبطل فورًا. لا جلسة/actingAs هنا أصلًا، فهذا يحاكي مهاجمًا
        // يعدّل الرابط الملتقط من فتح خارجي.
        $tampered = str_replace((string) $owner->getKey(), (string) $attacker->getKey(), $url);

        $this->get($tampered)->assertForbidden();
    }

    public function test_a_popup_media_link_url_expires_after_its_ttl(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-29 09:00:00', 'UTC'));

        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/doc.pdf';
        Storage::disk($disk)->put($path, 'pdf-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 9,
            'position' => 1,
        ]);

        $campaign->forceFill([
            'links' => [['text' => 'حمّل الملف', 'url' => 'popup-media:'.$media->getKey()]],
        ])->save();

        $popup = app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $user->getKey(),
            userAudiences: ['student'],
            placement: PopupPlacement::AfterLogin->value,
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        self::assertNotNull($popup);
        $url = $popup->links[0]['url'];

        $ttl = (int) config('popups.attachments.download_link_ttl_minutes');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-29 09:00:00', 'UTC')->addMinutes($ttl + 1));

        $this->get($url)->assertForbidden();

        CarbonImmutable::setTestNow();
    }

    public function test_a_popup_media_link_to_a_non_file_kind_medium_is_never_resolved(): void
    {
        // SavePopupCampaignAction يمنع أصلًا حفظ رابط لميديا غير file، لكن
        // resolveLinks() نفسها يجب ألا تثق بقيمة links المخزَّنة دون تحقق
        // مستقل — دفاع بعمق، لا اعتماد كليًا على طبقة التحقق عند الحفظ.
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/note.jpg';
        Storage::disk($disk)->put($path, 'fake-bytes');

        $image = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'note.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'position' => 1,
        ]);

        $campaign->forceFill([
            'links' => [['text' => 'صورة غير صالحة كرابط', 'url' => 'popup-media:'.$image->getKey()]],
        ])->save();

        $popup = app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $user->getKey(),
            userAudiences: ['student'],
            placement: PopupPlacement::AfterLogin->value,
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        self::assertNotNull($popup);
        self::assertSame([], $popup->links);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function baseAttributes(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'links-signed-'.str()->random(6),
            'type' => 'general',
            'status' => PopupCampaignStatus::Published,
            'priority' => 5,
            'title' => ['ar' => 'عنوان تجريبي', 'en' => 'Demo title'],
            'body' => ['ar' => 'نص تجريبي', 'en' => 'Demo body'],
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => 'once',
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDays(7),
        ], $overrides);
    }
}
