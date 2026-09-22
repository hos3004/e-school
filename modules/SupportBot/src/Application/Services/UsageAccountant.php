<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\SupportBot\Domain\Models\BotUsage;

/**
 * عدّاد الاستهلاك وحدوده.
 *
 * يوجد لأن المشروع **لا يملك أي حساب تكلفة ولا حدًّا عامًّا للطلبات**: مفاتيح
 * rate_limit_per_minute موجودة في إعدادات الإشعارات ولا يقرؤها كود، وbootstrap
 * لا يفعّل throttleApi. أي مسار جديد يبدأ بلا سقف، وهذا المسار يكلّف مالًا عند
 * كل رسالة.
 *
 * كل الحسابات بأعداد صحيحة بوحدات صغرى. المشروع يمنع float في حساب المال،
 * والتقريب المتكرر عبر آلاف النداءات ينحرف بصمت.
 *
 * التقريب لأعلى مقصود: أن نظن أننا أنفقنا أكثر قليلًا أأمن من أن نتجاوز السقف
 * ونكتشف الفرق في الفاتورة.
 */
final readonly class UsageAccountant
{
    /**
     * سبب المنع إن بلغ حدًّا، أو null إن كان الطريق مفتوحًا.
     *
     * تُستدعى قبل أي نداء للمزوّد — بما في ذلك نداء التصنيف، فهو يكلّف أيضًا.
     */
    public function denialReason(string $organizationId, string $userId): ?string
    {
        $today = $this->today();

        $perDay = (int) config('support_bot.limits.messages_per_day', 0);

        if ($perDay > 0) {
            $requests = (int) BotUsage::query()
                ->forOrganization($organizationId)
                ->where('user_id', $userId)
                ->whereDate('usage_date', $today)
                ->value('requests');

            if ($requests >= $perDay) {
                return 'daily_message_cap';
            }
        }

        $costCap = (int) config('support_bot.limits.daily_cost_cap_micro_usd', 0);

        if ($costCap > 0 && $this->organizationSpendToday($organizationId) >= $costCap) {
            return 'daily_cost_cap';
        }

        return null;
    }

    /** إنفاق المؤسسة اليوم بالميكرو-دولار. */
    public function organizationSpendToday(string $organizationId): int
    {
        return (int) BotUsage::query()
            ->forOrganization($organizationId)
            ->whereDate('usage_date', $this->today())
            ->sum('cost_micro_usd');
    }

    /**
     * تسجيل نداء واحد.
     *
     * يُسجَّل حتى لو فشل النداء ما دام المزوّد أعاد عدّادات: المحاولة الفاشلة
     * بعد توليد جزئي كلّفت فعلًا، وتجاهلها يجعل السقف يتسرّب.
     *
     * الزيادة ذرّية عبر upsert ثم increment، فلا يضيع عدّ حين يسأل المستخدم من
     * نافذتين في اللحظة نفسها.
     */
    public function record(string $organizationId, string $userId, int $inputTokens, int $outputTokens): void
    {
        $cost = $this->cost($inputTokens, $outputTokens);
        $today = $this->today();

        DB::transaction(function () use ($organizationId, $userId, $today, $inputTokens, $outputTokens, $cost): void {
            $row = BotUsage::query()
                ->forOrganization($organizationId)
                ->where('user_id', $userId)
                ->whereDate('usage_date', $today)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                BotUsage::query()->create([
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'usage_date' => $today,
                    'requests' => 1,
                    'input_tokens' => max(0, $inputTokens),
                    'output_tokens' => max(0, $outputTokens),
                    'cost_micro_usd' => $cost,
                ]);

                return;
            }

            $row->forceFill([
                'requests' => $row->requests + 1,
                'input_tokens' => $row->input_tokens + max(0, $inputTokens),
                'output_tokens' => $row->output_tokens + max(0, $outputTokens),
                'cost_micro_usd' => $row->cost_micro_usd + $cost,
            ])->save();
        });
    }

    /** التكلفة بالميكرو-دولار، بأعداد صحيحة وتقريب لأعلى. */
    public function cost(int $inputTokens, int $outputTokens): int
    {
        $inputPrice = (int) config('llm.providers.anthropic.pricing.input_micro_usd_per_million', 0);
        $outputPrice = (int) config('llm.providers.anthropic.pricing.output_micro_usd_per_million', 0);

        return $this->ceilDiv(max(0, $inputTokens) * $inputPrice, 1_000_000)
            + $this->ceilDiv(max(0, $outputTokens) * $outputPrice, 1_000_000);
    }

    private function ceilDiv(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator <= 0) {
            return 0;
        }

        return intdiv($numerator + $denominator - 1, $denominator);
    }

    /**
     * اليوم بتوقيت التطبيق لا بتوقيت UTC.
     *
     * السقف اليومي مفهوم إداري: صاحب المنصة يريد «كم أنفقنا اليوم» بيومه هو،
     * لا بيوم ينتهي في منتصف نهاره.
     */
    private function today(): string
    {
        return now(config('app.timezone'))->toDateString();
    }
}
