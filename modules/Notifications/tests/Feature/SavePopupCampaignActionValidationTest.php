<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Domain\Enums\PopupDisplayMode;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

/**
 * تغطية Phase 2 لإغلاق الفجوة التي أجّلها مراجع Phase 1 عمدًا:
 * SavePopupCampaignAction لم يكن يتحقق من display_mode/excluded_audiences/
 * auto_dismiss_seconds/links. هذا الملف يثبت أن كل حقل منها مرفوض بوضوح عند
 * قيمة غير منطقية، ومقبول ومُطبَّع عند قيمة صحيحة.
 */
final class SavePopupCampaignActionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_audience_cannot_be_both_targeted_and_excluded(): void
    {
        [$organization, $actor] = $this->context();

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'audiences' => ['student', 'teacher'],
                    'excluded_audiences' => ['teacher'],
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'محاولة تناقض في الجمهور',
            );
            self::fail('Expected a contradictory-audience rejection.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_contradictory_audience', $violation->rule);
        }

        self::assertSame(0, PopupCampaign::query()->count());
    }

    public function test_an_excluded_audience_with_no_overlap_is_accepted(): void
    {
        [$organization, $actor] = $this->context();

        $campaign = $this->action()->execute(
            campaign: null,
            organizationId: (string) $organization->id,
            attributes: $this->baseAttributes([
                'audiences' => ['student'],
                'excluded_audiences' => ['teacher'],
            ]),
            scheduleChanges: null,
            actorId: (string) $actor->id,
            reason: 'استثناء غير متناقض',
        );

        self::assertSame(['teacher'], $campaign->excluded_audiences);
    }

    public function test_an_invalid_excluded_audience_value_is_rejected(): void
    {
        [$organization, $actor] = $this->context();

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes(['excluded_audiences' => ['not-a-real-audience']]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'قيمة استثناء غير صالحة',
            );
            self::fail('Expected a rejection for an invalid excluded audience value.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_configuration', $violation->rule);
        }
    }

    public function test_an_invalid_display_mode_is_rejected(): void
    {
        [$organization, $actor] = $this->context();

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes(['display_mode' => 'side_drawer']),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'نمط عرض غير معروف',
            );
            self::fail('Expected a rejection for an unknown display mode.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_configuration', $violation->rule);
        }
    }

    public function test_display_mode_defaults_to_bottom_banner_when_omitted(): void
    {
        [$organization, $actor] = $this->context();

        $attributes = $this->baseAttributes();
        unset($attributes['display_mode']);

        $campaign = $this->action()->execute(
            campaign: null,
            organizationId: (string) $organization->id,
            attributes: $attributes,
            scheduleChanges: null,
            actorId: (string) $actor->id,
            reason: 'بلا نمط عرض محدد',
        );

        self::assertSame(PopupDisplayMode::BottomBanner, $campaign->display_mode);
    }

    public function test_auto_dismiss_seconds_below_the_configured_minimum_is_rejected(): void
    {
        [$organization, $actor] = $this->context();
        $min = (int) config('popups.auto_dismiss.min_seconds');

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'is_dismissible' => false,
                    'requires_acknowledgement' => false,
                    'auto_dismiss_seconds' => max(0, $min - 1),
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'مدة إغلاق تلقائي أقل من الحد',
            );
            self::fail('Expected a rejection for an auto-dismiss duration below the configured minimum.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_auto_dismiss', $violation->rule);
        }
    }

    public function test_auto_dismiss_seconds_above_the_configured_maximum_is_rejected(): void
    {
        [$organization, $actor] = $this->context();
        $max = (int) config('popups.auto_dismiss.max_seconds');

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'is_dismissible' => false,
                    'requires_acknowledgement' => false,
                    'auto_dismiss_seconds' => $max + 1,
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'مدة إغلاق تلقائي أعلى من الحد',
            );
            self::fail('Expected a rejection for an auto-dismiss duration above the configured maximum.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_auto_dismiss', $violation->rule);
        }
    }

    public function test_auto_dismiss_seconds_within_bounds_is_accepted(): void
    {
        [$organization, $actor] = $this->context();
        $min = (int) config('popups.auto_dismiss.min_seconds');

        $campaign = $this->action()->execute(
            campaign: null,
            organizationId: (string) $organization->id,
            attributes: $this->baseAttributes([
                'is_dismissible' => false,
                'requires_acknowledgement' => false,
                'auto_dismiss_seconds' => $min,
            ]),
            scheduleChanges: null,
            actorId: (string) $actor->id,
            reason: 'مدة إغلاق تلقائي صالحة',
        );

        self::assertSame($min, $campaign->auto_dismiss_seconds);
    }

    public function test_a_link_whose_text_does_not_occur_in_the_body_is_rejected(): void
    {
        [$organization, $actor] = $this->context();

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'body' => ['ar' => 'يرجى مراجعة الجدول الجديد.'],
                    'links' => [['text' => 'كلمة غير موجودة', 'url' => 'https://example.test']],
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'رابط لكلمة غير موجودة بالنص',
            );
            self::fail('Expected a rejection for link text absent from the body.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_link_text_not_in_body', $violation->rule);
        }
    }

    public function test_a_link_with_a_matching_text_and_external_https_url_is_accepted(): void
    {
        [$organization, $actor] = $this->context();

        $campaign = $this->action()->execute(
            campaign: null,
            organizationId: (string) $organization->id,
            attributes: $this->baseAttributes([
                'body' => ['ar' => 'راجع الجدول الجديد من هنا.'],
                'links' => [['text' => 'هنا', 'url' => 'https://example.test/schedule']],
            ]),
            scheduleChanges: null,
            actorId: (string) $actor->id,
            reason: 'رابط خارجي صالح لكلمة موجودة',
        );

        self::assertSame([['text' => 'هنا', 'url' => 'https://example.test/schedule']], $campaign->links);
    }

    public function test_a_link_url_that_is_neither_https_nor_a_popup_media_reference_is_rejected(): void
    {
        [$organization, $actor] = $this->context();

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'body' => ['ar' => 'اضغط هنا للتفاصيل.'],
                    'links' => [['text' => 'هنا', 'url' => 'javascript:alert(1)']],
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'رابط بمخطط غير آمن',
            );
            self::fail('Expected a rejection for a non-HTTPS, non-popup-media link URL.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_link_url', $violation->rule);
        }
    }

    public function test_a_popup_media_link_pointing_at_another_campaigns_media_is_rejected(): void
    {
        [$organization, $actor] = $this->context();

        $otherCampaign = PopupCampaign::query()->create([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'other-campaign',
            'type' => PopupType::General,
            'status' => 'draft',
            'priority' => 5,
            'title' => ['ar' => 'حملة أخرى'],
            'body' => ['ar' => 'نص حملة أخرى'],
            'audiences' => ['student'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => 'once',
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC'),
        ]);

        $foreignMedia = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $otherCampaign->getKey(),
            'kind' => 'file',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$otherCampaign->getKey().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'position' => 1,
        ]);

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'body' => ['ar' => 'حمّل الملف من هنا.'],
                    'links' => [['text' => 'هنا', 'url' => 'popup-media:'.$foreignMedia->getKey()]],
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'رابط لميديا حملة أخرى',
            );
            self::fail('Expected a rejection for a popup-media link referencing another campaign.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_media_link', $violation->rule);
        }
    }

    public function test_a_popup_media_link_pointing_at_a_non_file_kind_medium_is_rejected(): void
    {
        [$organization, $actor] = $this->context();

        $campaign = PopupCampaign::query()->create([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'draft-campaign',
            'type' => PopupType::General,
            'status' => 'draft',
            'priority' => 5,
            'title' => ['ar' => 'مسودة'],
            'body' => ['ar' => 'حمّل الصورة من هنا.'],
            'audiences' => ['student'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => 'once',
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC'),
        ]);

        $imageMedia = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'image',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$campaign->getKey().'/note.jpg',
            'original_name' => 'note.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'position' => 1,
        ]);

        try {
            $this->action()->execute(
                campaign: $campaign,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'body' => ['ar' => 'حمّل الصورة من هنا.'],
                    'links' => [['text' => 'هنا', 'url' => 'popup-media:'.$imageMedia->getKey()]],
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'رابط لملف ليس من نوع file',
            );
            self::fail('Expected a rejection for a popup-media link to a non-file-kind medium.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_media_link', $violation->rule);
        }
    }

    public function test_a_popup_media_link_pointing_at_this_campaigns_own_file_medium_is_accepted_on_update(): void
    {
        [$organization, $actor] = $this->context();

        $campaign = PopupCampaign::query()->create([
            'organization_id' => (string) $organization->id,
            'internal_name' => 'draft-campaign-with-file',
            'type' => PopupType::General,
            'status' => 'draft',
            'priority' => 5,
            'title' => ['ar' => 'مسودة'],
            'body' => ['ar' => 'حمّل الملف من هنا.'],
            'audiences' => ['student'],
            'placement' => PopupPlacement::AfterLogin,
            'frequency' => 'once',
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'starts_at' => now('UTC'),
        ]);

        $fileMedia = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => 'file',
            'disk' => (string) config('popups.attachments.disk'),
            'path' => 'popups/'.$campaign->getKey().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'position' => 1,
        ]);

        $updated = $this->action()->execute(
            campaign: $campaign,
            organizationId: (string) $organization->id,
            attributes: $this->baseAttributes([
                'body' => ['ar' => 'حمّل الملف من هنا.'],
                'links' => [['text' => 'هنا', 'url' => 'popup-media:'.$fileMedia->getKey()]],
            ]),
            scheduleChanges: null,
            actorId: (string) $actor->id,
            reason: 'رابط صالح لملف هذه الحملة بالذات',
        );

        self::assertSame([['text' => 'هنا', 'url' => 'popup-media:'.$fileMedia->getKey()]], $updated->links);
    }

    public function test_a_popup_media_link_on_a_brand_new_unsaved_campaign_is_always_rejected(): void
    {
        // لا صف ميديا يمكن أن يملك campaign_id لحملة لم تُحفظ بعد — أي مرجع
        // popup-media وقت الإنشاء مرفوض بالضرورة، وهذا هو السلوك المطلوب:
        // الرفع أولًا، ثم ربط الرابط لاحقًا عبر تعديل.
        [$organization, $actor] = $this->context();

        try {
            $this->action()->execute(
                campaign: null,
                organizationId: (string) $organization->id,
                attributes: $this->baseAttributes([
                    'body' => ['ar' => 'حمّل الملف من هنا.'],
                    'links' => [['text' => 'هنا', 'url' => 'popup-media:01ARZ3NDEKTSV4RRFFQ69G5FAV']],
                ]),
                scheduleChanges: null,
                actorId: (string) $actor->id,
                reason: 'رابط ميديا لحملة غير موجودة بعد',
            );
            self::fail('Expected a rejection for a popup-media link on a not-yet-created campaign.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('notifications.popup_invalid_media_link', $violation->rule);
        }
    }

    private function action(): SavePopupCampaignAction
    {
        return app(SavePopupCampaignAction::class);
    }

    /** @return array{0: Organization, 1: User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();

        return [$organization, $actor];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function baseAttributes(array $overrides = []): array
    {
        return array_replace([
            'internal_name' => 'validation-campaign-'.str()->random(6),
            'type' => PopupType::General->value,
            'title' => ['ar' => 'عنوان تجريبي'],
            'body' => ['ar' => 'نص تجريبي'],
            'audiences' => ['student'],
            'excluded_audiences' => [],
            'placement' => PopupPlacement::AfterLogin->value,
            'display_mode' => PopupDisplayMode::BottomBanner->value,
            'page_key' => null,
            'frequency' => 'once',
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'auto_dismiss_seconds' => null,
            'priority' => 5,
            'starts_at' => now('UTC')->addMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now('UTC')->addWeek()->format('Y-m-d H:i:s'),
            'action_type' => null,
            'action_target' => null,
            'links' => [],
        ], $overrides);
    }
}
