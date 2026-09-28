<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupDisplayMode;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * Phase 2: مدخل /manage/popup-messages. يغطي بوابة الصلاحيات، ودورة حياة
 * كاملة عبر هذا المدخل الجديد بالذات (إنشاء → نشر → إيقاف → أرشفة)،
 * وإثباتًا حقيقيًا أن حملة أُنشئت من هنا تُطابق دلالات Phase 1 المنشورة
 * فعليًا: مؤهلة/مستبعدة عبر PopupQueries::activeForUser تمامًا كما لو
 * أُنشئت من Filament.
 *
 * لا صلاحية جديدة: كل الفحوص هنا تستخدم قيم popup_campaign.* الحالية فقط.
 */
final class PopupMessageConsoleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();

        foreach ([
            'admin.panel.access',
            'popup_campaign.view_any',
            'popup_campaign.view',
            'popup_campaign.create',
            'popup_campaign.update',
            'popup_campaign.publish',
            'popup_campaign.pause',
            'popup_campaign.archive',
        ] as $permission) {
            Gate::define($permission, fn (): bool => in_array($permission, $this->permissions, true));
        }
    }

    public function test_a_user_without_popup_campaign_permissions_is_forbidden(): void
    {
        $this->permissions = ['admin.panel.access'];

        $organization = Organization::factory()->create();
        $stranger = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($stranger)->get('/manage/popup-messages')->assertForbidden();
    }

    public function test_a_user_without_console_access_is_forbidden_even_with_popup_permissions(): void
    {
        // admin.panel.access نفسه غير ممنوح — بوابة الكونسول العامة تسبق
        // بوابة الميزة، وأحدهما لا يعوّض غياب الآخر.
        $this->permissions = ['popup_campaign.view_any', 'popup_campaign.create'];

        $organization = Organization::factory()->create();
        $stranger = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($stranger)->get('/manage/popup-messages')->assertForbidden();
    }

    public function test_create_route_requires_its_own_ability_separate_from_view_any(): void
    {
        $this->permissions = ['admin.panel.access', 'popup_campaign.view_any'];

        $organization = Organization::factory()->create();
        $viewerOnly = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($viewerOnly)
            ->get('/manage/popup-messages')
            ->assertOk();

        $this->actingAs($viewerOnly)
            ->post('/manage/popup-messages', $this->payload())
            ->assertForbidden();

        self::assertSame(0, PopupCampaign::query()->count());
    }

    public function test_full_lifecycle_through_the_console_matches_phase_1_eligibility_semantics(): void
    {
        $this->permissions = [
            'admin.panel.access', 'popup_campaign.view_any', 'popup_campaign.view', 'popup_campaign.create',
            'popup_campaign.update', 'popup_campaign.publish', 'popup_campaign.pause', 'popup_campaign.archive',
        ];

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $student = User::factory()->inOrganization((string) $organization->id)->create();
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();

        // الحملة تستهدف الجميع لكنها تستثني المعلمين صراحة.
        $this->actingAs($admin)
            ->post('/manage/popup-messages', $this->payload([
                'audiences' => ['all_authenticated'],
                'excluded_audiences' => ['teacher'],
            ]))
            ->assertSessionHasNoErrors();

        $campaign = PopupCampaign::query()->sole();
        self::assertSame(PopupCampaignStatus::Draft, $campaign->status);
        self::assertSame(PopupDisplayMode::Fullscreen, $campaign->display_mode);
        self::assertSame(['teacher'], $campaign->excluded_audiences);

        // مسودة: لا تظهر لأحد بعد رغم مطابقة الجمهور.
        self::assertNull($this->activeFor($organization, $student, ['student']));

        $this->actingAs($admin)
            ->post("/manage/popup-messages/{$campaign->getKey()}/publish", ['reason' => 'إطلاق الحملة'])
            ->assertSessionHasNoErrors();

        $campaign->refresh();
        self::assertSame(PopupCampaignStatus::Published, $campaign->status);

        // نفس دلالة Phase 1 بالضبط: الاستثناء يفوز، والطالب مؤهل.
        $forStudent = $this->activeFor($organization, $student, ['student']);
        $forTeacher = $this->activeFor($organization, $teacher, ['teacher']);
        self::assertNotNull($forStudent);
        self::assertSame((string) $campaign->getKey(), $forStudent->campaignId);
        self::assertNull($forTeacher);

        $this->actingAs($admin)
            ->post("/manage/popup-messages/{$campaign->getKey()}/pause", ['reason' => 'إيقاف مؤقت للمراجعة'])
            ->assertSessionHasNoErrors();

        $campaign->refresh();
        self::assertSame(PopupCampaignStatus::Paused, $campaign->status);
        self::assertNull(
            $this->activeFor($organization, $student, ['student']),
            'campaign paused: should no longer be eligible',
        );

        $this->actingAs($admin)
            ->post("/manage/popup-messages/{$campaign->getKey()}/archive", ['reason' => 'انتهت الحاجة للحملة'])
            ->assertSessionHasNoErrors();

        $campaign->refresh();
        self::assertSame(PopupCampaignStatus::Archived, $campaign->status);
        self::assertFalse($campaign->status->canTransitionTo(PopupCampaignStatus::Published));
    }

    public function test_store_reports_form_shape_errors_before_reaching_the_action(): void
    {
        $this->permissions = ['admin.panel.access', 'popup_campaign.view_any', 'popup_campaign.create'];

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($admin)
            ->post('/manage/popup-messages', $this->payload([
                'title' => ['ar' => ''],
            ]))
            ->assertSessionHasErrors('title.ar');

        self::assertSame(0, PopupCampaign::query()->count());
    }

    /**
     * الرفض هنا يأتي من SavePopupCampaignAction نفسه (تناقض جمهور/استثناء)،
     * لا من شكل الطلب — نفس Action الذي يخدم لوحة Filament القديمة.
     */
    public function test_store_reports_business_rule_violations_from_the_shared_action(): void
    {
        $this->permissions = ['admin.panel.access', 'popup_campaign.view_any', 'popup_campaign.create'];

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($admin)
            ->post('/manage/popup-messages', $this->payload([
                'audiences' => ['student'],
                'excluded_audiences' => ['student'],
            ]))
            ->assertSessionHasErrors('internal_name');

        self::assertSame(0, PopupCampaign::query()->count());
    }

    public function test_media_upload_delegates_to_the_phase_1_upload_controller(): void
    {
        $this->permissions = ['admin.panel.access', 'popup_campaign.view_any', 'popup_campaign.create', 'popup_campaign.update'];
        Storage::fake((string) config('popups.attachments.disk'));

        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();

        $this->actingAs($admin)->post('/manage/popup-messages', $this->payload())->assertSessionHasNoErrors();
        $campaign = PopupCampaign::query()->sole();

        $image = UploadedFile::fake()->image('note.jpg', 100, 100)->size(50);

        $this->actingAs($admin)
            ->postJson("/manage/popup-messages/{$campaign->getKey()}/media", [
                'kind' => 'image',
                'file' => $image,
            ])
            ->assertCreated();

        self::assertSame(1, PopupCampaignMedia::query()->where('campaign_id', $campaign->getKey())->count());
    }

    /**
     * @param list<string> $userAudiences
     */
    private function activeFor(Organization $organization, User $user, array $userAudiences): mixed
    {
        return app(PopupQueries::class)->activeForUser(
            organizationId: (string) $organization->id,
            userId: (string) $user->id,
            userAudiences: $userAudiences,
            placement: 'after_login',
            pageKey: null,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'internal_name' => 'console-campaign-'.str()->random(6),
            'type' => PopupType::General->value,
            'title' => ['ar' => 'عنوان تجريبي', 'en' => 'Demo title'],
            'body' => ['ar' => 'نص تجريبي', 'en' => 'Demo body'],
            'audiences' => ['student'],
            'excluded_audiences' => [],
            'placement' => PopupPlacement::AfterLogin->value,
            'display_mode' => PopupDisplayMode::Fullscreen->value,
            'page_key' => null,
            'frequency' => PopupFrequency::Once->value,
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'auto_dismiss_seconds' => null,
            'priority' => 5,
            'starts_at' => now('UTC')->subMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now('UTC')->addWeek()->format('Y-m-d H:i:s'),
            'action_type' => '',
            'internal_action_target' => null,
            'external_action_target' => null,
            'links' => [],
            'reason' => 'اختبار الرسائل المنبثقة',
        ], $overrides);
    }
}
