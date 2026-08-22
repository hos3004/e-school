<?php

declare(strict_types=1);

use Modules\VirtualClassroom\Infrastructure\Providers\BigBlueButtonProvider;
use Modules\VirtualClassroom\Infrastructure\Providers\LiveKitProvider;
use Modules\VirtualClassroom\Infrastructure\Providers\NullProvider;
use Modules\VirtualClassroom\Infrastructure\Providers\WherebyProvider;
use Modules\VirtualClassroom\Infrastructure\Providers\ZoomVideoProvider;

/**
 * الفصل المباشر.
 *
 * بقية المنصة لا تعرف ولا تهتم بمن يشغّل الفصل. كل التعامل يتم عبر
 * VirtualClassroomProvider. تبديل المزوّد لاحقًا = كتابة Adapter جديد فقط.
 *
 * القرار الحالي: BigBlueButton — لأنه مبني للتعليم أصلًا (سبورة مشتركة،
 * غرف فرعية، استطلاعات، رفع ملفات، تحكم المعلم، تسجيل) وتكلفته المستضافة
 * أقل بكثير من التسعير بالدقيقة عند حجم استخدامنا.
 *
 * انظر docs/11-provider-interfaces.md و docs/18-ADRs.md (ADR-003)
 */
return [

    'default' => env('CLASSROOM_PROVIDER', 'bigbluebutton'),

    'providers' => [

        'bigbluebutton' => [
            'driver' => BigBlueButtonProvider::class,
            'base_url' => env('BBB_BASE_URL'),
            'secret' => env('BBB_SECRET'),
            'webhook_secret' => env('BBB_WEBHOOK_SECRET'),
            'supports' => [
                'whiteboard' => true,
                'breakout_rooms' => true,
                'polls' => true,
                'shared_notes' => true,
                'file_upload' => true,
                'recording' => true,
                'moderator_controls' => true,
                'raise_hand' => true,
                'private_chat' => true,
            ],
        ],

        'zoom' => [
            'driver' => ZoomVideoProvider::class,
            'sdk_key' => env('ZOOM_SDK_KEY'),
            'sdk_secret' => env('ZOOM_SDK_SECRET'),
        ],

        'whereby' => [
            'driver' => WherebyProvider::class,
            'api_key' => env('WHEREBY_API_KEY'),
        ],

        'livekit' => [
            'driver' => LiveKitProvider::class,
            'url' => env('LIVEKIT_URL'),
            'api_key' => env('LIVEKIT_API_KEY'),
            'api_secret' => env('LIVEKIT_API_SECRET'),
        ],

        'null' => [
            'driver' => NullProvider::class,
        ],
    ],

    /**
     * نافذة الدخول للفصل.
     * الطالب لا يستطيع الدخول قبل الموعد بأكثر من هذه المدة.
     */
    'join_window' => [
        'before_minutes' => env('CLASSROOM_JOIN_WINDOW_BEFORE_MINUTES', 10),
        'after_minutes' => env('CLASSROOM_JOIN_WINDOW_AFTER_MINUTES', 15),
        // المعلم يستطيع الدخول أبكر لتجهيز الفصل.
        'teacher_before_minutes' => 20,
    ],

    /**
     * السعات — مشتقة من الوضع الحالي وخطة النمو.
     * اليوم: نحو 5 حصص متزامنة و200 طالب.
     * خلال سنة: 600 طالب. العام التالي: 1500 إلى 2000.
     */
    'capacity' => [
        'max_concurrent_meetings' => env('BBB_MAX_CONCURRENT_MEETINGS', 30),
        'max_participants_group' => 25,
        'max_participants_individual' => 2,
        'max_participants_webinar' => 70,
        // تحذير للإدارة عند تجاوز هذه النسبة من السعة المتزامنة.
        'warn_at_utilization_percent' => 75,
    ],

    /**
     * التسجيل.
     * قرار العميل: كل الحصص تُسجَّل.
     */
    'recording' => [
        'auto_record' => env('CLASSROOM_AUTO_RECORD', true),
        'teacher_can_pause' => true,
        // إشعار ظاهر لكل المشاركين بأن الحصة تُسجَّل — إلزامي لوجود قُصّر.
        'show_consent_notice' => true,
    ],

    /**
     * الأدوار داخل الفصل.
     */
    'roles' => [
        'teacher' => 'moderator',
        'substitute_teacher' => 'moderator',
        'supervisor' => 'moderator',
        'student' => 'viewer',
        'guardian_observer' => 'viewer',
    ],

    /**
     * فحص صحة المزوّد قبل بدء الحصص وعند لوحة الحالة.
     */
    'health_check' => [
        'enabled' => true,
        'interval_minutes' => 5,
        'timeout_seconds' => 10,
        'alert_permission' => 'system.alerts',
    ],
];
