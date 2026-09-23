<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Models\BotAccountAccess;
use Modules\SupportBot\Domain\ValueObjects\BotAccess;

/**
 * من يصل إلى البوت.
 *
 * أربع بوابات مرتبة:
 *
 *  1. **المفتاح العام.** حالة الاتصال بالمزوّد. إيقافه يوقف البوت للجميع فورًا
 *     دون نشر ودون حذف المفتاح.
 *  2. **الفئة.** مستخلَصة من أدوار المستخدم. من لا يطابق دورُه أي فئة معروفة
 *     لا يحصل على بوت — لا نخمّن له شخصية.
 *  3. **استثناء الحساب.** يفوز على الفئة في الاتجاهين: يفتح لحساب في فئة
 *     مغلقة، ويغلق على حساب في فئة مفتوحة. هذا ما طلبه صاحب المنصة حرفيًا.
 *  4. **الفئات المفعَّلة** من إعدادات المؤسسة — تُحرَّر من اللوحة، وتبدأ
 *     بالمعلمين ومشرف الجودة.
 *
 * ترتيب 3 قبل 4 مقصود: القرار الصريح في حساب بعينه أولى من الافتراضي العام.
 */
final readonly class AccessResolver
{
    public function __construct(
        private LlmConnections $connections,
        private AccessControlQuerier $accessControl,
        private OrganizationSettingQueries $settings,
    ) {}

    /**
     * @param class-string|string $userMorphClass
     */
    public function resolve(string $organizationId, string $userId, string $userMorphClass): BotAccess
    {
        if (!$this->connections->isEnabled($organizationId)) {
            return BotAccess::denied('bot_disabled');
        }

        $audience = $this->audienceFor($userMorphClass, $userId);

        if (!$audience instanceof BotAudience) {
            return BotAccess::denied('no_audience');
        }

        $override = $this->override($organizationId, $userId);

        if ($override !== null) {
            return $override
                ? BotAccess::granted($audience)
                : BotAccess::denied('account_disabled');
        }

        return $this->audienceEnabled($organizationId, $audience)
            ? BotAccess::granted($audience)
            : BotAccess::denied('audience_disabled');
    }

    /**
     * أسماء الأدوار تُقرأ هنا لتحديد **النبرة** فقط. كل قرار وصول إلى بيانات
     * يمر على `$user->can()` وسياسات المنصة في موضعه، لا على هذه الخريطة.
     */
    private function audienceFor(string $userMorphClass, string $userId): ?BotAudience
    {
        $roles = $this->accessControl->rolesForModel($userMorphClass, $userId);

        $names = array_values(array_map(
            static fn (object $role): string => (string) $role->name,
            $roles,
        ));

        return BotAudience::fromRoleNames($names);
    }

    /** null = لا استثناء لهذا الحساب، فيُتبع الافتراضي. */
    private function override(string $organizationId, string $userId): ?bool
    {
        $row = BotAccountAccess::query()
            ->forOrganization($organizationId)
            ->where('user_id', $userId)
            ->first();

        return $row?->enabled;
    }

    private function audienceEnabled(string $organizationId, BotAudience $audience): bool
    {
        $key = config('support_bot.audiences_setting_key');
        $stored = is_string($key) ? $this->settings->value($organizationId, $key) : null;

        $enabled = is_array($stored) ? $stored : config('support_bot.default_audiences');

        if (!is_array($enabled)) {
            return false;
        }

        return in_array($audience->value, $enabled, true);
    }
}
