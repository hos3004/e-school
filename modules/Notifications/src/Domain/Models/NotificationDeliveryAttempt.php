<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * محاولة تسليم واحدة لرسالة في صندوق الإرسال — سجل تاريخي لا يُعدَّل.
 *
 * كل استدعاء لمزوّد الإرسال يولّد صفًا جديدًا برقم تسلسلي، والنتيجة
 * (نجاح/فشل) مع ردّ المزوّد الخام للتحقيق اللاحق.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $outbox_id
 * @property int $attempt_number
 * @property CarbonInterface $attempted_at
 * @property array<string, mixed>|null $provider_response
 * @property bool $succeeded
 * @property bool|null $retryable
 * @property string|null $error
 */
final class NotificationDeliveryAttempt extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'notification_delivery_attempts';

    protected $fillable = [
        'organization_id',
        'outbox_id',
        'attempt_number',
        'attempted_at',
        'provider_response',
        'succeeded',
        'retryable',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'int',
            'attempted_at' => 'immutable_datetime',
            'provider_response' => 'array',
            'succeeded' => 'bool',
            'retryable' => 'bool',
        ];
    }

    /**
     * المحاولات الناجحة فقط.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('succeeded', true);
    }

    /**
     * محاولات رسالة بعينها بترتيب زمني.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOutbox(Builder $query, string $outboxId): Builder
    {
        return $query->where('outbox_id', $outboxId)->orderBy('attempt_number');
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
