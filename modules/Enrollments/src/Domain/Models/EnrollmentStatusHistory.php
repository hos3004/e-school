<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $enrollment_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string $reason
 * @property string $changed_by
 * @property CarbonImmutable|null $changed_at
 */
final class EnrollmentStatusHistory extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'enrollment_status_history';

    protected $fillable = [
        'enrollment_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
        'changed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
