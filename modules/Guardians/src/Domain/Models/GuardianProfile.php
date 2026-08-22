<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithOccupation(Builder $query, string $occupation): Builder
    {
        return $query->where('occupation', $occupation);
    }
}
