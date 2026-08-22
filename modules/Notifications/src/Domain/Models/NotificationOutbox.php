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
 * @property bool|null $last_error_retryable
 * @property CarbonInterface|null $sent_at
 * @property string|null $external_message_id
 * @property string|null $provider_status
 * @property string|null $failure_reason
 * @property CarbonInterface|null $read_at
 * @property string|null $last_manual_retry_by
 * @property CarbonInterface|null $last_manual_retry_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
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
        'last_error_retryable',
        'sent_at',
        'external_message_id',
        'provider_status',
        'failure_reason',
        'read_at',
        'last_manual_retry_by',
        'last_manual_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'subject' => 'array',
            'body' => 'array',
            'payload' => 'array',
            'scheduled_for' => 'immutable_datetime',
            'attempts' => 'int',
            'last_error_retryable' => 'bool',
            'sent_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'last_manual_retry_at' => 'immutable_datetime',
            'status' => OutboxStatus::class,
        ];
    }

    /**
     * رسائل بصدد الانتظار في مؤسسة معينة.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', OutboxStatus::Queued);
    }

    /**
     * رسانات حان موعد إرسالها الآن — استعلام المجدول.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('status', OutboxStatus::Queued)
            ->where('scheduled_for', '<=', now());
    }

    /**
     * رسائل مستخدم بعينه — قائمة "إشعاراتي".
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
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
