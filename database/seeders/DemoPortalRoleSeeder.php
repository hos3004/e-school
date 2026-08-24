<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Models\User;

/**
 * يسند أدوار البوابات إلى حسابات العرض فقط بعد انتهاء بذور الموديولات.
 */
final class DemoPortalRoleSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoPortalRoleSeeder: تم التخطي خارج بيئة local/testing.');

            return;
        }

        $organizationId = DB::table('organizations')->orderBy('created_at')->value('id');

        if (!is_string($organizationId) || $organizationId === '') {
            $this->command?->warn('DemoPortalRoleSeeder: لا توجد مؤسسة.');

            return;
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['platform_admin', 'academic_supervisor', 'student', 'teacher', 'guardian'])
            ->orderByRaw('organization_id nulls first')
            ->get()
            ->unique('name')
            ->pluck('id', 'name');

        $missingRoles = array_values(array_diff(
            ['student', 'teacher', 'guardian'],
            $roleIds->keys()->all(),
        ));

        if ($missingRoles !== []) {
            $this->command?->warn('أدوار عرض مفقودة: '.implode(', ', $missingRoles));
        }

        $profilesByRole = [
            'student' => [
                'table' => 'student_profiles',
                'query' => DB::table('student_profiles')
                    ->where('student_profiles.organization_id', $organizationId)
                    ->whereNull('student_profiles.deleted_at'),
            ],
            'teacher' => [
                'table' => 'staff_profiles',
                'query' => DB::table('staff_profiles')
                    ->where('staff_profiles.organization_id', $organizationId)
                    ->whereNull('staff_profiles.deleted_at')
                    ->whereExists(function (Builder $contracts): void {
                        $contracts
                            ->selectRaw('1')
                            ->from('teacher_contracts')
                            ->whereColumn('teacher_contracts.staff_profile_id', 'staff_profiles.id');
                    }),
            ],
            'guardian' => [
                'table' => 'guardian_profiles',
                'query' => DB::table('guardian_profiles')
                    ->where('guardian_profiles.organization_id', $organizationId)
                    ->whereNull('guardian_profiles.deleted_at'),
            ],
        ];

        $assignments = [];

        foreach ($profilesByRole as $roleName => $profileSource) {
            $roleId = $roleIds->get($roleName);

            if (!is_string($roleId) || $roleId === '') {
                continue;
            }

            $userIds = $profileSource['query']
                ->join('users', 'users.id', '=', $profileSource['table'].'.user_id')
                ->where('users.email', 'like', '%@demo.%')
                ->whereNull('users.deleted_at')
                ->pluck('users.id');

            foreach ($userIds as $userId) {
                if (!is_string($userId) || $userId === '') {
                    continue;
                }

                $assignments[] = [
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ];
            }
        }

        // Accounts used to verify the administration panel locally must have
        // real seeded capabilities; no production-wide bypass is introduced.
        $adminRoleIds = $roleIds->only(['platform_admin', 'academic_supervisor']);
        $adminAccounts = [
            'admin@eschool.test' => 'platform_admin',
            'coordinator@eschool.test' => 'academic_supervisor',
        ];

        foreach ($adminAccounts as $email => $roleName) {
            $userId = DB::table('users')
                ->where('organization_id', $organizationId)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->value('id');
            $roleId = $adminRoleIds->get($roleName);

            if (is_string($userId) && is_string($roleId)) {
                $assignments[] = [
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ];
            }
        }

        $inserted = $assignments === []
            ? 0
            : DB::table('model_has_roles')->insertOrIgnore($assignments);

        $this->command?->info(sprintf(
            'أدوار حسابات البوابات التجريبية: %d معرّفة، %d جديدة',
            count($assignments),
            $inserted,
        ));
    }
}
