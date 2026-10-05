<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasUlid;

/**
 * مرحلة تذكير أُرسلت لحصة — سجل تشغيلي لا بيانات بشرية فيه.
 *
 * وجود الصف هو الضمان الوحيد لعدم تكرار المرحلة نفسها للحصة نفسها.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $session_id
 * @property string $stage
 * @property CarbonImmutable $dispatched_at
 * @property int $recipient_count
 */
final class SessionReminderDispatch extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $table = 'session_reminder_dispatches';

    protected $fillable = [
        'organization_id',
        'session_id',
        'stage',
        'dispatched_at',
        'recipient_count',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'immutable_datetime',
            'recipient_count' => 'int',
        ];
    }
}
