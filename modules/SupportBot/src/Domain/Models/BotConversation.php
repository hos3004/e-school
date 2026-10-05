<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * جلسة محادثة واحدة بين مستخدم والبوت.
 *
 * «الجلسة» هنا نافذة زمنية لا اتصال شبكي: تُقفَل بعد خمول يحدده الإعداد، وما
 * قبل الإقفال لا يدخل سياق البوت بعده إطلاقًا. هذا ما يحقق طلب «الذاكرة تتصفر
 * بانتهاء الجلسة» دون أن يعني حذف الأرشيف.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $audience
 * @property string $locale
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $closed_at
 * @property int $message_count
 * @property int $blocked_count
 * @property-read Collection<int, BotMessage> $messages
 */
final class BotConversation extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'support_bot_conversations';

    protected $fillable = [
        'organization_id',
        'user_id',
        'audience',
        'locale',
        'started_at',
        'last_message_at',
        'closed_at',
        'message_count',
        'blocked_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'message_count' => 'int',
            'blocked_count' => 'int',
        ];
    }

    /**
     * @return HasMany<BotMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BotMessage::class, 'conversation_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
