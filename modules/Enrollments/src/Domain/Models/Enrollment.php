<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }
}
