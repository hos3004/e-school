<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SupportBot\Domain\Enums\MessageRole;
use Modules\SupportBot\Domain\Enums\TopicMode;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * رسالة واحدة داخل محادثة — من المستخدم أو من البوت.
 *
 * لا updated_at: الرسالة لا تُعدَّل بعد كتابتها. مراجعة شكوى بعد أسبوع تحتاج
 * ما قيل فعلًا، لا نسخة محرَّرة منه.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $organization_id
 * @property MessageRole $role
 * @property string $body
 * @property string|null $topic
 * @property TopicMode|null $mode
 * @property bool $was_generated
 * @property string|null $model
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int|null $latency_milliseconds
 * @property string|null $failure_reason
 * @property string|null $correlation_id
 * @property CarbonImmutable $created_at
 */
final class BotMessage extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'support_bot_messages';

    protected $fillable = [
        'conversation_id',
        'organization_id',
        'role',
        'body',
        'topic',
        'mode',
        'was_generated',
        'model',
        'input_tokens',
        'output_tokens',
        'latency_milliseconds',
        'failure_reason',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'mode' => TopicMode::class,
            'was_generated' => 'bool',
            'input_tokens' => 'int',
            'output_tokens' => 'int',
            'latency_milliseconds' => 'int',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<BotConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BotConversation::class, 'conversation_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
