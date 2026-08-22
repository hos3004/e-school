<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Staff\Domain\Enums\EmploymentType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class StaffProfile extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'staff_profiles';

    protected $fillable = [
        'organization_id',
        'user_id',
        'staff_code',
        'employment_type',
        'hired_at',
        'terminated_at',
        'bio',
        'specializations',
    ];

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'hired_at' => 'immutable_datetime',
            'terminated_at' => 'immutable_datetime',
            'bio' => 'array',
            'specializations' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->terminated_at === null;
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
     * الموظفون غير المنتهية خدماتهم.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('terminated_at');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
