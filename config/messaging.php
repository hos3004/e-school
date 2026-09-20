<?php

declare(strict_types=1);

return [

    /*
     * حملات واتساب: إرسال جماعي إلى أرقام لا تملك حسابًا على المنصة.
     *
     * هذا المسار منفصل عن صندوق صادر الإشعارات عمدًا: الصندوق يربط كل رسالة
     * بمستخدم (user_id غير قابل للإفراغ) ويحترم تفضيلاته وساعات هدوئه، وهي
     * معانٍ لا وجود لها لرقم خارجي. لذلك للحملات جداولها وحالاتها.
     */
    'campaigns' => [

        'queue' => env('WHATSAPP_CAMPAIGN_QUEUE', 'notifications'),

        // سقف المستلمين في الحملة الواحدة — يحمي المزوّد والطابور من دفعة ضخمة.
        'max_recipients' => (int) env('WHATSAPP_CAMPAIGN_MAX_RECIPIENTS', 2000),

        /*
         * المهلة بين رسالة وأخرى تُختار عشوائيًا بين الحدّين لكل مستلم.
         * العشوائية مقصودة: إيقاع ثابت تمامًا هو أوضح ما يميّز المرسل الآلي.
         */
        'delay' => [
            'floor_seconds' => (int) env('WHATSAPP_CAMPAIGN_DELAY_FLOOR', 3),
            'ceiling_seconds' => (int) env('WHATSAPP_CAMPAIGN_DELAY_CEILING', 3600),
            'default_min_seconds' => (int) env('WHATSAPP_CAMPAIGN_DELAY_MIN', 5),
            'default_max_seconds' => (int) env('WHATSAPP_CAMPAIGN_DELAY_MAX', 15),
        ],

        /*
         * الوسائط تُرفع إلى قرص خاص لا يخدم الملفات عبر الويب، وتُسلَّم للمزوّد
         * برفع مباشر لا برابط عام — فلا يصبح مرفق حملة رابطًا يتداوله الناس.
         */
        'media' => [
            'disk' => env('WHATSAPP_CAMPAIGN_MEDIA_DISK', 'local'),
            'directory' => 'whatsapp-campaigns',
            'retention_days' => (int) env('WHATSAPP_CAMPAIGN_MEDIA_RETENTION_DAYS', 7),
            'max_files' => (int) env('WHATSAPP_CAMPAIGN_MEDIA_MAX_FILES', 5),
            'max_size_kilobytes' => (int) env('WHATSAPP_CAMPAIGN_MEDIA_MAX_KB', 16384),
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'video/mp4',
                'audio/mpeg',
                'audio/ogg',
                'application/pdf',
            ],
        ],

        /*
         * الرموز التي تُستبدل باسم المستلم كما ورد في قائمته. رمز بلا اسم
         * مقابل يُستبدل بفراغ ثم تُنظَّف المسافات، فلا تخرج رسالة فيها «{الاسم}».
         */
        'placeholders' => ['{الاسم}', '{name}'],

        /*
         * شبكة أمان: مستلم فات موعده بهذا القدر ولم تلتقطه مهمته المؤجلة
         * (إعادة تشغيل، أو فقد مفتاح من الطابور) يُعاد توزيعه من المجدول.
         */
        'overdue_sweep_minutes' => (int) env('WHATSAPP_CAMPAIGN_SWEEP_MINUTES', 5),

        'sweep_batch_size' => (int) env('WHATSAPP_CAMPAIGN_SWEEP_BATCH', 200),
    ],
];
