<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\MessageRole;
use Modules\SupportBot\Domain\Models\BotConversation;
use Modules\SupportBot\Domain\Models\BotMessage;
use Modules\SupportBot\Domain\ValueObjects\GuardrailDecision;

/**
 * الجلسة والأرشيف.
 *
 * **«الذاكرة تتصفّر بانتهاء الجلسة» منفَّذة هنا، لا في النموذج.** الجلسة تُقفَل
 * بعد خمول تحدّده الإعدادات، وسجلّها لا يدخل سياق أي جلسة بعدها إطلاقًا. البوت
 * إذن لا يتذكّر، بينما يبقى الأرشيف للمراجعة والتقارير.
 *
 * الجلسة تُنشأ مع أول رسالة فقط، لا عند فتح صفحة: الودجت يسأل عن السجلّ في كل
 * تحميل، ولو أنشأ ذلك جلسة لامتلأ الأرشيف بجلسات فارغة تدفن المحادثات الحقيقية.
 */
final readonly class ConversationStore
{
    /**
     * الجلسة المفتوحة غير الخاملة إن وُجدت. قراءة فقط — لا تنشئ ولا تُقفل.
     */
    public function open(string $organizationId, string $userId): ?BotConversation
    {
        $open = $this->latestOpen($organizationId, $userId);

        return $open instanceof BotConversation && !$this->isIdle($open) ? $open : null;
    }

    /** الجلسة المفتوحة غير الخاملة، أو جلسة جديدة. يُستدعى عند إرسال رسالة. */
    public function current(
        string $organizationId,
        string $userId,
        BotAudience $audience,
        string $locale,
    ): BotConversation {
        $open = $this->latestOpen($organizationId, $userId);

        if ($open instanceof BotConversation) {
            if (!$this->isIdle($open)) {
                return $open;
            }

            $this->close($open);
        }

        try {
            return BotConversation::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'audience' => $audience->value,
                'locale' => $locale,
                'started_at' => now('UTC'),
                'message_count' => 0,
                'blocked_count' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            /*
             * طلب متزامن من المستخدم نفسه سبقنا إلى إنشاء الجلسة، والفهرس الجزئي
             * منع جلسة ثانية. نعود إلى جلسته بدل أن ننشئ نسخة تقسم ذاكرتها.
             */
            $winner = $this->latestOpen($organizationId, $userId);

            if ($winner instanceof BotConversation) {
                return $winner;
            }

            throw new \RuntimeException('support_bot.conversation_race_unresolved');
        }
    }

    /**
     * سجلّ الجلسة الحالية وحدها، الأقدم أولًا.
     *
     * @return list<array{role: string, body: string}>
     */
    public function history(BotConversation $conversation): array
    {
        $limit = max(0, (int) config('support_bot.conversation.max_history_turns', 8)) * 2;

        if ($limit === 0) {
            return [];
        }

        /*
         * الترتيب بـid إلى جانب الوقت: رسالتا الدور الواحد تُكتبان في اللحظة
         * نفسها، فالوقت وحده لا يفصل بينهما. وULID مرتّب زمنيًا فيحسمها.
         */
        return BotMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(static fn (BotMessage $message): array => [
                'role' => $message->role->value,
                'body' => $message->body,
            ])
            ->values()
            ->all();
    }

    /**
     * رسائل المستخدم وحدها في هذه الجلسة — يحتاجها المصنِّف للأسئلة المتصلة.
     *
     * @return list<string>
     */
    public function recentUserMessages(BotConversation $conversation, int $limit = 3): array
    {
        return BotMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->map(static fn (BotMessage $message): string => $message->body)
            ->values()
            ->all();
    }

    public function recordUserMessage(BotConversation $conversation, string $body, string $correlationId): BotMessage
    {
        return $this->write($conversation, [
            'role' => MessageRole::User->value,
            'body' => $body,
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * @param bool $withheld صحيح متى وصل المستخدمَ ردٌّ معدّ بدل إجابة مصوغة —
     *                       سواء قرره الحارس أو التقطه الفلتر بعد التوليد. هذا
     *                       ما يعدّه blocked_count، لا وضع القاعدة وحده.
     */
    public function recordBotMessage(
        BotConversation $conversation,
        string $body,
        string $correlationId,
        ?GuardrailDecision $decision = null,
        bool $wasGenerated = false,
        bool $withheld = false,
        ?string $model = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?int $latencyMilliseconds = null,
        ?string $failureReason = null,
    ): BotMessage {
        $message = $this->write($conversation, [
            'role' => MessageRole::Bot->value,
            'body' => $body,
            'topic' => $decision?->topic->value,
            'mode' => $decision?->mode->value,
            'was_generated' => $wasGenerated,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_milliseconds' => $latencyMilliseconds,
            'failure_reason' => $failureReason,
            'correlation_id' => $correlationId,
        ]);

        if ($withheld) {
            $conversation->increment('blocked_count');
        }

        return $message;
    }

    public function close(BotConversation $conversation): void
    {
        if ($conversation->isClosed()) {
            return;
        }

        $conversation->forceFill(['closed_at' => now('UTC')])->save();
    }

    private function latestOpen(string $organizationId, string $userId): ?BotConversation
    {
        return BotConversation::query()
            ->forOrganization($organizationId)
            ->where('user_id', $userId)
            ->open()
            // جلسة لم تُكتب فيها رسالة بعد تحمل last_message_at فارغًا، وPostgreSQL
            // يضع الفارغ أولًا في الترتيب التنازلي — فنرتّب بأحدث نشاط فعلي.
            ->orderByRaw('COALESCE(last_message_at, started_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function write(BotConversation $conversation, array $attributes): BotMessage
    {
        // created_at يضبطه Eloquent وحده: UPDATED_AT = null يبقي created_at مُدارًا.
        $message = BotMessage::query()->create([
            'conversation_id' => $conversation->id,
            'organization_id' => $conversation->organization_id,
            ...$attributes,
        ]);

        $conversation->increment('message_count', 1, ['last_message_at' => now('UTC')]);

        return $message;
    }

    private function isIdle(BotConversation $conversation): bool
    {
        $minutes = (int) config('support_bot.conversation.idle_timeout_minutes', 45);

        if ($minutes < 1) {
            return false;
        }

        $reference = $conversation->last_message_at ?? $conversation->started_at;

        return $reference->addMinutes($minutes)->isPast();
    }
}
