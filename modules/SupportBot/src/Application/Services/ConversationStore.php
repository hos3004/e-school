<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Modules\SupportBot\Domain\Enums\BotAudience;
use Modules\SupportBot\Domain\Enums\MessageRole;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Modules\SupportBot\Domain\Models\BotConversation;
use Modules\SupportBot\Domain\Models\BotMessage;
use Modules\SupportBot\Domain\ValueObjects\GuardrailDecision;

/**
 * الجلسة والأرشيف.
 *
 * **«الذاكرة تتصفّر بانتهاء الجلسة» منفَّذة هنا، لا في النموذج.** الجلسة تُقفَل
 * بعد خمول تحدّده الإعدادات، وسجلّها لا يدخل سياق أي جلسة بعدها إطلاقًا. البوت
 * إذن لا يتذكّر، بينما يبقى الأرشيف كاملًا للمراجعة والتقارير — وهما مطلبان
 * مختلفان لا يتعارضان.
 *
 * كل رسالة تُحفظ بقرار الحارس الذي أنتجها وقتها، لا محسوبًا لاحقًا: القواعد
 * قابلة للتحرير، وإعادة حسابها بعد شهر تعطي تفسيرًا مختلفًا لما جرى فعلًا.
 */
final readonly class ConversationStore
{
    /** الجلسة المفتوحة غير الخاملة، أو جلسة جديدة. */
    public function current(
        string $organizationId,
        string $userId,
        BotAudience $audience,
        string $locale,
    ): BotConversation {
        $open = BotConversation::query()
            ->forOrganization($organizationId)
            ->where('user_id', $userId)
            ->open()
            ->orderByDesc('last_message_at')
            ->orderByDesc('started_at')
            ->first();

        if ($open instanceof BotConversation) {
            if (!$this->isIdle($open)) {
                return $open;
            }

            $this->close($open);
        }

        return BotConversation::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'audience' => $audience->value,
            'locale' => $locale,
            'started_at' => now('UTC'),
            'message_count' => 0,
            'blocked_count' => 0,
        ]);
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

        return BotMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
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

    public function recordBotMessage(
        BotConversation $conversation,
        string $body,
        string $correlationId,
        ?GuardrailDecision $decision = null,
        bool $wasGenerated = false,
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

        if ($decision !== null && $decision->mode !== TopicMode::Allow) {
            $conversation->forceFill(['blocked_count' => $conversation->blocked_count + 1])->save();
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

        $conversation->forceFill([
            'message_count' => $conversation->message_count + 1,
            'last_message_at' => now('UTC'),
        ])->save();

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
