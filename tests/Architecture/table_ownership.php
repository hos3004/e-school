<?php

declare(strict_types=1);

/**
 * خريطة ملكية الجداول — مصدرها الحرفي: docs/08-module-boundaries.md قسم 4.
 *
 * الشكل: array<string table, string module> — جدول واحد له مالك واحد بالضبط.
 * المفاتيح التي تحوي * أنماط (wildcards) كما وردت في الوثيقة:
 *   - *_has_*    → جداول الربط الخاصة بالأدوار والصلاحيات
 *   - class_wall_* → حائط الصف
 *   - report_*     → Read Models الخاصة بـ Reporting
 *
 * أي جدول جديد يجب أن يُسجَّل هنا أولاً، وإلا ستفشل اختبارات المعمارية.
 */
return [
    // Organization
    'organizations' => 'Organization',
    'organization_settings' => 'Organization',
    'academic_calendars' => 'Organization',
    'holidays' => 'Organization',
    'countries' => 'Organization',
    'regions' => 'Organization',

    // Identity
    'users' => 'Identity',
    'user_devices' => 'Identity',
    'password_reset_tokens' => 'Identity',
    'phone_password_reset_tokens' => 'Identity',

    // AccessControl
    'roles' => 'AccessControl',
    'permissions' => 'AccessControl',
    '*_has_*' => 'AccessControl',

    // Audit
    'audit_log' => 'Audit',

    // Integrations
    'integration_providers' => 'Integrations',
    'integration_connections' => 'Integrations',
    'integration_webhook_deliveries' => 'Integrations',

    // Students
    'student_profiles' => 'Students',
    'registration_applications' => 'Students',

    // Guardians
    'guardian_profiles' => 'Guardians',
    'guardian_links' => 'Guardians',

    // Staff
    'staff_profiles' => 'Staff',
    'teacher_contracts' => 'Staff',
    'teacher_rates' => 'Staff',
    'teacher_availability' => 'Staff',
    'teacher_leaves' => 'Staff',
    'teacher_courses' => 'Staff',

    // Academics
    'programs' => 'Academics',
    'levels' => 'Academics',
    'courses' => 'Academics',
    'program_categories' => 'Academics',
    'course_category' => 'Academics',
    'program_eligibility' => 'Academics',

    // Groups
    'groups' => 'Groups',
    'group_programs' => 'Groups',
    'group_teachers' => 'Groups',
    'group_memberships' => 'Groups',

    // Enrollments
    'enrollments' => 'Enrollments',
    'enrollment_status_history' => 'Enrollments',

    // Content
    'course_materials' => 'Content',

    // Scheduling
    'schedules' => 'Scheduling',
    'postponement_requests' => 'Scheduling',

    // Sessions
    'sessions' => 'Sessions',
    'session_substitutions' => 'Sessions',
    'teacher_apologies' => 'Sessions',
    'session_status_history' => 'Sessions',
    'session_participants' => 'Sessions',

    // Attendance
    'attendances' => 'Attendance',

    // VirtualClassroom
    'classrooms' => 'VirtualClassroom',
    'classroom_events' => 'VirtualClassroom',

    // Recordings
    'recordings' => 'Recordings',
    'recording_views' => 'Recordings',
    'recording_access_grants' => 'Recordings',

    // Assignments
    'assignments' => 'Assignments',
    'assignment_submissions' => 'Assignments',

    // Assessments
    'assessments' => 'Assessments',
    'questions' => 'Assessments',
    'assessment_attempts' => 'Assessments',

    // AcademicReports
    'session_reports' => 'AcademicReports',
    'session_report_students' => 'AcademicReports',
    'monthly_reports' => 'AcademicReports',

    // Certificates
    'certificate_templates' => 'Certificates',
    'certificates' => 'Certificates',
    'badges' => 'Certificates',
    'badge_awards' => 'Certificates',

    // Discipline
    'violation_events' => 'Discipline',
    'discipline_actions' => 'Discipline',
    'reactivation_requests' => 'Discipline',

    // Messaging
    'conversations' => 'Messaging',
    'conversation_participants' => 'Messaging',
    'messages' => 'Messaging',
    'class_wall_*' => 'Messaging',
    'whatsapp_inbound' => 'Messaging',

    // Notifications
    'notification_outbox' => 'Notifications',
    'notification_delivery_attempts' => 'Notifications',
    'notification_preferences' => 'Notifications',
    'notification_templates' => 'Notifications',

    // Payroll
    'payroll_periods' => 'Payroll',
    'payroll_entries' => 'Payroll',
    'payroll_adjustments' => 'Payroll',
    'staff_obligations' => 'Payroll',

    // Billing
    'invoices' => 'Billing',
    'payments' => 'Billing',
    'student_packages' => 'Billing',
    'coupons' => 'Billing',
    'refunds' => 'Billing',

    // Reporting — Read Models فقط
    'report_*' => 'Reporting',
];
