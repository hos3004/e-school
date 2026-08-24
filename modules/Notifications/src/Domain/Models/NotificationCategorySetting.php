<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasUlid;

/**
 * إعداد توجيه فئة إشعار لمؤسسة — override على الافتراضي في config.
 *
 * القيد الفريد (organization_id, category) يعني صفًا واحدًا لكل فئة لكل مؤسسة.
 * غياب الصف = اعتماد الافتراضي، فلا يُفرض على أي مؤسسة تخصيص مسبق.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $category
 * @property list<string> $channels
 * @property bool $is_critical
 * @property bool $respects_quiet_hours
 */
final class NotificationCategorySetting extends Model
{
    use HasUlid;

    protected $table = 'notification_category_settings';

    protected $fillable = [
        'organization_id',
        'category',
        'channels',
        'is_critical',
        'respects_quiet_hours',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_critical' => 'bool',
            'respects_quiet_hours' => 'bool',
        ];
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
