<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * يمنح صلاحيتَي عرض تعليقات حائط الصف للمعلم والطالب.
 *
 * الصلاحيتان كانتا محصورتين على platform_admin منذ إنشاء الموديول، فلا شاشة
 * تعرض تعليقات الحائط لمعلم أو طالب رغم أن إنشاء التعليق نفسه (StoreWallCommentRequest)
 * يتحقق من message.send العامة لا من هاتين الصلاحيتين، وهما ممنوحتان له فعلًا.
 * الفجوة الحقيقية إذن في القراءة، لا الكتابة — قبل هذه الهجرة كان أي مستخدم
 * غير platform_admin يقدر يعلّق دون أن يقدر يرى تعليقات غيره أبدًا.
 *
 * لا نعتمد على AccessControlSeeder في النشر — انظر تعليق الهجرة المشابهة
 * 2026_09_10_160100_grant_schedule_change_permissions.php.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const GRANTS = [
        'messaging.class_wall_comment.view_any' => ['teacher', 'student'],
        'messaging.class_wall_comment.view' => ['teacher', 'student'],
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
                    'module' => 'Messaging',
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

        $roleIds = DB::table('roles')
            ->whereNull('organization_id')
            ->whereIn('name', ['teacher', 'student'])
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
