<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Carbon\CarbonImmutable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Domain\Enums\UserStatus;
use Shared\Concerns\HasUlid;
use Shared\Concerns\RecordsDomainEvents;

/**
 * حساب المستخدم — الجذر الفعلي للهوية في المنصة.
 *
 * organization_id عمود عادي بلا علاقة Eloquent: المؤسسة تملكها موديول آخر.
 * الحساب المحذوف (SoftDeletes) يبقى موجودًا في قاعدة البيانات دائمًا.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $email
 * @property string $password
 * @property string $username
 * @property string|null $phone
 * @property string|null $phone_country
 * @property string $locale
 * @property string $timezone
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $phone_verified_at
 * @property string|null $avatar_path
 * @property UserStatus $status
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlid;
    use Notifiable;
    use RecordsDomainEvents;
    use SoftDeletes;

    protected $table = 'users';

    protected static string $factory = UserFactory::class;

    /**
     * Keep nullable database defaults available on newly-created instances.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'avatar_path' => null,
        'email_verified_at' => null,
        'last_login_at' => null,
        'status' => UserStatus::Active->value,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'username',
        'phone',
        'phone_country',
        'password',
        'remember_token',
        'locale',
        'timezone',
        'email_verified_at',
        'phone_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'avatar_path',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'status' => UserStatus::class,
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
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

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function canLogIn(): bool
    {
        return $this->status->allowsLogin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== UserStatus::Active) {
            return false;
        }

        return $panel->getId() !== 'admin' || $this->can('admin.panel.access');
    }
}
