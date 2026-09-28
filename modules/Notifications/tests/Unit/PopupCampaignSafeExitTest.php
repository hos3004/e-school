<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Unit;

use Modules\Notifications\Domain\Models\PopupCampaign;
use Tests\TestCase;

/**
 * hasSafeExit() هي القاعدة الوحيدة التي تمنع حملة تحبس المستخدم: بلا
 * إغلاق يدوي وبلا إقرار وبلا إغلاق تلقائي. اختبار وحدة صرف بلا قاعدة
 * بيانات — يفحص منطقًا حسابيًا بحتًا على نموذج غير محفوظ.
 */
final class PopupCampaignSafeExitTest extends TestCase
{
    public function test_dismissible_campaign_has_a_safe_exit(): void
    {
        $campaign = new PopupCampaign([
            'is_dismissible' => true,
            'requires_acknowledgement' => false,
            'auto_dismiss_seconds' => null,
        ]);

        self::assertTrue($campaign->hasSafeExit());
    }

    public function test_acknowledgement_only_campaign_has_a_safe_exit(): void
    {
        $campaign = new PopupCampaign([
            'is_dismissible' => false,
            'requires_acknowledgement' => true,
            'auto_dismiss_seconds' => null,
        ]);

        self::assertTrue($campaign->hasSafeExit());
    }

    public function test_auto_dismiss_alone_now_counts_as_a_safe_exit(): void
    {
        $campaign = new PopupCampaign([
            'is_dismissible' => false,
            'requires_acknowledgement' => false,
            'auto_dismiss_seconds' => 10,
        ]);

        self::assertTrue($campaign->hasSafeExit());
    }

    public function test_zero_auto_dismiss_seconds_does_not_count_as_a_safe_exit(): void
    {
        $campaign = new PopupCampaign([
            'is_dismissible' => false,
            'requires_acknowledgement' => false,
            'auto_dismiss_seconds' => 0,
        ]);

        self::assertFalse($campaign->hasSafeExit());
    }

    public function test_campaign_with_no_exit_mechanism_at_all_is_unsafe(): void
    {
        $campaign = new PopupCampaign([
            'is_dismissible' => false,
            'requires_acknowledgement' => false,
            'auto_dismiss_seconds' => null,
        ]);

        self::assertFalse($campaign->hasSafeExit());
    }
}
