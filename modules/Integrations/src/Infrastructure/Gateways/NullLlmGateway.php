<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Gateways;

use Modules\Integrations\Domain\Contracts\LlmGateway;
use Modules\Integrations\Domain\ValueObjects\LlmRequest;
use Modules\Integrations\Domain\ValueObjects\LlmResult;

/**
 * مشغّل بلا شبكة — نظير NullProvider في موديول الفصل الافتراضي.
 *
 * له غرضان حقيقيان، وليس مجرد بديل للاختبارات:
 *
 *  1. تشغيل الميزة كاملة قبل وجود مفتاح المزوّد. الحارس وقاعدة المعرفة
 *     والصلاحيات والأرشيف والاستهلاك كلها قابلة للاختبار من طرف إلى طرف بلا
 *     نداء خارجي ولا تكلفة.
 *
 *  2. اختبارات حتمية. الرد يأتي من الإعداد لا من نموذج، فالاختبار يثبت سلوك
 *     الحارس لا مزاج النموذج — وهذا بالضبط ما يجب أن يثبته.
 *
 * الردود من config('llm.providers.null.*') حتى يضبطها الاختبار لأي حالة.
 */
final readonly class NullLlmGateway implements LlmGateway
{
    public function complete(LlmRequest $request): LlmResult
    {
        $key = $request->purpose === LlmRequest::PURPOSE_CLASSIFY ? 'classify' : 'answer';
        $configured = config('llm.providers.null.'.$key);
        $text = is_string($configured) && trim($configured) !== '' ? trim($configured) : '';

        if ($text === '') {
            return LlmResult::rejected('llm_null_no_canned_response', false, $request->model);
        }

        /*
         * عدّادات تقريبية لا حقيقية. الغرض أن يعمل مسار حساب الاستهلاك وحدوده
         * في الاختبارات؛ رقم صفر كان سيخفي خطأً في العدّاد بدل أن يكشفه.
         */
        return LlmResult::accepted(
            $text,
            $request->model,
            (int) ceil(mb_strlen($request->system.$request->lastUserMessage()) / 4),
            (int) ceil(mb_strlen($text) / 4),
            0,
            'end_turn',
        );
    }
}
