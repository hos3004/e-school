<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Actions;

use Illuminate\Support\Str;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\SupportBot\Application\Services\AccessResolver;
use Modules\SupportBot\Application\Services\AnswerComposer;
use Modules\SupportBot\Application\Services\ConversationStore;
use Modules\SupportBot\Application\Services\GuardrailResolver;
use Modules\SupportBot\Application\Services\KnowledgeResolver;
use Modules\SupportBot\Application\Services\MoneyIntentDetector;
use Modules\SupportBot\Application\Services\OutputFilter;
use Modules\SupportBot\Application\Services\TopicClassifier;
use Modules\SupportBot\Application\Services\UsageAccountant;
use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Modules\SupportBot\Domain\Models\BotConversation;
use Modules\SupportBot\Domain\ValueObjects\BotAccess;
use Modules\SupportBot\Domain\ValueObjects\BotReply;
use Modules\SupportBot\Domain\ValueObjects\GuardrailDecision;

/**
 * دور واحد كامل: سؤال المستخدم ← رد البوت.
 *
 * ترتيب الخطوات هو الأمان نفسه، ولا يجوز تبديله:
 *
 *   الوصول ← الطول ← الحدود ← الجلسة ← **التصنيف** ← **الكاشف المالي**
 *   ← **الحارس** ← (إن سمح) الصياغة ← **الفلتر البعدي** ← الأرشفة
 *
 *  - **الكاشف المالي يقرأ نص السؤال لا تصنيفه.** سياج BotTopic يحرس أسماء
 *    مواضيع؛ لو أُقنع المصنِّف أن سؤال المستحقات «مساعدة في المنصة» لما رأى
 *    السياج شيئًا. الكاشف يطابق كلمات ولا يمكن إقناعه، فيعيد السؤال إلى موضوعه
 *    المالي قبل أن يقرر الحارس.
 *
 *  - **الوضع غير Allow لا ينادي نموذج الصياغة إطلاقًا.** ما دام لم يُستدعَ فلا
 *    احتمال لأن يذكر رقمًا، مهما تكرر السؤال.
 *
 *  - **مفتاح الإيقاف يُفحَص مرتين**: عند الوصول وقبل الصياغة، فالإيقاف أثناء
 *    الدور نفسه يوقفه.
 *
 *  - **كل مسار فشل ينتهي برد معدّ**: لا استثناء يصعد إلى المستخدم ولا فقاعة
 *    فارغة.
 *
 * ملاحظة للمالك: رسالة المستخدم تصل مزوّد النموذج في مرحلة التصنيف دائمًا —
 * «بلا نداء» في وضعَي الإرشاد والاعتذار تعني بلا نداء *صياغة*.
 */
