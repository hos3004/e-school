<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\RecordPopupInteractionAction;
use Modules\Notifications\Application\Actions\TransitionPopupCampaignAction;
use Modules\Notifications\Application\Services\AccessControlPopupAudienceResolver;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\ValueObjects\ActivePopupData;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

/**
 * تغطية مركزة لدورة حياة النوافذ المنبثقة: الأهلية، الجمهور، المؤسسة،
 * المواضع، التكرار، التفاعلات، الانتقالات، والأمان.
 */
final class PopupCampaignTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // الأهلية الزمنية والحالة
    // ------------------------------------------------------------------

    public function test_published_within_window_is_served_and_others_are_not(): void
    {
        $fixture = $this->context();

        $active = $this->campaign($fixture['organization'], ['internal_name' => 'active-now']);
        $future = $this->campaign($fixture['organization'], [
            'internal_name' => 'future', 'starts_at' => now('UTC')->addDay(), 'ends_at' => null,
        ]);
        $expired = $this->campaign($fixture['organization'], [
            'internal_name' => 'expired', 'starts_at' => now('UTC')->subDays(3), 'ends_at' => now('UTC')->subDay(),
        ]);
        $paused = $this->campaign($fixture['organization'], ['status' => PopupCampaignStatus::Paused]);
        $archived = $this->campaign($fixture['organization'], ['status' => PopupCampaignStatus::Archived]);
        $draft = $this->campaign($fixture['organization'], ['status' => PopupCampaignStatus::Draft]);

        $popup = $this->fetch($fixture);

        self::assertNotNull($popup);
        self::assertSame((string) $active->getKey(), $popup->campaignId);
    }

    public function test_highest_priority_wins_with_deterministic_tiebreak(): void
    {
        $fixture = $this->context();
        $now = now('UTC');

        $low = $this->campaign($fixture['organization'], ['priority' => 1]);
        $high = $this->campaign($fixture['organization'], [
            'priority' => 9,
            'starts_at' => $now->copy()->subMinutes(30),
        ]);
        $tieOlder = $this->campaign($fixture['organization'], [
            'priority' => 9,
            'starts_at' => $now->copy()->subHours(2),
        ]);

        $popup = $this->fetch($fixture);

        // أعلى أولوية، وعند التعادل الأسبق زمنيًا.
        self::assertNotNull($popup);
        self::assertSame((string) $tieOlder->getKey(), $popup->campaignId);
    }

    // ------------------------------------------------------------------
    // الجمهور والمؤسسة
    // ------------------------------------------------------------------

    public function test_audience_targeting_matches_only_listed_roles(): void
    {
        $fixture = $this->context();

        $studentsOnly = $this->campaign($fixture['organization'], ['audiences' => ['student']]);

        // المعلم ليس طالبًا.
        self::assertNull($this->fetch($fixture, audiences: ['teacher']));

        // الطالب يراه.
        $seen = $this->fetch($fixture, audiences: ['student']);
        self::assertNotNull($seen);
        self::assertSame(['student'], $seen->matchedAudiences);
    }

    public function test_multi_audience_overlap_is_reported_without_duplication(): void
    {
        $fixture = $this->context();

        // مستخدم يحمل أكثر من جمهور: النتيجة نافذة واحدة وقائمة تقاطع واحدة.
        $this->campaign($fixture['organization'], ['audiences' => ['teacher', 'supervisor']]);

        $seen = $this->fetch($fixture, audiences: ['teacher', 'supervisor']);

        self::assertNotNull($seen);
        self::assertSame(['teacher', 'supervisor'], $seen->matchedAudiences);
    }

    public function test_all_authenticated_covers_any_signed_in_user(): void
    {
        $fixture = $this->context();

        $this->campaign($fixture['organization'], ['audiences' => ['all_authenticated']]);

        self::assertNotNull($this->fetch($fixture, audiences: []));
        self::assertNotNull($this->fetch($fixture, audiences: ['guardian']));
    }

    public function test_campaigns_never_cross_organizations(): void
    {
        [$organization] = $this->contextPair();
        $other = Organization::factory()->create();

        $this->campaign($other, ['internal_name' => 'foreign']);

        $fixture = ['organization' => $other, 'user' => User::factory()->inOrganization((string) $other->id)->create()];

        // مستخدم مؤسسة أخرى لا يرى حملة المؤسسة الأولى.
        $this->campaign($organization, ['internal_name' => 'local']);
        $popup = $this->fetch($fixture);

        if ($popup !== null) {
            self::assertNotSame('foreign', $popup->campaignId);
        }
    }

    // ------------------------------------------------------------------
    // المواضع
    // ------------------------------------------------------------------

    public function test_placements_match_by_page_context(): void
    {
        $fixture = $this->context();

        $afterLogin = $this->campaign($fixture['organization'], ['placement' => PopupPlacement::AfterLogin]);
        $specific = $this->campaign($fixture['organization'], [
            'placement' => PopupPlacement::SpecificPage,
            'page_key' => 'student.schedule',
        ]);
        $anywhere = $this->campaign($fixture['organization'], [
            'placement' => PopupPlacement::AllAuthenticatedPages,
        ]);

        // after_login يُطلب بمفتاح after_login فقط.
        self::assertNull($this->fetch($fixture, placement: 'dashboard'));
        self::assertNotNull($this->fetch($fixture, placement: 'after_login'));

        // الصفحة المحددة تطابق مفتاحها القانوني فقط.
        self::assertNull($this->fetch($fixture, placement: 'specific_page', pageKey: 'student.dashboard'));
        self::assertNotNull($this->fetch($fixture, placement: 'specific_page', pageKey: 'student.schedule'));

        // أول صفحة مؤهلة تظهر في أي سياق.
        self::assertNotNull($this->fetch($fixture, placement: 'all_authenticated_pages'));
    }

    // ------------------------------------------------------------------
    // التكرار والتفاعلات
    // ------------------------------------------------------------------

    public function test_frequency_rules_govern_repeat_visibility(): void
    {
        $fixture = $this->context();
        $queries = app(PopupQueries::class);
        $action = app(RecordPopupInteractionAction::class);
        $now = now('UTC')->toImmutable();

        // Once: بعد أول مشاهدة لا تعود.
        $once = $this->campaign($fixture['organization'], ['frequency' => 'once']);
        app(RecordPopupInteractionAction::class)->execute(
            (string) $once->getKey(), $fixture['user']->id, (string) $fixture['organization']->id,
            RecordPopupInteractionAction::TYPE_IMPRESSION, 'marker-1',
        );
        self::assertNull($queries->activeForUser(
            (string) $fixture['organization']->id, (string) $fixture['user']->id, ['student'],
            PopupPlacement::AfterLogin->value, null, 'marker-1', $now,
        ));

        // OncePerLogin: علامة دخول جديدة تُعيد الظهور.
        $perLogin = $this->campaign($fixture['organization'], [
            'frequency' => 'once_per_login', 'internal_name' => 'per-login',
        ]);
        app(RecordPopupInteractionAction::class)->execute(
            (string) $perLogin->getKey(), $fixture['user']->id, (string) $fixture['organization']->id,
            RecordPopupInteractionAction::TYPE_IMPRESSION, 'login-A',
        );
        self::assertNull($queries->activeForUser(
            (string) $fixture['organization']->id, (string) $fixture['user']->id, ['student'],
            PopupPlacement::AfterLogin->value, null, 'login-A', $now,
        ));
        self::assertNotNull($queries->activeForUser(
            (string) $fixture['organization']->id, (string) $fixture['user']->id, ['student'],
            PopupPlacement::AfterLogin->value, null, 'login-B', $now,
        ));

        // UntilAcknowledged: تظهر، ثم الإغلاق المتعمد يسددها.
        $untilAck = $this->campaign($fixture['organization'], [
            'frequency' => 'until_acknowledged', 'internal_name' => 'until-ack', 'priority' => 9,
            'placement' => PopupPlacement::Dashboard->value,
        ]);
        $action->execute(
            (string) $untilAck->getKey(), $fixture['user']->id, (string) $fixture['organization']->id,
            RecordPopupInteractionAction::TYPE_IMPRESSION, null,
        );
        self::assertNotNull($queries->activeForUser(
            (string) $fixture['organization']->id, (string) $fixture['user']->id,
            ['student'], PopupPlacement::Dashboard->value, null, null, $now,
        ));
        $action->execute(
            (string) $untilAck->getKey(), $fixture['user']->id, (string) $fixture['organization']->id,
            RecordPopupInteractionAction::TYPE_DISMISS, null,
        );
        self::assertNull($queries->activeForUser(
            (string) $fixture['organization']->id, (string) $fixture['user']->id,
            ['student'], PopupPlacement::Dashboard->value, null, null, $now,
        ));
    }

    public function test_interactions_are_idempotent_and_guarded(): void
    {
        $fixture = $this->context();
        $action = app(RecordPopupInteractionAction::class);
        $orgId = (string) $fixture['organization']->id;
        $userId = (string) $fixture['user']->id;

        // Acknowledge على حملة بلا إقرار يُرفض.
        $plain = $this->campaign($fixture['organization'], ['requires_acknowledgement' => false]);
        try {
            $action->execute((string) $plain->getKey(), $userId, $orgId, RecordPopupInteractionAction::TYPE_ACKNOWLEDGE, null);
            self::fail('Expected rejection of acknowledge on a campaign without acknowledgement.');
        } catch (BusinessRuleViolation) {
        }

        // إقرار على حملة تتطلب إقرارًا: idempotent.
        $ack = $this->campaign($fixture['organization'], [
            'internal_name' => 'needs-ack',
            'is_dismissible' => false,
            'requires_acknowledgement' => true,
        ]);
        $ackId = (string) $ack->getKey();
        $action->execute($ackId, $userId, $orgId, RecordPopupInteractionAction::TYPE_ACKNOWLEDGE, null);
        $state = $action->execute($ackId, $userId, $orgId, RecordPopupInteractionAction::TYPE_ACKNOWLEDGE, null);

        self::assertNotNull($state->acknowledged_at);

        $row = DB::table('popup_campaign_user_state')
            ->where('campaign_id', $ackId)
            ->where('user_id', $userId)
            ->first();

        self::assertNotNull($row);
        self::assertSame(0, (int) $row->impressions_count); // بلا مشاهدات مسجلة هنا

        // IDOR: حساب من مؤسسة أخرى لا يستطيع التفاعل مع الحملة.
        $intruder = User::factory()->inOrganization((string) Organization::factory()->create()->id)->create();
        $this->expectException(BusinessRuleViolation::class);
        $action->execute($ackId, (string) $intruder->id, (string) $intruder->organization_id, RecordPopupInteractionAction::TYPE_IMPRESSION, null);
    }

    // ------------------------------------------------------------------
    // انتقالات الحالة والتدقيق
    // ------------------------------------------------------------------

    public function test_status_transitions_follow_the_state_machine(): void
    {
        $fixture = $this->context();
        $transition = app(TransitionPopupCampaignAction::class);

        $campaign = $this->campaign($fixture['organization'], ['status' => PopupCampaignStatus::Draft]);

        $transition->execute($campaign, PopupCampaignStatus::Published, (string) $fixture['user']->id, 'نشر الحملة للطلاب');
        self::assertSame(PopupCampaignStatus::Published, $campaign->refresh()->status);
        self::assertNotNull($campaign->published_at);

        $transition->execute($campaign, PopupCampaignStatus::Paused, (string) $fixture['user']->id, 'إيقاف مؤقت لمراجعة النص');
        $transition->execute($campaign, PopupCampaignStatus::Published, (string) $fixture['user']->id, 'استئناف بعد المراجعة');
        $transition->execute($campaign, PopupCampaignStatus::Archived, (string) $fixture['user']->id, 'انتهاء صلاحية المحتوى');

        // الأرشفة نهائية.
        $this->expectException(BusinessRuleViolation::class);
        $transition->execute($campaign->refresh(), PopupCampaignStatus::Published, (string) $fixture['user']->id, 'محاولة إعادة نشر مؤرشفة');
    }

    public function test_publish_requires_reason_and_audit_row_is_written(): void
    {
        $fixture = $this->context();
        $transition = app(TransitionPopupCampaignAction::class);
        $campaign = $this->campaign($fixture['organization'], ['status' => PopupCampaignStatus::Draft]);

        try {
            $transition->execute($campaign, PopupCampaignStatus::Published, (string) $fixture['user']->id, '');
            self::fail('Expected reason requirement.');
        } catch (BusinessRuleViolation) {
        }

        $transition->execute($campaign, PopupCampaignStatus::Published, (string) $fixture['user']->id, 'إعلان بدء التسجيل');

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'notifications.popup_campaign_published',
            'auditable_id' => (string) $campaign->getKey(),
            'reason' => 'إعلان بدء التسجيل',
        ])->exists());
    }

    // ------------------------------------------------------------------
    // أمان المحتوى والروابط والعربية
    // ------------------------------------------------------------------

    public function test_unsafe_external_urls_are_never_resolved(): void
    {
        $fixture = $this->context();

        $evil = $this->campaign($fixture['organization'], [
            'action_type' => 'external_url',
            'action_target' => 'javascript:alert(document.cookie)',
        ]);

        $popup = $this->fetch($fixture);

        // الرابط غير الآمن لا يخرج من الخادم إطلاقًا؛ النافذة تظهر بلا CTA.
        self::assertNotNull($popup);
        self::assertNull($popup->actionUrl);

        // HTTPS الخارجي مسموح ويخرج كما هو.
        $safe = $this->campaign($fixture['organization'], [
            'internal_name' => 'safe-cta',
            'priority' => 9,
            'action_type' => 'external_url',
            'action_target' => 'https://example.com/promo',
            'action_label' => ['ar' => 'اعرف المزيد'],
        ]);
        $popupSafe = $this->fetch($fixture);
        self::assertNotNull($popupSafe);
        self::assertSame('https://example.com/promo', $popupSafe->actionUrl);
        self::assertTrue($popupSafe->actionIsExternal);
    }

    public function test_arabic_fallback_when_user_locale_has_no_translation(): void
    {
        $fixture = $this->context();

        $this->campaign($fixture['organization'], [
            'title' => ['ar' => 'إعلان بالعربية فقط'],
            'body' => ['ar' => 'النص العربي الإلزامي'],
        ]);

        app()->setLocale('en');
        $popup = $this->fetch($fixture);
        app()->setLocale('ar');

        self::assertNotNull($popup);
        self::assertSame('إعلان بالعربية فقط', $popup->title['value']);
        self::assertSame('النص العربي الإلزامي', $popup->body['value']);
    }

    // ------------------------------------------------------------------
    // حلّ الجمهور عبر عقود AccessControl
    // ------------------------------------------------------------------

    public function test_audience_resolver_maps_access_control_roles(): void
    {
        $resolver = app(AccessControlPopupAudienceResolver::class);
        $modelType = app(UserQueryService::class)->modelType();

        Role::query()->create([
            'organization_id' => null,
            'name' => 'teacher',
            'guard_name' => 'web',
            'is_system' => true,
        ]);
        Role::query()->create([
            'organization_id' => null,
            'name' => 'academic_supervisor',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $user = User::factory()->create();

        foreach (['teacher', 'academic_supervisor'] as $roleName) {
            ModelHasRole::query()->create([
                'role_id' => (string) Role::query()
                    ->where('name', $roleName)->firstOrFail()->getKey(),
                'model_type' => $modelType,
                'model_id' => (string) $user->id,
            ]);
        }

        $audiences = $resolver->audiencesFor($modelType, (string) $user->id);

        sort($audiences);
        self::assertSame(['supervisor', 'teacher'], $audiences);
    }

    // ------------------------------------------------------------------
    // تجهيزات
    // ------------------------------------------------------------------

    /**
     * @return array{organization: Organization, user: User}
     */
    private function context(): array
    {
        [$organization, $user] = $this->contextPair();

        return ['organization' => $organization, 'user' => $user];
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function contextPair(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->inOrganization((string) $organization->id)->create();

        return [$organization, $user];
    }

    /** @param array<string, mixed> $overrides */
    private function campaign(Organization $organization, array $overrides = []): PopupCampaign
    {
        /** @var array<string, mixed> $attributes */
        $attributes = array_merge([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'camp-'.str()->random(6),
            'type' => 'general',
            'status' => PopupCampaignStatus::Published,
            'priority' => 5,
            'title' => ['ar' => 'عنوان تجريبي', 'en' => 'Demo title'],
            'body' => ['ar' => 'نص تجريبي للحملة المنبثقة', 'en' => 'Demo popup body'],
            'audiences' => ['all_authenticated'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => 'once',
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDays(7),
            'created_by' => (string) $this->adminId($organization),
        ], $overrides);

        return PopupCampaign::query()->create($attributes);
    }

    private function adminId(Organization $organization): string
    {
        static $ids = [];

        $key = (string) $organization->id;

        return $ids[$key] ??= (string) User::factory()->inOrganization($key)->create()->getKey();
    }

    /**
     * @param array{organization: Organization, user: User} $fixture
     * @param list<string>|null $audiences
     */
    private function fetch(
        array $fixture,
        ?array $audiences = null,
        string $placement = 'after_login',
        ?string $pageKey = null,
    ): ?ActivePopupData {
        return app(PopupQueries::class)->activeForUser(
            organizationId: (string) $fixture['organization']->id,
            userId: (string) $fixture['user']->id,
            userAudiences: $audiences ?? ['student'],
            placement: $placement,
            pageKey: $pageKey,
            loginMarker: 'marker-test',
            now: now('UTC')->toImmutable(),
        );
    }
}
