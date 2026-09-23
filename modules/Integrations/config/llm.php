<?php

declare(strict_types=1);
use Illuminate\Support\Env;

/*
| إعدادات مزوّد النموذج اللغوي — كل رقم سياسة هنا وليس في الكود.
|
| المفتاح نفسه ليس هنا ولا في البيئة: يُخزَّن مشفّرًا في integration_connections
| ويُدخَل بسكربت على الخادم. ما هنا هو ما يجوز أن يقرأه أي مطوّر.
*/

return [

    /*
    | المشغّل الفعّال. anthropic افتراضيًا وهو آمن: بلا صف اتصال مُفعَّل يرفض
    | المشغّل كل نداء قبل أي شبكة (llm_disabled)، فلا تكلفة قبل أن يُدخِل الأدمن
    | المفتاح ويشغّل البوت. المشغّل null للاختبارات وحدها وتضبطه بنفسها.
    */
    'driver' => (string) Env::get('LLM_DRIVER', 'anthropic'),

    'providers' => [

        'anthropic' => [
            'base_url' => (string) Env::get('LLM_ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'api_version' => (string) Env::get('LLM_ANTHROPIC_API_VERSION', '2023-06-01'),

            /*
            | نموذجان لمرحلتين. التصنيف مهمة ميكانيكية مخرجها كلمة واحدة من
            | قائمة مغلقة، فلا يحتاج أكثر من أصغر نموذج؛ والرد يحتاج لغة عربية
            | لائقة. فصلهما يسمح برفع نموذج الرد وحده دون مضاعفة تكلفة التصنيف.
            */
            'models' => [
                'classify' => (string) Env::get('LLM_MODEL_CLASSIFY', 'claude-haiku-4-5-20251001'),
                'answer' => (string) Env::get('LLM_MODEL_ANSWER', 'claude-haiku-4-5-20251001'),
            ],

            /*
            | سقف التوليد. التصنيف يحتاج توكِنات معدودة — السقف المنخفض هنا حارس
            | تكلفة لا مجرد ضبط: نموذج ينحرف عن التعليمات ويبدأ شرحًا مطوّلًا
            | يتوقف بعد بضع توكِنات بدل أن يكتب صفحة.
            */
            'max_tokens' => [
                'classify' => (int) Env::get('LLM_MAX_TOKENS_CLASSIFY', 16),
                'answer' => (int) Env::get('LLM_MAX_TOKENS_ANSWER', 700),
            ],

            /*
            | المهلة. المستخدم ينتظر أمام الشاشة، فالمهلة الطويلة تعني فقاعة
            | معلّقة. الأفضل رد مهذّب سريع عند التعثّر.
            */
            'timeout_seconds' => (int) Env::get('LLM_TIMEOUT_SECONDS', 25),
            'connect_timeout_seconds' => (int) Env::get('LLM_CONNECT_TIMEOUT_SECONDS', 5),

            /*
            | إعادة المحاولة لأخطاء الخادم فقط. 429 لا تُعاد داخل طلب متزامن —
            | انظر شرح AnthropicGateway.
            */
            'retry_delays_milliseconds' => [400, 1200],

            /*
            | قاطع الدارة: بعد هذا العدد من الإخفاقات المتتالية تُرفض النداءات
            | فورًا لهذه المدة، فلا ينتظر كل مستخدم مهلة كاملة أمام مزوّد متعثّر.
            */
            'circuit_failure_threshold' => (int) Env::get('LLM_CIRCUIT_FAILURE_THRESHOLD', 4),
            'circuit_open_seconds' => (int) Env::get('LLM_CIRCUIT_OPEN_SECONDS', 60),

        ],

        /*
        | المشغّل بلا شبكة. الردود هنا حتمية ويضبطها الاختبار لأي حالة.
        */
        'null' => [
            'classify' => (string) Env::get('LLM_NULL_CLASSIFY', 'platform_help'),
            'answer' => (string) Env::get('LLM_NULL_ANSWER', ''),
        ],

    ],

    /*
    | التسعير لكل نموذج بوحدات صغرى صحيحة: ميكرو-دولار لكل مليون توكِن.
    |
    | لا يؤثر في أي فوترة؛ يغذّي عدّاد الاستهلاك والسقف اليومي فقط. عند تغيير
    | نموذج في الإعداد أعلاه يجب إضافة سعره هنا من صفحة أسعار المزوّد، وإلا حُسب
    | بسعر unpriced_model المتشائم عمدًا — فيبلغ السقف مبكرًا بدل أن يتجاوزه
    | بصمت.
    */
    'pricing' => [
        'models' => [
            'claude-haiku-4-5-20251001' => ['input' => 1_000_000, 'output' => 5_000_000],
        ],
        'unpriced_model' => ['input' => 15_000_000, 'output' => 75_000_000],
    ],

];
