<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class EnrollmentStatusHistory extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

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
