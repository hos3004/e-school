<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\SupportBot\Domain\Models\BotUsage;

/**
 * عدّاد الاستهلاك وحدوده.
 *
 * يوجد لأن المشروع **لا يملك أي حساب تكلفة ولا حدًّا عامًّا للطلبات**. أي مسار
 * جديد يبدأ بلا سقف، وهذا المسار يكلّف مالًا عند كل رسالة.
 *
 * ثلاثة قرارات:
 *
 *  - **`requests` تعدّ أدوار المحادثة لا نداءات المزوّد.** الدور المسموح ينادي
 *    المزوّد مرتين (تصنيف ثم صياغة) والمحجوب مرة واحدة؛ لو عُدّت النداءات لصار
 *    حد «٦٠ رسالة يوميًا» ثلاثين لمن يسأل أسئلة مشروعة وستين لمن يسأل عن المال.
 *
 *  - **الكتابة ذرّية بلا سباق.** صفّ اليوم يُنشأ بـinsertOrIgnore ثم تُزاد القيم
 *    بتحديث واحد في القاعدة. القراءة ثم الإنشاء كانت تجعل رسالتين متزامنتين
 *    أولَ اليوم تصطدمان بالفهرس الفريد، فترمي الثانية خطأً بعد أن كلّفت فعلًا.
 *
 *  - **السعر حسب النموذج، والمجهول بأغلى سعر.** رفع نموذج الرد تغيير إعداد لا
 *    نشر؛ لو حُسب بسعر النموذج الرخيص لما عاد السقف اليومي يسقف شيئًا.
 *
 * كل الحسابات أعداد صحيحة بوحدات صغرى: المشروع يمنع float في حساب المال.
 */
final readonly class UsageAccountant
{
    /**
     * سعر الملاذ الأخير إن غاب الإعداد نفسه: ألف دولار لكل مليون توكِن، أي أن
     * أول نداء يستنفد السقف اليومي. الفشل هنا يجب أن يوقف الإنفاق لا أن يبيحه.
     */
    private const int FAIL_CLOSED_PRICE = 1_000_000_000;

    public function denialReason(string $organizationId, string $userId): ?string
    {
        $perDay = (int) config('support_bot.limits.messages_per_day', 0);

        if ($perDay > 0) {
            $turns = (int) BotUsage::query()
                ->forOrganization($organizationId)
                ->where('user_id', $userId)
                ->whereDate('usage_date', $this->today())
                ->value('requests');

            if ($turns >= $perDay) {
                return 'daily_message_cap';
            }
        }

        $costCap = (int) config('support_bot.limits.daily_cost_cap_micro_usd', 0);

        if ($costCap > 0 && $this->organizationSpendToday($organizationId) >= $costCap) {
            return 'daily_cost_cap';
        }

        return null;
    }

    public function organizationSpendToday(string $organizationId): int
    {
        return (int) BotUsage::query()
            ->forOrganization($organizationId)
            ->whereDate('usage_date', $this->today())
            ->sum('cost_micro_usd');
    }

    /**
     * تسجيل نداء واحد للمزوّد.
     *
     * يُسجَّل حتى لو فشل النداء ما دام المزوّد أعاد عدّادات: المحاولة الفاشلة بعد
     * توليد جزئي كلّفت فعلًا، وتجاهلها يجعل السقف يتسرّب في حالة العطل تحديدًا.
     *
     * @param bool $opensTurn صحيح لأول نداء في الدور وحده، فيُعدّ الدور مرة واحدة.
     */
    public function record(
        string $organizationId,
        string $userId,
        string $model,
        int $inputTokens,
        int $outputTokens,
        bool $opensTurn,
    ): void {
        $today = $this->today();
        $now = now('UTC');

        DB::table('support_bot_usage')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'usage_date' => $today,
            'requests' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_micro_usd' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $input = max(0, $inputTokens);
        $output = max(0, $outputTokens);

        DB::table('support_bot_usage')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('usage_date', $today)
            ->update([
                'requests' => DB::raw('requests + '.($opensTurn ? 1 : 0)),
                'input_tokens' => DB::raw('input_tokens + '.$input),
                'output_tokens' => DB::raw('output_tokens + '.$output),
                'cost_micro_usd' => DB::raw('cost_micro_usd + '.$this->cost($model, $input, $output)),
                'updated_at' => $now,
            ]);
    }

    /** التكلفة بالميكرو-دولار، بأعداد صحيحة وتقريب لأعلى. */
    public function cost(string $model, int $inputTokens, int $outputTokens): int
    {
        ['input' => $inputPrice, 'output' => $outputPrice] = $this->priceFor($model);

        return $this->ceilDiv(max(0, $inputTokens) * $inputPrice, 1_000_000)
            + $this->ceilDiv(max(0, $outputTokens) * $outputPrice, 1_000_000);
    }

    /**
     * @return array{input: int, output: int}
     */
    private function priceFor(string $model): array
    {
        $table = config('llm.pricing.models');
        $row = is_array($table) ? ($table[$model] ?? null) : null;

        if (is_array($row) && is_int($row['input'] ?? null) && is_int($row['output'] ?? null)) {
            return ['input' => $row['input'], 'output' => $row['output']];
        }

        // نموذج بلا سعر مسجَّل يُحسب بالسعر الاحتياطي المتشائم لا بصفر.
        $fallback = config('llm.pricing.unpriced_model');

        return [
            'input' => is_array($fallback) && is_int($fallback['input'] ?? null) ? $fallback['input'] : self::FAIL_CLOSED_PRICE,
            'output' => is_array($fallback) && is_int($fallback['output'] ?? null) ? $fallback['output'] : self::FAIL_CLOSED_PRICE,
        ];
    }

    private function ceilDiv(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator <= 0) {
            return 0;
        }

        return intdiv($numerator + $denominator - 1, $denominator);
    }

    /**
     * يوم المحاسبة بتوقيت إداري صريح لا بتوقيت التطبيق.
     *
     * توقيت التطبيق هنا UTC، فكان السقف اليومي سيتجدد الثالثة فجرًا بتوقيت
     * القاهرة لا منتصف الليل. التوقيت إعداد لا رقم في الكود.
     */
    private function today(): string
    {
        $timezone = config('support_bot.limits.accounting_timezone');

        return now(is_string($timezone) && $timezone !== '' ? $timezone : 'UTC')->toDateString();
    }
}
