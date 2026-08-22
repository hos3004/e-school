<?php

declare(strict_types=1);

use Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway;

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
            'realtime' => true,  // يُبث فورًا عبر Reverb
            'always_on' => true, // لا يستطيع المستخدم إطفاءه
            'gateway' => InAppChannelGateway::class,
        ],
        'email' => [
            'enabled' => true,
            'driver' => 'ses',
            'rate_limit_per_minute' => 120,
        ],
        'push' => [
            'enabled' => true,
            'driver' => 'fcm',
        ],
        'whatsapp' => [
            // قرار العميل: إرسال فقط. الردود الواردة تُعرض للإدارة والمشرف
            // فقط ولا تُوجَّه آليًا لأي مستخدم آخر.
            'enabled' => env('WHATSAPP_ENABLED', false),
            'mode' => env('WHATSAPP_MODE', 'outbound_only'),
            'driver' => 'meta_cloud_api',
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
            'channels' => ['in_app', 'push', 'whatsapp'],
            'critical' => false,
            'respects_quiet_hours' => false,
        ],
        'session_changed' => ['channels' => ['in_app', 'push', 'whatsapp', 'email'], 'critical' => true],
        'postponement_request' => ['channels' => ['in_app', 'push', 'whatsapp'], 'critical' => true],
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
