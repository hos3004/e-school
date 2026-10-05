<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Tests\TestCase;

/**
 * كل صلاحية تسألها سياسة يجب أن تُنشئها البذرة ويملكها مدير المنصة.
 *
 * `platform_admin => ['*']` يمنح **ما تُنشئه البذرة** لا ما تسأله السياسات.
 * فما غاب عن مصفوفة البذرة لا يملكه أحد إطلاقًا — ولا حتى المدير — فتردّ
 * الصفحة 403 بلا أثر في أي سجل.
 *
 * وقع ذلك فعلًا على السيرفر: 98 صلاحية تسألها السياسات ولا تُنشئها البذرة،
 * فسقطت صفحات كاملة — الانضباط والشهادات والتكاملات والتقاويم والعطل — بـ403
 * بينما كل الاختبارات خضراء، لأن الاختبارات كانت تمنح الصلاحيات يدويًا.
 */
final class PermissionMatrixCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_permission_a_policy_checks_is_seeded_and_granted_to_the_platform_admin(): void
    {
        $this->seed(AccessControlSeeder::class);

        $needed = $this->permissionsReferencedByPolicies();

        $this->assertGreaterThan(150, count($needed), 'لم تُستخرج الصلاحيات من السياسات.');

        $granted = DB::table('permissions')
            ->join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('roles.name', 'platform_admin')
            ->pluck('permissions.name')
            ->all();

        $missing = array_values(array_diff($needed, $granted));
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "صلاحيات تسألها السياسات ولا يملكها platform_admin:\n".implode("\n", $missing),
        );
    }

    /**
     * وسائط `->can('...')` الحرفية في ملفات السياسات وحدها.
     *
     * قيدان ضروريان:
     *
     * 1. **التعليقات تُنزع أولًا.** توثيق `GroupPolicy` و`StudentProfilePolicy`
     *    يضرب المثل بـ`groups.action` و`student.action`، وليستا صلاحيتين.
     *
     * 2. **وسائط `can()` وحدها لا كل نص منقوط.** الالتقاط الواسع يجرف مفاتيح
     *    إعدادات مثل `payroll.adjustments.approve_permission` و
     *    `admission.self_registration.enabled` — وهي مفاتيح config تُرجع اسم
     *    صلاحية، لا أسماء صلاحيات بذاتها.
     *
     * @return list<string>
     */
    private function permissionsReferencedByPolicies(): array
    {
        $names = [];

        foreach (glob(base_path('modules/*/src/Application/Policies/*.php')) ?: [] as $file) {
            $stripped = '';

            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (is_array($token)) {
                    if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                        continue;
                    }

                    $stripped .= $token[1];

                    continue;
                }

                $stripped .= $token;
            }

            preg_match_all("/->can\(\s*'([a-z_]+(?:\.[a-z_]+)+)'/", $stripped, $matches);

            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
