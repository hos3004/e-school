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
        'Staff' => [
            'staff.view', 'staff.view.any', 'staff.contract.view', 'staff.contract.update',
            'staff.availability.create', 'staff.availability.approve', 'staff.leave.approve',
        ],
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
            'session_report.create', 'session_report.view', 'monthly_report.create', 'monthly_report.approve',
        ],
        'Certificates' => [
            'certificates.badge.view_any',
            'certificates.badge.view',
            'certificates.badge.create',
            'certificates.badge.update',
            'certificates.badge.delete',
            'certificates.award.view_any',
            'certificates.award.view',
            'certificates.award.create',
            'certificates.certificate.view_any',
            'certificates.certificate.view',
            'certificates.certificate.create',
            'certificates.certificate.revoke',
            'certificates.template.view_any',
            'certificates.template.view',
            'certificates.template.create',
            'certificates.template.update',
            'certificates.template.delete',
            'certificate.issue', 'badge.award'],
        'Messaging' => [
            'messaging.class_wall_comment.view_any',
            'messaging.class_wall_comment.view',
            'messaging.class_wall_comment.create',
            'messaging.class_wall_comment.update',
            'messaging.class_wall_comment.delete',
            'messaging.whatsapp_inbound.view_any',
            'messaging.whatsapp_inbound.view',
            'messaging.whatsapp_inbound.create',
            'messaging.whatsapp_inbound.update',
            'messaging.whatsapp_inbound.delete',
            'messaging.whatsapp_inbound.handle',

            'message.send', 'message.moderate', 'messaging.inbound.view',
            'announcement.publish', 'class_wall.post',
        ],
        'Notifications' => [
            'notifications.outbox.update',
            'notifications.outbox.cancel',
            'notifications.outbox.delete',
            'notifications.attempt.view_any',
            'notifications.attempt.view',
            'notifications.preference.view_any',
            'notifications.preference.view',
            'notifications.preference.create',
            'notifications.preference.update',
            'notifications.preference.delete',
            'notifications.outbox.create'],
        'Payroll' => [
            'payroll.view', 'payroll.calculate', 'payroll.review',
            'payroll.adjustment.propose', 'payroll.adjustment.approve',
            'payroll.approve', 'payroll.pay', 'payroll.lock',
        ],
        'Discipline' => [
            'discipline.view_any', 'discipline.record_violations',
            'discipline.waive_violations', 'discipline.apply_actions',
            'discipline.request_reactivation',
        ],
        'Integrations' => [
            'integrations.provider.view_any', 'integrations.provider.view',
            'integrations.provider.create', 'integrations.provider.update',
            'integrations.provider.delete',
            'integrations.connection.view_any', 'integrations.connection.view',
            'integrations.connection.create', 'integrations.connection.update',
            'integrations.connection.delete', 'integrations.connection.activate',
            'integrations.connection.disable',
            'integrations.delivery.view_any', 'integrations.delivery.view',
            'integrations.delivery.create', 'integrations.delivery.delete',
            'integrations.delivery.requeue',
        ],
        'Reporting' => [
            'reporting.event_log.view_any',
            'reporting.event_log.view',
            'reporting.event_log.delete',
            'reporting.snapshot.view_any',
            'reporting.snapshot.view',
            'reporting.snapshot.build',
            'reporting.snapshot.delete',
            'reporting.student.view',
            'reporting.student.correct',
            'reporting.student.delete',
            'reporting.teacher.view',
            'reporting.teacher.correct',
            'reporting.teacher.delete',
            'report.view', 'report.export'],
        'Audit' => [
            'audit.view_any',
            'audit.export',
            'audit.prune',
            'audit.record',
            'audit.view'],
        'Popups' => [
            'popup_campaign.view_any', 'popup_campaign.view', 'popup_campaign.view_analytics',
            'popup_campaign.create', 'popup_campaign.update',
            'popup_campaign.publish', 'popup_campaign.pause', 'popup_campaign.archive',
        ],
        'Organization' => [
            'organizations.view_any',
            'organizations.view',
            'organizations.create',
            'organizations.update',
            'organizations.delete',
            'organizations.manage_settings',
            'academic_calendars.view_any',
            'academic_calendars.view',
            'academic_calendars.create',
            'academic_calendars.update',
            'academic_calendars.delete',
            'academic_calendars.activate',
            'academic_calendars.close',
            'holidays.view_any',
            'holidays.view',
            'holidays.create',
            'holidays.update',
            'holidays.delete',
            'settings.manage', 'system.alerts'],
        'Identity' => [
            'identity.devices.view_any',
            'identity.devices.view',
            'identity.devices.revoke',

            'admin.panel.access',
            'identity.users.view_any', 'identity.users.view',
            'identity.users.create', 'identity.users.update',
            'identity.users.delete', 'identity.users.change_status',
            'user.impersonate',
        ],
        'AccessControl' => [
            'accesscontrol.roles.view_any', 'accesscontrol.roles.view',
            'accesscontrol.roles.create', 'accesscontrol.roles.update',
            'accesscontrol.roles.delete', 'accesscontrol.roles.sync_permissions',
            'accesscontrol.permissions.view_any', 'accesscontrol.permissions.view',
            'accesscontrol.permissions.grant_direct',
            'accesscontrol.assignments.assign_role', 'accesscontrol.assignments.revoke_role',
        ],
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
            'admin.panel.access',
            'identity.users.view_any', 'identity.users.view', 'identity.users.update',
            'identity.users.change_status',
            'accesscontrol.roles.view_any', 'accesscontrol.roles.view',
            'accesscontrol.permissions.view_any', 'accesscontrol.permissions.view',
            'student.view', 'student.view.any', 'student.update', 'guardian.view',
            'staff.view', 'staff.view.any', 'staff.contract.view',
            'staff.availability.approve', 'staff.leave.approve',
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
            'session_report.create', 'session_report.view', 'monthly_report.create', 'monthly_report.approve',
            'certificate.issue', 'badge.award',
            'message.send', 'message.moderate', 'messaging.inbound.view',
            'announcement.publish', 'class_wall.post',
            'report.view', 'report.export', 'system.alerts',
            // يقترح التسوية ولا يعتمدها — طلب صريح من العميل
            'payroll.adjustment.propose',
        ],

        'finance_supervisor' => [
            'admin.panel.access',
            'student.view', 'student.view.any', 'staff.view', 'staff.view.any', 'staff.contract.view',
            'enrollment.view', 'group.view', 'session.view',
            'attendance.view', 'grade.view',
            'payroll.view', 'payroll.calculate', 'payroll.review',
            'payroll.adjustment.propose', 'payroll.adjustment.approve',
            'payroll.approve', 'payroll.pay', 'payroll.lock',
            'message.send', 'report.view', 'report.export',
        ],

        'registrar' => [
            'admin.panel.access',
            'identity.users.view_any', 'identity.users.view', 'identity.users.create',
            'identity.users.update', 'identity.users.change_status',
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
            'admin.panel.access',
            'identity.users.view_any', 'identity.users.view',
            'student.view', 'student.view.any', 'guardian.view', 'group.view', 'session.view',
            'attendance.view', 'enrollment.view',
            'message.send', 'message.moderate', 'messaging.inbound.view',
            'announcement.publish', 'report.view',
            'notifications.outbox.create',
            'popup_campaign.view_any', 'popup_campaign.view', 'popup_campaign.view_analytics',
            'popup_campaign.create', 'popup_campaign.update',
            'popup_campaign.publish', 'popup_campaign.pause', 'popup_campaign.archive',
        ],

        'teacher' => [
            'student.view', 'guardian.view',
            'staff.view', 'staff.contract.view',
            'staff.availability.create',
            'enrollment.view', 'group.view', 'content.view', 'content.manage',
            'schedule.view',
            'session.view', 'session.create', 'session.cancel',
            'session.postpone.request', 'session.postpone.approve',
            'session.join', 'session.finalize',
            'attendance.view', 'attendance.record',
            'recording.view',
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
            'admin.panel.access',
            'identity.users.view_any', 'identity.users.view',
            'accesscontrol.roles.view_any', 'accesscontrol.roles.view',
            'accesscontrol.permissions.view_any', 'accesscontrol.permissions.view',
            'student.view', 'student.view.any', 'guardian.view',
            'staff.view', 'staff.view.any', 'staff.contract.view',
            'enrollment.view', 'group.view', 'content.view',
            'schedule.view', 'session.view', 'attendance.view',
            'recording.view',
            'grade.view', 'session_report.view',
            'payroll.view', 'report.view', 'report.export',
            'audit.view',
        ],
    ];

    /**
     * المصدر القابل للاختبار لأسماء الصلاحيات، مع إبقاء تصنيف الموديولات
     * داخل البذرة نفسها. لا نستخدم array_unique هنا حتى يكشف الاختبار أي
     * اسم مكرر بدل أن تخفيه عملية التطبيع.
     *
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $names) {
            array_push($permissions, ...$names);
        }

        return $permissions;
    }

    public function run(): void
    {
        $permissionIds = $this->seedPermissions();
        $this->seedRoles($permissionIds);

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
    private function seedRoles(array $permissionIds): void
    {
        $now = now();
        $allNames = array_keys($permissionIds);

        foreach (self::ROLE_PERMISSIONS as $role => $granted) {
            $roleId = (string) DB::table('roles')
                ->whereNull('organization_id')
                ->where('name', $role)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId === '') {
                $roleId = (string) Str::ulid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'organization_id' => null,
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
