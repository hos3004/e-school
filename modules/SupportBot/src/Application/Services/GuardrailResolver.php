<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Modules\SupportBot\Domain\Models\BotRule;
use Modules\SupportBot\Domain\ValueObjects\GuardrailDecision;

/**
 * الحارس: ماذا يفعل البوت في هذا الموضوع أمام هذه الفئة.
 *
 * ثلاث طبقات مرتبة، الأعلى يفوز:
 *
 *  1. **سياج الكود.** المواضيع المالية لا تُولَّد أبدًا، مهما قال الجدول.
 *     الجدول قابل للتحرير من اللوحة، والتحرير الخاطئ وارد؛ هذا السياج هو ما
 *     يجعل الخطأ غير قادر على كشف مبلغ.
 *
 *  2. **قاعدة المؤسسة، ثم القاعدة العامة.** نفس دلالة التغطية في قوالب
 *     الإشعارات: صف المؤسسة يفوز على الصف العام عند وجوده.
 *
 *  3. **الافتراضي من الإعداد** حين لا توجد قاعدة أصلًا — وهو «إرشاد» لا
 *     «سماح»، فالموضوع الذي لم يقرر فيه أحد لا يُفتح من تلقاء نفسه.
 *
 * ملاحظة على ما لا يفعله هذا الصنف: لا يقرأ بيانات ولا يفحص صلاحيات. الوضع
 * Allow يعني «يجوز أن نصوغ ردًّا»، لا «يجوز أن يرى أي شيء». ما يراه تحكمه
 * صلاحياته في المنصة عند قراءة البيانات، لا هذا الجدول.
 */
final readonly class GuardrailResolver
{
    public function resolve(string $organizationId, BotTopic $topic, BotAudience $audience): GuardrailDecision
    {
        $rule = $this->rule($organizationId, $topic, $audience);

        $mode = $rule?->mode ?? $this->defaultMode();
        $replyKey = $this->replyKey($rule?->reply_key);

        /*
         * السياج: موضوع يكشف أرقامًا لا يُولَّد فيه ردّ حر إطلاقًا. نخفّضه إلى
         * إرشاد — لا إلى منع — لأن المطلوب أن يدلّ على الطريق بلباقة، وهذا
         * بالضبط ما طلبه صاحب المنصة.
         */
        if ($topic->disclosesFigures() && $mode === TopicMode::Allow) {
            return new GuardrailDecision($topic, TopicMode::Guide, $replyKey, true);
        }

        return new GuardrailDecision($topic, $mode, $replyKey);
    }

    private function rule(string $organizationId, BotTopic $topic, BotAudience $audience): ?BotRule
    {
        $candidates = BotRule::query()
            ->active()
            ->forOrganizationOrGlobal($organizationId)
            ->where('topic', $topic->value)
            ->where('audience', $audience->value)
            ->get();

        // صف المؤسسة يغطّي الصف العام.
        return $candidates->firstWhere('organization_id', $organizationId)
            ?? $candidates->firstWhere('organization_id', null);
    }

    private function defaultMode(): TopicMode
    {
        $configured = config('support_bot.default_mode');

        return is_string($configured)
            ? (TopicMode::tryFrom($configured) ?? TopicMode::Guide)
            : TopicMode::Guide;
    }

    private function replyKey(?string $fromRule): string
    {
        if (is_string($fromRule) && trim($fromRule) !== '') {
            return trim($fromRule);
        }

        $fallback = config('support_bot.fallback_reply_key');

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : 'fallback_contact';
    }
}
