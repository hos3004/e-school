<?php

declare(strict_types=1);

use Modules\Integrations\Infrastructure\Gateways\WhatsAppCloudGateway;
use Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway;
use Modules\Notifications\Infrastructure\Gateways\MailChannelGateway;

/**
 * محرّك الإشعارات.
 *
 * القاعدة: لا يستدعي أي موديول قناة إرسال مباشرة. الموديول ينشر Domain Event،
 * والمحرّك يقرر: من يُخطَر، وبأي قناة، وبأي لغة، ومتى، وماذا يحدث عند الفشل.
 *
 * كل رسالة تمر بـ Outbox: تُسجَّل أولًا ثم تُرسل. لو وقعت قناة، لا نفقد الإشعار.
 * انظر docs/12-notification-architecture.md
 */
return [

    /*
     * القنوات المتاحة.
     */
    'channels' => [
        'in_app' => [
            'enabled' => true,
            // الواجهة الحالية تستخدم polling؛ لا نعلن Reverb قبل ربط بث فعلي.
            'realtime' => false,
            'always_on' => true, // لا يستطيع المستخدم إطفاءه
            'gateway' => InAppChannelGateway::class,
        ],
        'email' => [
            'enabled' => true,
            'driver' => env('MAIL_MAILER', 'smtp'),
            'gateway' => MailChannelGateway::class,
            'rate_limit_per_minute' => 120,
        ],
        'push' => [
            // خارج قنوات المرحلة الأولى حتى يُسجّل Gateway حقيقي لـ FCM.
            'enabled' => env('PUSH_ENABLED', false),
            'driver' => 'fcm',
        ],
        'whatsapp' => [
            // قرار العميل: إرسال فقط. الردود الواردة تُعرض للإدارة والمشرف
            // فقط ولا تُوجَّه آليًا لأي مستخدم آخر.
            'enabled' => env('WHATSAPP_ENABLED', false),
            'mode' => env('WHATSAPP_MODE', 'outbound_only'),
            'driver' => 'meta_cloud_api',
            'gateway' => WhatsAppCloudGateway::class,
            'token' => env('WHATSAPP_TOKEN', env('WHATSAPP_ACCESS_TOKEN')),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
            'timeout_seconds' => (int) env('WHATSAPP_TIMEOUT_SECONDS', 10),
            'retry_delays_milliseconds' => [200, 500],
            'inbound_visible_to_permissions' => ['messaging.inbound.view'],
            'requires_template' => true,
            'rate_limit_per_minute' => 60,
        ],
        'sms' => [
            'enabled' => false,
        ],
    ],

    'default_channels' => ['in_app', 'email'],

    /*
     * تصنيف الإشعارات وقنواتها الافتراضية.
     * ما هو critical لا يخضع لساعات الهدوء ولا يستطيع المستخدم إيقافه.
     */
    'categories' => [
        'session_reminder' => [
            'channels' => ['in_app', 'whatsapp'],
            'critical' => false,
            'respects_quiet_hours' => false,
        ],
        'session_changed' => ['channels' => ['in_app', 'whatsapp', 'email'], 'critical' => true],
        'postponement_request' => ['channels' => ['in_app', 'whatsapp', 'email'], 'critical' => true],
        'registration_update' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => true],
        'assignment_update' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => true],
        'teacher_workflow' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => true],
        'classroom_invitation' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => true],
        'session_report' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => false],
        'attendance_recorded' => ['channels' => ['in_app'], 'critical' => false],
        'discipline_notice' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => true],
        'enrollment_frozen' => ['channels' => ['in_app', 'email', 'whatsapp'], 'critical' => true],
        'assignment_due' => ['channels' => ['in_app', 'push'], 'critical' => false],
        'grade_published' => ['channels' => ['in_app', 'email'], 'critical' => false],
        'monthly_report' => ['channels' => ['in_app', 'email'], 'critical' => false],
        'payroll_period' => ['channels' => ['in_app', 'email'], 'critical' => true],
        'message_received' => ['channels' => ['in_app', 'push'], 'critical' => false],
        'system_alert' => ['channels' => ['in_app', 'email'], 'critical' => true],
    ],

    /*
     * أحداث المرحلة الأولى. source_events أسماء أصناف وليست imports حتى يبقى
     * التواصل حدثيًا؛ الحقول المدرجة يجب أن تحمل user IDs لا profile IDs.
     */
    'events' => [
        'registration.submitted' => [
            'category' => 'registration_update',
            'audiences' => ['student', 'guardian', 'admin'],
            'recipient_fields' => ['student_user_id', 'guardian_user_ids', 'admin_user_ids'],
            'source_events' => ['Modules\\Students\\Domain\\Events\\RegistrationSubmitted'],
        ],
        'registration.approved' => [
            'category' => 'registration_update',
            'audiences' => ['student', 'guardian'],
            'recipient_fields' => ['student_user_id', 'guardian_user_ids'],
            'source_events' => ['Modules\\Students\\Domain\\Events\\RegistrationAccepted'],
        ],
        'registration.rejected' => [
            'category' => 'registration_update',
            'audiences' => ['student', 'guardian'],
            'recipient_fields' => ['student_user_id', 'guardian_user_ids'],
            'source_events' => ['Modules\\Students\\Domain\\Events\\RegistrationRejected'],
        ],
        'teacher.availability.approved' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher'],
            'recipient_fields' => ['teacher_user_id'],
            'source_events' => ['Modules\\Staff\\Domain\\Events\\TeacherAvailabilityApproved'],
        ],
        'student.assigned_to_teacher' => [
            'category' => 'assignment_update',
            'audiences' => ['student', 'guardian', 'teacher'],
            'recipient_fields' => ['student_user_id', 'guardian_user_ids', 'teacher_user_id'],
            'source_events' => ['Modules\\Students\\Domain\\Events\\StudentAssignedToTeacher'],
        ],
        'student.assigned_to_group' => [
            'category' => 'assignment_update',
            'audiences' => ['student', 'guardian', 'teacher'],
            'recipient_fields' => ['student_user_id', 'guardian_user_ids', 'teacher_user_ids'],
            'source_events' => ['Modules\\Groups\\Domain\\Events\\StudentAssignedToGroup'],
        ],
        'session.scheduled' => [
            'category' => 'session_changed',
            'audiences' => ['student', 'guardian', 'teacher'],
            'recipient_fields' => ['student_user_ids', 'guardian_user_ids', 'teacher_user_id'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\SessionScheduled'],
        ],
        'session.rescheduled' => [
            'category' => 'session_changed',
            'audiences' => ['student', 'guardian', 'teacher'],
            'recipient_fields' => ['student_user_ids', 'guardian_user_ids', 'teacher_user_id'],
            'source_events' => [
                'Modules\\Sessions\\Domain\\Events\\SessionRescheduled',
                'Modules\\Sessions\\Domain\\Events\\SessionPostponed',
            ],
        ],
        'teacher.apology.submitted' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher', 'supervisor', 'admin'],
            'recipient_fields' => ['teacher_user_id', 'supervisor_user_ids', 'admin_user_ids'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\TeacherApologySubmitted'],
        ],
        'teacher.apology.approved' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher', 'supervisor'],
            'recipient_fields' => ['teacher_user_id', 'supervisor_user_ids'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\TeacherApologyApproved'],
        ],
        'teacher.apology.rejected' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher', 'supervisor'],
            'recipient_fields' => ['teacher_user_id', 'supervisor_user_ids'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\TeacherApologyRejected'],
        ],
        'session.substitute.required' => [
            'category' => 'teacher_workflow',
            'audiences' => ['supervisor', 'admin'],
            'recipient_fields' => ['supervisor_user_ids', 'admin_user_ids'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\SessionSubstituteRequired'],
        ],
        'session.substitute.assigned' => [
            'category' => 'session_changed',
            'audiences' => ['student', 'guardian', 'teacher', 'supervisor'],
            'recipient_fields' => [
                'student_user_ids',
                'guardian_user_ids',
                'original_teacher_user_id',
                'substitute_teacher_user_id',
                'supervisor_user_ids',
            ],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\SessionSubstituteAssigned'],
        ],
        'session.substitute.changed' => [
            'category' => 'session_changed',
            'audiences' => ['student', 'guardian', 'teacher', 'supervisor'],
            'recipient_fields' => [
                'student_user_ids',
                'guardian_user_ids',
                'original_teacher_user_id',
                'substitute_teacher_user_id',
                'supervisor_user_ids',
            ],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\SessionSubstituteChanged'],
        ],
        'session.approaching' => [
            'category' => 'session_reminder',
            'audiences' => ['student', 'guardian', 'teacher'],
            'recipient_fields' => ['student_user_ids', 'guardian_user_ids', 'teacher_user_id'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\SessionApproaching'],
        ],
        'session.joinable' => [
            'category' => 'session_reminder',
            'audiences' => ['student', 'teacher'],
            'recipient_fields' => ['student_user_ids', 'teacher_user_id'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\SessionJoinable'],
        ],
        'classroom.guest_invited' => [
            'category' => 'classroom_invitation',
            'audiences' => ['guest', 'admin'],
            'recipient_fields' => ['guest_user_id', 'admin_user_ids'],
            'source_events' => ['Modules\\VirtualClassroom\\Domain\\Events\\ClassroomGuestInvited'],
        ],
        'teacher.apology.second_warning' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher', 'supervisor'],
            'recipient_fields' => ['teacher_user_id', 'supervisor_user_ids'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\TeacherApologySecondWarning'],
        ],
        'teacher.apology.third_escalation' => [
            'category' => 'teacher_workflow',
            'audiences' => ['teacher', 'supervisor', 'admin'],
            'recipient_fields' => ['teacher_user_id', 'supervisor_user_ids', 'admin_user_ids'],
            'source_events' => ['Modules\\Sessions\\Domain\\Events\\TeacherApologyThirdEscalation'],
        ],
        'session.report.due' => [
            'category' => 'session_report',
            'audiences' => ['teacher'],
            'recipient_fields' => ['teacher_user_id'],
            'source_events' => ['Modules\\AcademicReports\\Domain\\Events\\SessionReportDue'],
        ],
        'session.report.late' => [
            'category' => 'session_report',
            'audiences' => ['teacher', 'supervisor'],
            'recipient_fields' => ['teacher_user_id', 'supervisor_user_ids'],
            'source_events' => ['Modules\\AcademicReports\\Domain\\Events\\SessionReportLate'],
        ],
    ],

    /*
     * ساعات الهدوء بتوقيت المستلم — تُؤجَّل غير الحرجة حتى انتهائها.
     */
    'quiet_hours' => [
        'enabled' => true,
        'start' => env('NOTIFY_QUIET_HOURS_START', '22:00'),
        'end' => env('NOTIFY_QUIET_HOURS_END', '07:00'),
        'respects_recipient_timezone' => true,
    ],

    /*
     * التسليم وإعادة المحاولة.
     */
    'delivery' => [
        'max_retries' => env('NOTIFY_MAX_RETRIES', 5),
        'backoff_seconds' => [60, 300, 900, 3600, 10800],
        'queue' => env('NOTIFY_QUEUE', 'notifications'),
        'dispatch_batch_size' => env('NOTIFY_DISPATCH_BATCH_SIZE', 100),
        'retry_batch_size' => env('NOTIFY_RETRY_BATCH_SIZE', 50),

        // مفتاح التكرار: نفس الحدث لنفس المستلم على نفس القناة لا يُرسل مرتين.
        'idempotency_window_minutes' => 30,
        'track_delivery_status' => true,
        'store_payload_days' => 90,
    ],

    /*
     * اللغة: لغة المستلم، ثم لغة المؤسسة، ثم الافتراضية.
     */
    'localization' => [
        'fallback_locale' => 'ar',
        'supported' => ['ar', 'en', 'fr'],
        'datetime_format' => 'Y-m-d H:i T',
        'datetime_parameters' => [
            'scheduled_start',
            'scheduled_end',
            'makeup_start',
            'makeup_end',
            'expires_at',
            'due_at',
        ],
    ],

    /*
     * إشعارات ولي الأمر.
     * أكثر من نصف الطلاب قُصّر — ولي الأمر مستلم أصيل وليس نسخة كربونية.
     */
    'guardian' => [
        'receives_by_default' => true,
        'categories' => [
            'session_reminder',
            'session_changed',
            'discipline_notice',
            'enrollment_frozen',
            'monthly_report',
            'grade_published',
        ],

        // دون هذه السن يُخطَر ولي الأمر دائمًا حتى لو أطفأ الطالب الإشعار.
        'mandatory_under_age' => 18,
    ],
];
