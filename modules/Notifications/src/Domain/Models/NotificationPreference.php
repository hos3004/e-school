<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * تفضيل إشعارات لمستخدم: فئة × قناة × مفعّل/معطّل.
 *
 * القيد الفريد (user_id, category, channel) يعني أن التفضيل upsert دائمًا:
 * لا صفوف مكرّرة لنفس الثلاثية. غياب الصف يعني "مفعّل افتراضيًا".
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $category
 * @property string $channel
 * @property bool $enabled
 * @property CarbonInterface|null $updated_at
 */
final class NotificationPreference extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'organization_id',
        'user_id',
        'category',
        'channel',
        'enabled',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * التفضيلات المفعّلة فقط — قائمة من يُرسل إليه.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * تفضيلات مستخدم بعينه.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * تفضيلات فئة وقناة محددتين.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForCategoryChannel(Builder $query, string $category, string $channel): Builder
    {
        return $query->where('category', $category)->where('channel', $channel);
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
