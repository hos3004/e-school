<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $student_profile_id
 * @property string $program_id
 * @property EnrollmentStatus $status
 * @property string|null $current_level_id
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property CarbonImmutable|null $frozen_at
 * @property string|null $frozen_reason
 * @property string|null $freeze_type
 * @property CarbonImmutable|null $expected_return_date
 */
final class Enrollment extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'enrollments';

    protected $fillable = [
        'organization_id',
        'student_profile_id',
        'program_id',
        'status',
        'applied_at',
        'activated_at',
        'completed_at',
        'withdrawn_at',
        'current_level_id',
        'frozen_at',
        'frozen_reason',
        'freeze_type',
        'expected_return_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'applied_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
            'frozen_at' => 'immutable_datetime',
            'expected_return_date' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<EnrollmentStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class);
    }

    /**
     * @param Builder<Enrollment> $query
     * @return Builder<Enrollment>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<Enrollment> $query
     * @return Builder<Enrollment>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }
}
