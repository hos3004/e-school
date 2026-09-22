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
 *   الوصول ← الطول ← الحدود ← الجلسة ← **التصنيف** ← **الحارس**
 *   ← (إن سمح) الصياغة ← **الفلتر البعدي** ← الأرشفة
 *
 * ثلاث نقاط تستحق التوضيح:
 *
 *  - **مفتاح الإيقاف يُفحَص مرتين**: مرة عند الوصول ومرة قبل الصياغة. الدرس
 *    مأخوذ من حملات الواتساب التي تفحص المفتاح قبل كل رسالة لا مرة واحدة عند
 *    الإطلاق؛ الأدمن الذي يضغط «إيقاف» يتوقع أن يتوقف كل شيء الآن.
 *
 *  - **الوضع غير Allow لا ينادي النموذج إطلاقًا.** ليس توفيرًا: ما دام النموذج
 *    لم يُستدعَ فلا يوجد احتمال أن يذكر رقمًا، مهما كان السؤال أو تكراره.
 *
 *  - **كل مسار فشل يُنهي برد معدّ.** لا استثناء يصعد إلى المستخدم، ولا فقاعة
 *    فارغة. عطل المزوّد ينتهي باعتذار وإحالة إلى الواتساب الرسمي.
 */
final readonly class AskSupportBotAction
{
    public function __construct(
        private AccessResolver $access,
        private ConversationStore $conversations,
        private TopicClassifier $classifier,
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

        $this->conversations->recordUserMessage($conversation, $message, $correlationId);

        $classification = $this->classifier->classify(
            $organizationId,
            $message,
            $this->conversations->recentUserMessages($conversation),
        );

        $this->usage->record($organizationId, $userId, $classification->inputTokens, $classification->outputTokens);

        if (!$classification->succeeded()) {
            return $this->canned(
                $conversation,
                'provider_unavailable',
                $correlationId,
                null,
                $classification->failureReason,
            );
        }

        $topic = $classification->topic ?? BotTopic::Unknown;
        $decision = $this->guardrail->resolve($organizationId, $topic, $audience);

        if (!$decision->allowsGeneration()) {
            return $this->canned($conversation, $decision->replyKey, $correlationId, $decision);
        }

        // الفحص الثاني للمفتاح: قد يكون الأدمن أوقفه أثناء هذا الدور نفسه.
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
            $this->conversations->history($conversation),
        );

        $this->usage->record($organizationId, $userId, $result->inputTokens, $result->outputTokens);

        if (!$result->isAccepted()) {
            return $this->canned($conversation, 'provider_unavailable', $correlationId, $decision, $result->error());
        }

        $text = $result->text();

        /*
         * ردّ مبتور لبلوغ سقف التوكِن يصل كنصف جملة. الرد المعدّ أوضح من نص
         * ينقطع في منتصفه ويترك المستخدم يخمّن البقية.
         */
        if ($result->wasTruncated()) {
            return $this->canned($conversation, $decision->replyKey, $correlationId, $decision, 'answer_truncated');
        }

        if (!$this->filter->passes($text)) {
            return $this->canned($conversation, $decision->replyKey, $correlationId, $decision, 'output_filtered');
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

        return new BotReply(
            $text,
            $topic,
            $decision->mode,
            true,
            (string) $conversation->id,
            $correlationId,
        );
    }

    /**
     * رد معدّ: يُسجَّل في الأرشيف كأي رد، ويحمل سبب اللجوء إليه.
     */
    private function canned(
        BotConversation $conversation,
        string $replyKey,
        string $correlationId,
        ?GuardrailDecision $decision,
        ?string $failureReason = null,
    ): BotReply {
        $body = $this->replyBody((string) $conversation->organization_id, $replyKey, $conversation->locale);

        $this->conversations->recordBotMessage(
            $conversation,
            $body,
            $correlationId,
            $decision,
            wasGenerated: false,
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

    /**
     * نص الرد المعدّ، مع تدرّج احتياطي حتى لا يخرج رد فارغ أبدًا.
     */
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

        // آخر ملاذ: قاعدة البيانات خالية أو معطّلة بالكامل.
        return __('supportbot::replies.last_resort', [], $locale);
    }

    private function maxMessageLength(): int
    {
        $configured = (int) config('support_bot.conversation.max_message_characters', 2000);

        return $configured > 0 ? $configured : 2000;
    }
}
