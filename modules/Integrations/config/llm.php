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
    | المشغّل الفعّال. الافتراضي null عمدًا: الميزة تعمل كاملة بلا شبكة ولا
    | تكلفة حتى يُدخَل المفتاح ويُبدَّل المشغّل صراحةً.
    */
    'driver' => (string) Env::get('LLM_DRIVER', 'null'),

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

            /*
            | التسعير بوحدات صغرى صحيحة (ميكرو-دولار لكل مليون توكِن). لا float
            | في أي حساب مال في هذا المشروع. تُحدَّث يدويًا عند تغيّر أسعار
            | المزوّد — الرقم هنا لا يؤثر في الفوترة، بل في عدّاد الاستهلاك
            | وسقف الإنفاق اليومي فقط.
            */
            'pricing' => [
                'input_micro_usd_per_million' => (int) Env::get('LLM_PRICE_INPUT_MICRO', 1_000_000),
                'output_micro_usd_per_million' => (int) Env::get('LLM_PRICE_OUTPUT_MICRO', 5_000_000),
            ],
        ],

        /*
        | المشغّل بلا شبكة. الردود هنا حتمية ويضبطها الاختبار لأي حالة.
        */
        'null' => [
            'classify' => (string) Env::get('LLM_NULL_CLASSIFY', 'platform_help'),
            'answer' => (string) Env::get('LLM_NULL_ANSWER', 'هذه إجابة تجريبية من المشغّل المحلي.'),
        ],

    ],

];
