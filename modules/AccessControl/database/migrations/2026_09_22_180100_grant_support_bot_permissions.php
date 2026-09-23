<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * يمنح صلاحيتَي بوت الدعم لمدير المنصة.
 *
 * لا نعتمد على AccessControlSeeder في النشر: بذرته تزامن صلاحيات كل دور نظامي
 * مزامنة كاملة (حذف ثم إدراج)، فتمحو أي تخصيص أجرته الإدارة من اللوحة. هذه
 * الهجرة تضيف الناقص فقط وتترك ما عداه كما هو — على نمط هجرة صلاحيات تغيير
 * الموعد الدائم.
 *
 * المدير وحده مبدئيًّا: قراءة الأرشيف تكشف ما كتبه الناس للبوت، وإدارته تغيّر ما
 * يقوله لهم. منحهما لغيره قرار إداري يُتخذ من شاشة الأدوار.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const GRANTS = [
        'support_bot.manage' => ['platform_admin'],
        'support_bot.archive.view' => ['platform_admin'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GRANTS as $permission => $roles) {
            $permissionId = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', 'web')
                ->value('id');

            if (!is_string($permissionId) || $permissionId === '') {
                $permissionId = (string) Str::ulid();
                DB::table('permissions')->insert([
                    'id' => $permissionId,
                    'name' => $permission,
                    'guard_name' => 'web',
                    'module' => 'SupportBot',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($roles as $role) {
                $roleId = DB::table('roles')
                    ->whereNull('organization_id')
                    ->where('name', $role)
                    ->where('guard_name', 'web')
                    ->value('id');

                if (!is_string($roleId) || $roleId === '') {
                    continue;
                }

                $linked = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (!$linked) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::GRANTS))
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
