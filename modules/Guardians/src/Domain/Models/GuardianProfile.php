<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * الملف التعريفي للوصي — امتداد لحساب المستخدم بصفته ولي أمر.
 *
 * user_id معرّف خارجي يبقى عمودًا عاديًا؛ لا علاقة Eloquent لموديول آخر.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string|null $national_id_last4
 * @property string|null $occupation
 * @property ContactChannel|null $preferred_contact_channel
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, GuardianLink> $links
 */
final class GuardianProfile extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'guardian_profiles';

    protected $fillable = [
        'organization_id',
        'user_id',
        'national_id_last4',
        'occupation',
        'preferred_contact_channel',
    ];

    protected function casts(): array
    {
        return [
            'preferred_contact_channel' => ContactChannel::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<GuardianLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(GuardianLink::class, 'guardian_profile_id');
    }

    /**
     * @param Builder<GuardianProfile> $query
     * @return Builder<GuardianProfile>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<GuardianProfile> $query
     * @return Builder<GuardianProfile>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param Builder<GuardianProfile> $query
     * @return Builder<GuardianProfile>
     */
    public function scopeWithOccupation(Builder $query, string $occupation): Builder
    {
        return $query->where('occupation', $occupation);
    }
}
