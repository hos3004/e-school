<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Models;

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

    public function settings(): HasMany
    {
        return $this->hasMany(OrganizationSetting::class);
    }

    public function academicCalendars(): HasMany
    {
        return $this->hasMany(AcademicCalendar::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * مؤسسات مرتبة بالاسم داخل اللغة الحالية.
     */
    public function scopeOrderedByName(Builder $query): Builder
    {
        $locale = app()->getLocale();

        return $query->orderBy('name->'.$locale)->orderBy('slug');
    }

    /**
     * @return Collection<int, Organization>
     */
    public static function findBySlug(string $slug): ?self
    {
        return self::query()->where('slug', $slug)->first();
    }
}
