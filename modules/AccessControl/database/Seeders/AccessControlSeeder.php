<?php

declare(strict_types=1);

namespace Modules\AccessControl\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * مصفوفة الصلاحيات الكاملة كما في docs/06-permissions-matrix.md
 *
 * قاعدة حاكمة: لا يُفحص اسم دور في أي مكان بالكود. الدور مجرد حزمة صلاحيات
 * قابلة للتعديل من لوحة التحكم، والكود يسأل عن الصلاحية بالاسم فقط.
 *
 * النطاقات (own / assigned / children) لا تُخزَّن هنا — تفسّرها طبقة Policy
 * وقت التشغيل. البذر يمنح الصلاحية الأساسية فقط.
 */
final class AccessControlSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const PERMISSIONS = [
        'Students' => ['student.view', 'student.view.any', 'student.create', 'student.update'],
        'Guardians' => ['guardian.view', 'guardian.link'],
        'Staff' => ['staff.view', 'staff.view.any', 'staff.contract.view', 'staff.contract.update', 'staff.leave.approve'],
        'Enrollments' => [
            'enrollment.view', 'enrollment.create', 'enrollment.pause',
            'enrollment.freeze', 'enrollment.reactivate',
        ],
        'Academics' => ['program.manage', 'course.manage'],
        'Groups' => ['group.view', 'group.manage'],
        'Content' => ['content.view', 'content.manage'],
        'Scheduling' => [
            'schedule.view', 'schedule.manage',
            'session.postpone.request', 'session.postpone.approve',
        ],
        'Sessions' => [
            'session.view', 'session.create', 'session.cancel',
            'session.assign_substitute', 'session.join', 'session.finalize',
            'classroom.observe', 'classroom.moderate',
            'classroom.guest.invite', 'classroom.guest.revoke',
        ],
        'Attendance' => ['attendance.view', 'attendance.record', 'attendance.override'],
        'Recordings' => [
            'recording.view', 'recording.view.any', 'recording.grant',
            'recording.download', 'recording.delete',
        ],
        'Assignments' => ['assignment.manage', 'assignment.submit', 'assignment.grade'],
        'Assessments' => ['assessment.manage', 'assessment.take', 'grade.view'],
        'AcademicReports' => [
            'session_report.create', 'session_report.view', 'monthly_report.approve',
        ],
        'Certificates' => ['certificate.issue', 'badge.award'],
        'Messaging' => [
            'message.send', 'message.moderate', 'messaging.inbound.view',
            'announcement.publish', 'class_wall.post',
        ],
        'Payroll' => [
            'payroll.view', 'payroll.calculate', 'payroll.review',
            'payroll.adjustment.propose', 'payroll.adjustment.approve',
            'payroll.approve', 'payroll.pay', 'payroll.lock',
        ],
        'Reporting' => ['report.view', 'report.export'],
        'Audit' => ['audit.view'],
        'Organization' => ['settings.manage', 'system.alerts'],
        'Identity' => ['user.impersonate'],
    ];

    /**
     * الأدوار التسعة وصلاحيات كل واحد — منسوخة من مصفوفة docs/06.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        // المدير يرى كل شيء — تُملأ آليًا بكل الصلاحيات.
        'platform_admin' => ['*'],

        'academic_supervisor' => [
            'student.view', 'student.view.any', 'student.update', 'guardian.view',
            'staff.view', 'staff.view.any', 'staff.contract.view', 'staff.leave.approve',
            'enrollment.view', 'enrollment.create', 'enrollment.pause',
            'enrollment.freeze', 'enrollment.reactivate',
            'program.manage', 'course.manage', 'group.view', 'group.manage',
            'content.view', 'content.manage',
            'schedule.view', 'schedule.manage',
            'session.view', 'session.create', 'session.cancel',
            'session.postpone.request', 'session.postpone.approve',
            'session.assign_substitute', 'session.join', 'session.finalize',
            'classroom.observe', 'classroom.moderate',
            'classroom.guest.invite', 'classroom.guest.revoke',
            'attendance.view', 'attendance.record', 'attendance.override',
            'recording.view', 'recording.view.any', 'recording.grant', 'recording.download',
            'assignment.manage', 'assignment.grade',
            'assessment.manage', 'grade.view',
            'session_report.create', 'session_report.view', 'monthly_report.approve',
            'certificate.issue', 'badge.award',
            'message.send', 'message.moderate', 'messaging.inbound.view',
            'announcement.publish', 'class_wall.post',
            'report.view', 'report.export', 'system.alerts',
            // يقترح التسوية ولا يعتمدها — طلب صريح من العميل
            'payroll.adjustment.propose',
        ],

        'finance_supervisor' => [
            'student.view', 'student.view.any', 'staff.view', 'staff.view.any', 'staff.contract.view',
            'enrollment.view', 'group.view', 'session.view',
            'attendance.view', 'grade.view',
            'payroll.view', 'payroll.calculate', 'payroll.review',
            'payroll.adjustment.propose', 'payroll.adjustment.approve',
            'payroll.approve', 'payroll.pay', 'payroll.lock',
            'message.send', 'report.view', 'report.export',
        ],

        'registrar' => [
            'student.view', 'student.view.any', 'student.create', 'student.update',
            'staff.view.any',
            'guardian.view', 'guardian.link',
            'enrollment.view', 'enrollment.create', 'enrollment.pause',
            'group.view', 'group.manage', 'content.view',
            'schedule.view', 'schedule.manage',
            'session.view', 'session.create', 'session.cancel',
            'session.postpone.request', 'session.postpone.approve',
            'session.assign_substitute',
            'attendance.view', 'recording.view',
            'session_report.view', 'grade.view',
            'message.send', 'announcement.publish',
            'report.view', 'report.export',
        ],

        'communications_officer' => [
            'student.view', 'student.view.any', 'guardian.view', 'group.view', 'session.view',
            'attendance.view', 'enrollment.view',
            'message.send', 'message.moderate', 'messaging.inbound.view',
            'announcement.publish', 'report.view',
        ],

        'teacher' => [
            'student.view', 'guardian.view',
            'staff.view', 'staff.contract.view',
            'enrollment.view', 'group.view', 'content.view', 'content.manage',
            'schedule.view',
            'session.view', 'session.create', 'session.cancel',
            'session.postpone.request', 'session.postpone.approve',
            'session.join', 'session.finalize',
            'attendance.view', 'attendance.record',
            'recording.view', 'recording.download',
            'assignment.manage', 'assignment.grade',
            'assessment.manage', 'grade.view',
            'session_report.create', 'session_report.view',
            'certificate.issue', 'badge.award',
            'message.send', 'class_wall.post',
            'payroll.view', 'report.view',
        ],

        'student' => [
            'student.view', 'enrollment.view', 'group.view', 'content.view',
            'schedule.view', 'session.view', 'session.join',
            'session.postpone.request',
            'attendance.view', 'recording.view',
            'assignment.submit', 'assessment.take', 'grade.view',
            'session_report.view',
            'message.send', 'class_wall.post',
        ],

        'guardian' => [
            'student.view', 'guardian.view', 'enrollment.view', 'group.view',
            'content.view', 'schedule.view', 'session.view',
            'session.postpone.request',
            'attendance.view', 'recording.view', 'grade.view',
            'session_report.view', 'message.send',
        ],

        // مراجع: قراءة شاملة بلا أي تعديل
        'auditor' => [
            'student.view', 'student.view.any', 'guardian.view',
            'staff.view', 'staff.view.any', 'staff.contract.view',
            'enrollment.view', 'group.view', 'content.view',
            'program.manage', 'course.manage',
            'schedule.view', 'session.view', 'attendance.view',
            'recording.view', 'assignment.manage', 'assessment.manage',
            'grade.view', 'session_report.view',
            'payroll.view', 'report.view', 'report.export',
            'audit.view',
        ],
    ];

    public function run(): void
    {
        $organizationId = (string) DB::table('organizations')->orderBy('created_at')->value('id');

        if ($organizationId === '') {
            $this->command?->warn('AccessControlSeeder: لا توجد مؤسسة — شغّل OrganizationSeeder أولًا.');

            return;
        }

        $permissionIds = $this->seedPermissions();
        $this->seedRoles($organizationId, $permissionIds);

        $this->command?->info(sprintf(
            'الصلاحيات: %d · الأدوار: %d',
            count($permissionIds),
            count(self::ROLE_PERMISSIONS),
        ));
    }

    /**
     * @return array<string, string> اسم الصلاحية => المعرّف
     */
    private function seedPermissions(): array
    {
        $ids = [];
        $now = now();

        foreach (self::PERMISSIONS as $module => $names) {
            foreach ($names as $name) {
                $existing = DB::table('permissions')
                    ->where('name', $name)
                    ->where('guard_name', 'web')
                    ->value('id');

                if (is_string($existing) && $existing !== '') {
                    DB::table('permissions')->where('id', $existing)->update([
                        'module' => $module,
                        'updated_at' => $now,
                    ]);
                    $ids[$name] = $existing;

                    continue;
                }

                $id = (string) Str::ulid();
                DB::table('permissions')->insert([
                    'id' => $id,
                    'name' => $name,
                    'guard_name' => 'web',
                    'module' => $module,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $ids[$name] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, string> $permissionIds
     */
    private function seedRoles(string $organizationId, array $permissionIds): void
    {
        $now = now();
        $allNames = array_keys($permissionIds);

        foreach (self::ROLE_PERMISSIONS as $role => $granted) {
            $roleId = (string) DB::table('roles')
                ->where('organization_id', $organizationId)
                ->where('name', $role)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId === '') {
                $roleId = (string) Str::ulid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'organization_id' => $organizationId,
                    'name' => $role,
                    'guard_name' => 'web',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('roles')->where('id', $roleId)->update([
                    'is_system' => true,
                    'updated_at' => $now,
                ]);
            }

            $names = $granted === ['*'] ? $allNames : $granted;

            // مزامنة كاملة حتى لا تتراكم صلاحيات ملغاة عند إعادة التشغيل
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

            $rows = [];
            foreach ($names as $name) {
                if (!isset($permissionIds[$name])) {
                    $this->command?->warn("صلاحية غير معرّفة في المصفوفة: {$name}");

                    continue;
                }

                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionIds[$name]];
            }

            if ($rows !== []) {
                DB::table('role_has_permissions')->insert($rows);
            }
        }
    }
}
