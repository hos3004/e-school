<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasUlid;

/**
 * نموذج تسجيل عام مستقل؛ الـslug هو مصدر الطلب الثابت في الروابط والحملات.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $slug
 * @property array<string, string> $title
 * @property array<string, string>|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class RegistrationForm extends Model
{
    use HasUlid;
    use SoftDeletes;

    protected $table = 'registration_forms';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'slug',
        'title',
        'description',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<RegistrationQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(RegistrationQuestion::class)->orderBy('sort_order')->orderBy('created_at');
    }

    /** @return HasMany<RegistrationApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(RegistrationApplication::class);
    }

    public function localizedTitle(): string
    {
        return $this->localized($this->title);
    }

    public function localizedDescription(): string
    {
        return $this->localized($this->description ?? []);
    }

    /**
     * @param Builder<RegistrationForm> $query
     * @return Builder<RegistrationForm>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<RegistrationForm> $query
     * @return Builder<RegistrationForm>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param array<string, string> $values */
    private function localized(array $values): string
    {
        $locale = app()->getLocale();

        return $values[$locale] ?? $values['ar'] ?? $values['en'] ?? '';
    }
}
