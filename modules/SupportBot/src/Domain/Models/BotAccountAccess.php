<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * استثناء صريح على ما يمنحه الدور: فتح البوت لحساب أو إغلاقه عليه.
 *
 * غياب الصف ليس «ممنوع» بل «اتبع الافتراضي». هذا يجعل الجدول صغيرًا مهما كبر
 * عدد المستخدمين: لا صف إلا لمن خرج عن القاعدة.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property bool $enabled
 * @property string $reason
 * @property string|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class BotAccountAccess extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'support_bot_account_access';

    protected $fillable = [
        'organization_id',
        'user_id',
        'enabled',
        'reason',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
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
