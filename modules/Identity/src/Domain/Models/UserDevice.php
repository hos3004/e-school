<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Database\Factories\UserDeviceFactory;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * جهاز مسجّل لمستخدم (للإشعارات الفورية وتتبّع الجلسات).
 *
 * الجدول بلا updated_at ولا deleted_at — الإبطال عبر revoked_at.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $device_name
 * @property string|null $platform
 * @property string|null $push_token
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 */
final class UserDevice extends Model
{
    use HasTimestamps;
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'user_devices';

    protected static string $factory = UserDeviceFactory::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_name',
        'platform',
        'push_token',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** هل يصلح هذا الجهاز لاستقبال إشعارات فورية؟ */
    public function canReceivePush(): bool
    {
        return ! $this->isRevoked() && $this->push_token !== null;
    }
}
