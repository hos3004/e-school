<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupDisplayMode;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Modules\Notifications\Domain\Models\PopupCampaignUserState;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * تغطية Phase 1 للميديا ونمط العرض والإغلاق التلقائي واستثناء الجمهور
 * ومسار الموبايل عبر Sanctum. مكمِّل لـPopupCampaignTest.php الحالي، ولا
 * يكرر تغطيته.
 */
final class PopupCampaignExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_fields_round_trip(): void
    {
        $organization = Organization::factory()->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'display_mode' => PopupDisplayMode::Fullscreen,
            'excluded_audiences' => ['teacher'],
            'auto_dismiss_seconds' => 12,
            'links' => [['text' => 'رابط خارجي', 'url' => 'https://example.test/x']],
        ]));

        $fresh = PopupCampaign::query()->findOrFail($campaign->getKey());

        self::assertSame(PopupDisplayMode::Fullscreen, $fresh->display_mode);
        self::assertSame(['teacher'], $fresh->excluded_audiences);
        self::assertSame(12, $fresh->auto_dismiss_seconds);
        self::assertCount(1, $fresh->links);
        self::assertSame('رابط خارجي', $fresh->links[0]['text']);
        self::assertSame('https://example.test/x', $fresh->links[0]['url']);
        // العمود الجديد له قيمة افتراضية ولا يكسر حملة قديمة لم تحدده صراحة.
        self::assertNotNull($fresh->display_mode);
    }

    public function test_display_mode_defaults_to_bottom_banner_for_existing_style_inserts(): void
    {
        $organization = Organization::factory()->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));

        self::assertSame(PopupDisplayMode::BottomBanner, $campaign->fresh()->display_mode);
    }

    // ------------------------------------------------------------------
    // استثناء الجمهور — يفوز دائمًا على المطابقة الموجبة
    // ------------------------------------------------------------------

    public function test_excluded_audience_blocks_an_otherwise_matching_campaign(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'excluded_audiences' => ['teacher'],
        ]));

        $popupForTeacher = app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $user->id,
            userAudiences: ['teacher'],
            placement: 'after_login',
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        $popupForStudent = app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $user->id,
            userAudiences: ['student'],
            placement: 'after_login',
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        self::assertNull($popupForTeacher);
        self::assertNotNull($popupForStudent);
    }

    // ------------------------------------------------------------------
    // استثناء الجمهور كحد وصول حقيقي — لا مجرد تفضيل عرض.
    // يغطي ShowPopupAttachmentController ونقطة interact() معًا: مستخدم
    // من نفس المؤسسة لا يطابق audiences الحملة (أو مُستثنى منها صراحة)
    // يُرفض من كليهما، رغم مطابقة المؤسسة والحالة.
    // ------------------------------------------------------------------

    public function test_show_attachment_rejects_a_same_organization_user_not_in_the_campaign_audience(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['teacher'],
            'status' => PopupCampaignStatus::Published,
            'created_by' => (string) $admin->id,
        ]));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/note.jpg';
        Storage::disk($disk)->put($path, 'fake-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'note.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'position' => 1,
        ]);

        // نفس المؤسسة، دور غير مستهدف بالحملة (student ≠ teacher).
        $student = User::factory()->inOrganization((string) $organization->id)->create();
        $this->assignAudienceRole($student, 'student');

        $this->actingAs($student)
            ->get("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}")
            ->assertNotFound();
    }

    public function test_show_attachment_rejects_a_user_explicitly_excluded_from_the_campaign(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'excluded_audiences' => ['teacher'],
            'status' => PopupCampaignStatus::Published,
            'created_by' => (string) $admin->id,
        ]));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/note.jpg';
        Storage::disk($disk)->put($path, 'fake-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'note.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'position' => 1,
        ]);

        // يطابق all_authenticated لولا الاستثناء الصريح لهذا الدور.
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $this->assignAudienceRole($teacher, 'teacher');

        $this->actingAs($teacher)
            ->get("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}")
            ->assertNotFound();
    }

    public function test_show_attachment_allows_a_recipient_actually_matching_the_campaign_audience(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['teacher'],
            'status' => PopupCampaignStatus::Published,
            'created_by' => (string) $admin->id,
        ]));

        $disk = (string) config('popups.attachments.disk');
        $path = 'popups/'.$campaign->getKey().'/note.jpg';
        Storage::disk($disk)->put($path, 'fake-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'note.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'position' => 1,
        ]);

        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $this->assignAudienceRole($teacher, 'teacher');

        $this->actingAs($teacher)
            ->get("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}")
            ->assertOk();
    }

    public function test_interact_endpoint_silently_rejects_a_user_not_in_the_campaign_audience(): void
    {
        $organization = Organization::factory()->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['teacher'],
        ]));

        $student = User::factory()->inOrganization((string) $organization->id)->create();
        $this->assignAudienceRole($student, 'student');

        $this->actingAs($student)
            ->postJson("/api/popups/{$campaign->getKey()}/impression")
            ->assertStatus(204);

        self::assertSame(0, PopupCampaignUserState::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('user_id', $student->getKey())
            ->count());
    }

    public function test_interact_endpoint_silently_rejects_a_user_excluded_from_the_campaign(): void
    {
        $organization = Organization::factory()->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'excluded_audiences' => ['teacher'],
        ]));

        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $this->assignAudienceRole($teacher, 'teacher');

        $this->actingAs($teacher)
            ->postJson("/api/popups/{$campaign->getKey()}/impression")
            ->assertStatus(204);

        self::assertSame(0, PopupCampaignUserState::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('user_id', $teacher->getKey())
            ->count());
    }

    public function test_interact_endpoint_still_succeeds_for_an_eligible_recipient(): void
    {
        $organization = Organization::factory()->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['teacher'],
        ]));

        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $this->assignAudienceRole($teacher, 'teacher');

        $this->actingAs($teacher)
            ->postJson("/api/popups/{$campaign->getKey()}/impression")
            ->assertOk()
            ->assertJson(['ok' => true]);

        self::assertSame(1, PopupCampaignUserState::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('user_id', $teacher->getKey())
            ->count());
    }

    // ------------------------------------------------------------------
    // المخرج الآمن للإغلاق التلقائي وحده يكفي
    // (PopupCampaign::hasSafeExit() وSavePopupCampaignAction يجب ألا يفترقا).
    // ------------------------------------------------------------------

    public function test_save_action_accepts_a_campaign_with_only_auto_dismiss_seconds_as_its_safe_exit(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = app(SavePopupCampaignAction::class)->execute(
            campaign: null,
            organizationId: (string) $organization->id,
            attributes: [
                'internal_name' => 'auto-dismiss-only',
                'type' => PopupType::General->value,
                'title' => ['ar' => 'عنوان تجريبي'],
                'body' => ['ar' => 'نص تجريبي'],
                'audiences' => ['student'],
                'placement' => PopupPlacement::AfterLogin->value,
                'page_key' => null,
                'frequency' => 'once',
                // لا إغلاق يدوي ولا إقرار — المخرج الآمن الوحيد هو
                // الإغلاق التلقائي.
                'is_dismissible' => false,
                'requires_acknowledgement' => false,
                'auto_dismiss_seconds' => 10,
                'priority' => 5,
                'starts_at' => now('UTC')->addMinute()->format('Y-m-d H:i:s'),
                'ends_at' => now('UTC')->addWeek()->format('Y-m-d H:i:s'),
                'action_type' => null,
                'action_target' => null,
            ],
            scheduleChanges: null,
            actorId: (string) $actor->id,
            reason: 'حملة إغلاق تلقائي فقط',
        );

        self::assertFalse($campaign->is_dismissible);
        self::assertFalse($campaign->requires_acknowledgement);
        self::assertSame(10, $campaign->auto_dismiss_seconds);
        self::assertTrue($campaign->hasSafeExit());
    }

    // ------------------------------------------------------------------
    // رفع الميديا — قبول ورفض حسب النوع
    // ------------------------------------------------------------------

    public function test_admin_can_upload_a_valid_image_for_the_campaign(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, ['created_by' => (string) $admin->id]));

        $image = UploadedFile::fake()->image('note.jpg', 100, 100)->size(50);

        $response = $this->actingAs($admin)->postJson("/api/popups/{$campaign->getKey()}/media", [
            'kind' => 'image',
            'file' => $image,
        ]);

        $response->assertCreated();
        self::assertSame(1, PopupCampaignMedia::query()->where('campaign_id', $campaign->getKey())->count());

        $media = PopupCampaignMedia::query()->where('campaign_id', $campaign->getKey())->sole();
        self::assertSame('image', $media->kind);
        self::assertSame((string) config('popups.attachments.disk'), $media->disk);
        self::assertStringStartsWith('popups/'.$campaign->getKey().'/', $media->path);
        Storage::disk($media->disk)->assertExists($media->path);
    }

    public function test_upload_rejects_a_file_exceeding_the_configured_size_for_its_kind(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, ['created_by' => (string) $admin->id]));

        $maxKb = (int) config('popups.attachments.image.max_size_kilobytes');
        $tooLarge = UploadedFile::fake()->image('big.jpg')->size($maxKb + 500);

        $this->actingAs($admin)->postJson("/api/popups/{$campaign->getKey()}/media", [
            'kind' => 'image',
            'file' => $tooLarge,
        ])->assertUnprocessable();

        self::assertSame(0, PopupCampaignMedia::query()->count());
    }

    public function test_upload_rejects_a_mime_type_not_allowed_for_the_declared_kind(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, ['created_by' => (string) $admin->id]));

        // ملف نصي معلن كأنه "image" — يجب أن يُرفض قبل لمس أي منطق تخزين.
        $file = UploadedFile::fake()->createWithContent('note.txt', 'plain text content');

        $this->actingAs($admin)->postJson("/api/popups/{$campaign->getKey()}/media", [
            'kind' => 'image',
            'file' => $file,
        ])->assertUnprocessable();

        self::assertSame(0, PopupCampaignMedia::query()->count());
    }

    public function test_upload_requires_popup_campaign_update_permission(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $stranger = User::factory()->inOrganization((string) $organization->id)->create();
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));

        $image = UploadedFile::fake()->image('note.jpg', 100, 100)->size(50);

        $this->actingAs($stranger)->postJson("/api/popups/{$campaign->getKey()}/media", [
            'kind' => 'image',
            'file' => $image,
        ])->assertForbidden();
    }

    // ------------------------------------------------------------------
    // عرض المرفق — نفس فحص بادئة المسار المستخدم في Messaging
    // (ShowWallAttachmentController) لمنع IDOR.
    // ------------------------------------------------------------------

    public function test_show_attachment_rejects_a_path_not_matching_the_expected_campaign_prefix(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'status' => PopupCampaignStatus::Published,
            'created_by' => (string) $admin->id,
        ]));

        // صف مُفبرك كما لو أن كتابة قديمة/استيرادًا خزّن مسارًا خارج مجلد
        // هذه الحملة بالذات — المتحكم يجب أن يتجاهله بصرف النظر عن مصدره.
        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/some-other-campaign/private.jpg',
            'original_name' => 'private.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'position' => 1,
        ]);

        $this->actingAs($admin)
            ->get("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}")
            ->assertNotFound();
    }

    public function test_show_attachment_is_visible_to_an_eligible_recipient_without_admin_permissions(): void
    {
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $this->allowPopupUpdateFor($admin);
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'status' => PopupCampaignStatus::Published,
            'created_by' => (string) $admin->id,
        ]));

        $disk = (string) config('popups.attachments.disk');
        $directory = trim((string) config('popups.attachments.directory'), '/').'/'.$campaign->getKey();
        $path = $directory.'/note.jpg';
        Storage::disk($disk)->put($path, 'fake-bytes');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'note.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'position' => 1,
        ]);

        $recipient = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($recipient)
            ->get("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}")
            ->assertOk();

        $outsider = User::factory()->inOrganization((string) Organization::factory()->create()->id)->create();

        $this->actingAs($outsider)
            ->get("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}")
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // رابط التنزيل الموقَّع — ينتهي، ويُرفض لمستخدم مختلف.
    // ------------------------------------------------------------------

    public function test_signed_download_url_expires_after_its_ttl(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 10:00:00', 'UTC'));

        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));
        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$campaign->getKey().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'position' => 1,
        ]);

        $url = URL::temporarySignedRoute(
            'popups.media.download',
            CarbonImmutable::now('UTC')->addMinutes(5),
            ['campaign' => (string) $campaign->getKey(), 'media' => (string) $media->getKey(), 'user' => (string) $user->getKey()],
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 10:06:00', 'UTC'));

        $this->get($url)->assertForbidden();

        CarbonImmutable::setTestNow();
    }

    public function test_signed_download_url_rejects_a_swapped_user_id(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->inOrganization((string) $organization->id)->create();
        $attacker = User::factory()->inOrganization((string) $organization->id)->create();
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization));
        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$campaign->getKey().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'position' => 1,
        ]);

        $url = URL::temporarySignedRoute(
            'popups.media.download',
            CarbonImmutable::now('UTC')->addMinutes(5),
            ['campaign' => (string) $campaign->getKey(), 'media' => (string) $media->getKey(), 'user' => (string) $owner->getKey()],
        );

        // استبدال قيمة user فقط — التوقيع محسوب على القيمة الأصلية، فيبطل فورًا.
        $tampered = str_replace((string) $owner->getKey(), (string) $attacker->getKey(), $url);

        $this->get($tampered)->assertForbidden();
    }

    public function test_signed_download_url_serves_the_file_when_valid(): void
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

        $url = URL::temporarySignedRoute(
            'popups.media.download',
            CarbonImmutable::now('UTC')->addMinutes(5),
            ['campaign' => (string) $campaign->getKey(), 'media' => (string) $media->getKey(), 'user' => (string) $user->getKey()],
        );

        $this->get($url)->assertOk();
    }

    // ------------------------------------------------------------------
    // طلب رابط التنزيل (App\Http\Controllers\Api\PopupMessageController) —
    // يعيش في app/ لا داخل الموديول لأنه يحتاج حلّ modelType عبر Identity.
    // ------------------------------------------------------------------

    public function test_eligible_user_can_request_a_download_url_for_a_file_medium(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::Dashboard,
        ]));

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$campaign->getKey().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'position' => 1,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/popups/{$campaign->getKey()}/media/{$media->getKey()}/download-request?placement=dashboard",
        );

        $response->assertOk();
        self::assertIsString($response->json('url'));
        self::assertStringContainsString('/media/'.$media->getKey().'/download/', $response->json('url'));
    }

    public function test_download_request_is_denied_for_a_campaign_not_addressed_to_the_user(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        // الحملة موجودة لكنها ليست في نافذة العرض (بدأت مستقبلًا) — غير
        // مؤهلة لهذا المستخدم الآن رغم مطابقة الجمهور نظريًا.
        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::Dashboard,
            'starts_at' => now('UTC')->addDay(),
            'ends_at' => null,
        ]));

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$campaign->getKey().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'position' => 1,
        ]);

        $this->actingAs($user)
            ->postJson("/api/popups/{$campaign->getKey()}/media/{$media->getKey()}/download-request?placement=dashboard")
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // مسار الموبايل (Sanctum) يطابق مسار الجلسة لنفس المستخدم.
    // ------------------------------------------------------------------

    public function test_mobile_active_endpoint_matches_the_session_endpoint_for_the_same_user(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::Dashboard,
        ]));

        $session = $this->actingAs($user)->getJson('/popups/active?placement=dashboard')->assertOk();
        $mobile = $this->actingAs($user)->getJson('/api/popups/active?placement=dashboard')->assertOk();

        self::assertNotNull($session->json('popup.id'));
        self::assertSame($session->json('popup.id'), $mobile->json('popup.id'));
        self::assertSame($session->json('popup.display_mode'), $mobile->json('popup.display_mode'));
        self::assertSame($session->json('popup.title'), $mobile->json('popup.title'));
    }

    public function test_mobile_interact_endpoint_records_the_same_state_as_the_session_endpoint(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        $campaign = PopupCampaign::query()->create($this->baseAttributes($organization, [
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::Dashboard,
        ]));

        $this->actingAs($user)
            ->postJson("/api/popups/{$campaign->getKey()}/impression")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $state = PopupCampaignUserState::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('user_id', $user->getKey())
            ->sole();

        self::assertNotNull($state->first_seen_at);
    }

    public function test_mobile_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/popups/active')->assertUnauthorized();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function baseAttributes(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'ext-camp-'.str()->random(6),
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

    private function allowPopupUpdateFor(User $actor): void
    {
        $this->seed(AccessControlSeeder::class);

        $permission = Permission::query()->where('name', 'popup_campaign.update')->firstOrFail();
        ModelHasPermission::query()->create([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $actor->getMorphClass(),
            'model_id' => (string) $actor->getAuthIdentifier(),
        ]);

        app(PermissionGateRegistrar::class)->register();
    }

    /**
     * يمنح المستخدم دورًا نظاميًا عبر AccessControl لكي يحلّه
     * AccessControlPopupAudienceResolver إلى قيمة PopupAudience المقابلة
     * (نفس المسار المستخدم في PopupCampaignTest::test_audience_resolver_maps_access_control_roles).
     */
    private function assignAudienceRole(User $user, string $roleName): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['organization_id' => null, 'is_system' => true],
        );

        ModelHasRole::query()->create([
            'role_id' => (string) $role->getKey(),
            'model_type' => app(UserQueryService::class)->modelType(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);
    }
}
