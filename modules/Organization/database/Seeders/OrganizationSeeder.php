<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Domain\Models\OrganizationSetting;

/**
 * بيانات تجريبية معقولة لموديول Organization — مؤسسة واحدة بتقويم نشط
 * وعطلتها وإعداداتها.
 */
final class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'demo-school'],
            [
                'name' => [
                    'ar' => 'مدرسة النموذج التجريبية',
                    'en' => 'Demo Model School',
                ],
                'default_timezone' => 'Africa/Cairo',
                'default_currency' => 'EGP',
                'default_locale' => 'ar',
                'supported_locales' => ['ar', 'en'],
                'week_starts_on' => 'saturday',
            ],
        );

        $year = (int) now('UTC')->format('Y');

        $calendar = AcademicCalendar::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'starts_on' => CarbonImmutable::create($year, 9, 1, 0, 0, 0, 'UTC'),
            ],
            [
                'name' => ['ar' => 'العام الدراسي '.$year, 'en' => 'School Year '.$year],
                'ends_on' => CarbonImmutable::create($year + 1, 5, 31, 0, 0, 0, 'UTC'),
                'is_active' => true,
            ],
        );

        AcademicCalendar::query()
            ->whereKeyNot($calendar->getKey())
            ->where('organization_id', $organization->id)
            ->update(['is_active' => false]);

        Holiday::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'starts_on' => CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, 'UTC'),
            ],
            [
                'academic_calendar_id' => $calendar->id,
                'name' => ['ar' => 'رأس السنة الميلادية', 'en' => "New Year's Day"],
                'ends_on' => CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, 'UTC'),
                'source' => 'manual',
                'blocks_scheduling' => true,
            ],
        );

        Holiday::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'starts_on' => CarbonImmutable::create($year + 1, 4, 25, 0, 0, 0, 'UTC'),
            ],
            [
                'academic_calendar_id' => $calendar->id,
                'name' => ['ar' => 'عيد تحرير سيناء', 'en' => 'Sinai Liberation Day'],
                'ends_on' => CarbonImmutable::create($year + 1, 4, 25, 0, 0, 0, 'UTC'),
                'source' => 'manual',
                'blocks_scheduling' => true,
            ],
        );

        OrganizationSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'key' => 'attendance.grace_minutes'],
            ['value' => 10],
        );
    }
}
