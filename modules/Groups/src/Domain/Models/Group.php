<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Groups\Domain\Enums\GroupStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property array<string, string> $name
 * @property int|null $capacity السعة مؤجَّلة ما دامت المجموعة قيد التخطيط
 * @property GroupStatus $status
 * @property string $timezone
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $active_members_count
 * @property int $occupied_seats_count المقاعد المشغولة (نشط + معلّق) — من withCount
 * @property-read Collection<int, GroupTeacher> $teachers
 */
final class Group extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'groups';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'capacity',
        'timezone',
        'status',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'capacity' => 'integer',
            'timezone' => 'string',
            'status' => GroupStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * السقف الفعلي لعدد المقاعد.
     *
     * المجموعة «قيد التخطيط» قد تُنشأ بلا سعة محددة بعد؛ في تلك الحالة يحكمها
     * السقف الأعلى المعلَن في `config/groups.php` — لا قيمة مخترعة ولا سعة
     * مفتوحة. تحديد السعة النهائية شرطٌ لتفعيل المجموعة.
     */
    public function effectiveCapacity(): int
    {
        return $this->capacity ?? (int) config('groups.capacity.maximum');
    }

    /**
     * @return HasMany<GroupMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /**
     * @return HasMany<GroupTeacher, $this>
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(GroupTeacher::class);
    }

    /**
     * @return HasMany<GroupProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(GroupProgram::class);
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
    public function scopeWithStatus(Builder $query, GroupStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