final readonly class AskSupportBotAction
{
    public function __construct(
        private AccessResolver $access,
        private ConversationStore $conversations,
        private TopicClassifier $classifier,
        private MoneyIntentDetector $moneyIntent,
        private GuardrailResolver $guardrail,
        private AnswerComposer $composer,
        private OutputFilter $filter,
        private UsageAccountant $usage,
        private KnowledgeResolver $knowledge,
        private LlmConnections $connections,
    ) {}

    public function execute(
        string $organizationId,
        string $userId,
        string $userMorphClass,
        string $message,
        string $locale,
    ): BotReply {
        $correlationId = (string) Str::ulid();
        $message = trim($message);

        $access = $this->access->resolve($organizationId, $userId, $userMorphClass);

        if (!$access->allowed) {
            return $this->refuse($organizationId, $locale, $correlationId, $access);
        }

        $audience = $access->audience;

        if (!$audience instanceof BotAudience) {
            return $this->refuse($organizationId, $locale, $correlationId, BotAccess::denied('no_audience'));
        }

        $conversation = $this->conversations->current($organizationId, $userId, $audience, $locale);

        if ($message === '' || mb_strlen($message) > $this->maxMessageLength()) {
            return $this->canned($conversation, 'message_too_long', $correlationId, null, 'message_length');
        }

        $limit = $this->usage->denialReason($organizationId, $userId);

        if ($limit !== null) {
            $this->conversations->recordUserMessage($conversation, $message, $correlationId);

            return $this->canned($conversation, 'rate_limited', $correlationId, null, $limit);
        }

        /*
         * السياق يُقرأ قبل تسجيل الرسالة الحالية. لو قُرئ بعدها لظهر السؤال
         * للنموذج مرتين — وهي بالضبط العلامة التي يعاملها توجيه «الإلحاح»
         * كتكرار من المستخدم.
         */
        $recent = $this->conversations->recentUserMessages($conversation);
        $history = $this->conversations->history($conversation);

        $this->conversations->recordUserMessage($conversation, $message, $correlationId);

        $classification = $this->classifier->classify($organizationId, $message, $recent);

        $this->usage->record(
            $organizationId,
            $userId,
            $classification->model,
            $classification->inputTokens,
            $classification->outputTokens,
            opensTurn: true,
        );

        if (!$classification->succeeded()) {
            return $this->canned(
                $conversation,
                'provider_unavailable',
                $correlationId,
                null,
                $classification->failureReason,
            );
        }

        $classified = $classification->topic ?? BotTopic::Unknown;
        $topic = $this->effectiveTopic($classified, $message);
        $decision = $this->guardrail->resolve($organizationId, $topic, $audience);

        if (!$decision->allowsGeneration()) {
            return $this->canned(
                $conversation,
                $decision->replyKey,
                $correlationId,
                $decision,
                $this->withholdingReason($decision, $classified, $topic),
                withheld: true,
            );
        }

        if (!$this->connections->isEnabled($organizationId)) {
            return $this->canned($conversation, 'provider_unavailable', $correlationId, $decision, 'bot_disabled');
        }

        $result = $this->composer->compose(
            $organizationId,
            $userId,
            $audience,
            $topic,
            $locale,
            $message,
            $history,
        );

        $this->usage->record(
            $organizationId,
            $userId,
            $result->model,
            $result->inputTokens,
            $result->outputTokens,
            opensTurn: false,
        );

        if (!$result->isAccepted()) {
            return $this->canned($conversation, 'provider_unavailable', $correlationId, $decision, $result->error());
        }

        /*
         * ردّ مبتور لبلوغ سقف التوكِن يصل كنصف جملة؛ والرد الذي التقطه الفلتر
         * ذكر ما لا يُذكر. كلاهما يُستبدل كاملًا بالرد المعدّ.
         */
        if ($result->wasTruncated()) {
            return $this->canned($conversation, $decision->replyKey, $correlationId, $decision, 'answer_truncated', withheld: true);
        }

        $text = $result->text();

        if (!$this->filter->passes($text)) {
            return $this->canned($conversation, $decision->replyKey, $correlationId, $decision, 'output_filtered', withheld: true);
        }

        $this->conversations->recordBotMessage(
            $conversation,
            $text,
            $correlationId,
            $decision,
            wasGenerated: true,
            model: $result->model,
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            latencyMilliseconds: $result->latencyMilliseconds,
        );

        return new BotReply($text, $topic, $decision->mode, true, (string) $conversation->id, $correlationId);
    }

    /**
     * التصنيف يُحترم ما لم يكشف النص نية مالية لم يلتقطها المصنِّف.
     *
     * الاتجاه واحد فقط: الكاشف يستطيع أن يجعل السؤال ماليًّا، ولا يستطيع أن يجعل
     * سؤالًا صنّفه النموذج ماليًّا سؤالًا عاديًّا.
     */
    private function effectiveTopic(BotTopic $classified, string $message): BotTopic
    {
        if ($classified->disclosesFigures()) {
            return $classified;
        }

        return $this->moneyIntent->detect($message) ?? $classified;
    }

    /**
     * سبب يُحفظ في الأرشيف حين يحجب طرفٌ غير القاعدة نفسها: ليعرف من يراجع أن
     * الكود — لا جدول الحدود — هو ما أوقف الإجابة.
     */
    private function withholdingReason(GuardrailDecision $decision, BotTopic $classified, BotTopic $effective): ?string
    {
        if ($classified !== $effective) {
            return 'money_intent_detected';
        }

        return $decision->clampedByCode ? 'clamped_by_code' : null;
    }

    private function canned(
        BotConversation $conversation,
        string $replyKey,
        string $correlationId,
        ?GuardrailDecision $decision,
        ?string $failureReason = null,
        bool $withheld = false,
    ): BotReply {
        $body = $this->replyBody((string) $conversation->organization_id, $replyKey, $conversation->locale);

        $this->conversations->recordBotMessage(
            $conversation,
            $body,
            $correlationId,
            $decision,
            wasGenerated: false,
            withheld: $withheld,
            failureReason: $failureReason,
        );

        return new BotReply(
            $body,
            $decision?->topic,
            $decision?->mode ?? TopicMode::Guide,
            false,
            (string) $conversation->id,
            $correlationId,
            $failureReason,
        );
    }

    /**
     * رفض قبل أن تُفتح جلسة أصلًا — لا أرشفة هنا: من لا بوت له لا محادثة له.
     */
    private function refuse(
        string $organizationId,
        string $locale,
        string $correlationId,
        BotAccess $access,
    ): BotReply {
        return new BotReply(
            $this->replyBody($organizationId, 'fallback_contact', $locale),
            null,
            TopicMode::Deny,
            false,
            '',
            $correlationId,
            $access->denialReason,
        );
    }

    private function replyBody(string $organizationId, string $replyKey, string $locale): string
    {
        $body = $this->knowledge->reply($organizationId, $replyKey, $locale);

        if (is_string($body) && trim($body) !== '') {
            return trim($body);
        }

        $fallbackKey = (string) config('support_bot.fallback_reply_key', 'fallback_contact');

        if ($fallbackKey !== $replyKey) {
            $fallback = $this->knowledge->reply($organizationId, $fallbackKey, $locale);

            if (is_string($fallback) && trim($fallback) !== '') {
                return trim($fallback);
            }
        }

        return __('supportbot::replies.last_resort', [], $locale);
    }

    private function maxMessageLength(): int
    {
        $configured = (int) config('support_bot.conversation.max_message_characters', 2000);

        return $configured > 0 ? $configured : 2000;
    }
}
