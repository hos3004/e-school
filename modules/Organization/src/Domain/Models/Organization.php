<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Organization\Domain\Enums\Weekday;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * المؤسسة (المدرسة) — جذر موديول Organization.
 *
 * كل كيان آخر في المنصة يحمل organization_id، وهنا تُضبط الإعدادات
 * الافتراضية: التوقيت، والعملة، واللغة، وأول يوم في الأسبوع.
 *
 * @property string $id
 * @property array<string, string> $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string $default_timezone
 * @property string $default_currency
 * @property string $default_locale
 * @property list<string>|null $supported_locales
 * @property string $week_starts_on
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $feature_overrides
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, AcademicCalendar> $academicCalendars
 * @property-read Collection<int, Holiday> $holidays
 */
final class Organization extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'organizations';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'default_timezone',
        'default_currency',
        'default_locale',
        'supported_locales',
        'week_starts_on',
        'settings',
        'feature_overrides',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'supported_locales' => 'array',
            'settings' => 'array',
            'feature_overrides' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function weekStartsOn(): Weekday
    {
        return Weekday::from($this->week_starts_on);
    }

    /** @return HasMany<OrganizationSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(OrganizationSetting::class);
    }

    /** @return HasMany<AcademicCalendar, $this> */
    public function academicCalendars(): HasMany
    {
        return $this->hasMany(AcademicCalendar::class);
    }

    /** @return HasMany<Holiday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * مؤسسات مرتبة بالاسم داخل اللغة الحالية.
     */
    /**
     * @param Builder<Organization> $query
     * @return Builder<Organization>
     */
    public function scopeOrderedByName(Builder $query): Builder
    {
        $locale = app()->getLocale();

        return $query->orderBy('name->'.$locale)->orderBy('slug');
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::query()->where('slug', $slug)->first();
    }
}
