<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * رسالة في صندوق الإرسال — القيد المركزي لتسليم الإشعارات.
 *
 * الجدول append-only منطقيًا: لا يُعدَّل نص الرسالة بعد الإنشاء، وتتغير
 * حالة التسليم فقط عبر الإجراءات. مفتاح idempotency_key يمنع التكرار.
 *
 * معرّفات user_id و organization_id معرّفات خارجية عادية — النماذج
 * المملوكة لموديولات أخرى لا تُستورد هنا إطلاقًا.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $category
 * @property string $channel
 * @property string $locale
 * @property string $event_name
 * @property string $event_id
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $subject
 * @property array<string, mixed> $body
 * @property array<string, mixed> $payload
 * @property string $idempotency_key
 * @property CarbonInterface $scheduled_for
 * @property OutboxStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonInterface|null $sent_at
 */
final class NotificationOutbox extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'notification_outbox';

    protected $fillable = [
        'organization_id',
        'user_id',
        'category',
        'channel',
        'locale',
        'event_name',
        'event_id',
        'correlation_id',
        'subject',
        'body',
        'payload',
        'idempotency_key',
        'scheduled_for',
        'status',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'subject' => 'array',
            'body' => 'array',
            'payload' => 'array',
            'scheduled_for' => 'immutable_datetime',
            'attempts' => 'int',
            'sent_at' => 'immutable_datetime',
            'status' => OutboxStatus::class,
        ];
    }

    /**
     * رسائل بصدد الانتظار في مؤسسة معينة.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OutboxStatus::Pending);
    }

    /**
     * رسانات حان موعد إرسالها الآن — استعلام المجدول.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('status', OutboxStatus::Pending)
            ->where('scheduled_for', '<=', now());
    }

    /**
     * رسائل مستخدم بعينه — قائمة "إشعاراتي".
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
