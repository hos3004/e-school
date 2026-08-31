<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;

/**
 * بيانات تجريبية معقولة لموديول Identity.
 *
 * المؤسسة تملكها طبقة أعلى (Organization) — إن وُجدت مؤسسة جاهزة
 * نُسجّل المستخدمين تحتها، وإلا نتخطى البذر بهدوء حتى لا يكسر الـ FK.
 */
final class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('IdentitySeeder: demo identities are disabled outside local/testing.');

            return;
        }

        if (!Schema::hasTable('organizations') || !Schema::hasTable('users')) {
            return;
        }

        /** @var string|null $organizationId */
        $organizationId = DB::table('organizations')->value('id');

        if ($organizationId === null) {
            return;
        }

        $users = [
            ['name' => 'مدير النظام', 'email' => 'admin@eschool.test', 'status' => UserStatus::Active],
            ['name' => 'منسق الجدولة', 'email' => 'coordinator@eschool.test', 'status' => UserStatus::Active],
            ['name' => 'ولي أمر — أحمد سمير', 'email' => 'guardian@eschool.test', 'status' => UserStatus::Active],
            ['name' => 'حساب موقوف للتجربة', 'email' => 'suspended@eschool.test', 'status' => UserStatus::Suspended],
            ['name' => 'حساب مجمّد للتجربة', 'email' => 'frozen@eschool.test', 'status' => UserStatus::Frozen],
        ];

        foreach ($users as $data) {
            User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'organization_id' => $organizationId,
                    'name' => $data['name'],
                    'username' => Str::before($data['email'], '@'),
                    'password' => 'password',
                    'locale' => 'ar',
                    'timezone' => 'Africa/Cairo',
                    'email_verified_at' => now()->utc(),
                    'status' => $data['status'],
                ],
            );
        }

        // مستخدمون عشوائيون إضافيون لامتلاء جدول Filament.
        if (User::query()->forOrganization($organizationId)->count() < 12) {
            UserFactory::new()
                ->inOrganization($organizationId)
                ->count(5)
                ->create();
        }
    }
}
