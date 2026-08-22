<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Models;

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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * إعدادات مؤسسة واحدة فقط.
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * البحث بمفتاح محدد.
     */
    public function scopeWithKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
