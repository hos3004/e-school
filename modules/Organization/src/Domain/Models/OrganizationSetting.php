<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * إعداد على مستوى المؤسسة — مفتاح/قيمة (jsonb).
 *
 * الإعدادات التشغيلية الخاصة بمؤسسة بعينها تُخزَّن هنا بدلًا من
 * تثبيتها في الكود؛ القيمة نفسها jsonb فتستوعب أي بنية.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $key
 * @property array<array-key, mixed>|null $value
 * @property string|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 */
final class OrganizationSetting extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'organization_settings';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'key',
        'value',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * إعدادات مؤسسة واحدة فقط.
     */
    /**
     * @param Builder<OrganizationSetting> $query
     * @return Builder<OrganizationSetting>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * البحث بمفتاح محدد.
     */
    /**
     * @param Builder<OrganizationSetting> $query
     * @return Builder<OrganizationSetting>
     */
    public function scopeWithKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
