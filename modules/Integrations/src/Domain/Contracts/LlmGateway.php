<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Contracts;

use Modules\Integrations\Domain\ValueObjects\LlmRequest;
use Modules\Integrations\Domain\ValueObjects\LlmResult;

/**
 * بوابة النموذج اللغوي — الباب الوحيد لأي نداء خارجي لمزوّد ذكاء اصطناعي.
 *
 * المنفّذ ملزم بثلاثة أشياء:
 *  - لا يرمي استثناءً: كل فشل يعود LlmResult::rejected مع تصنيف قابلية الإعادة.
 *  - لا يسجّل محتوى الطلب أو الرد في أي log. سجلّ الموقع يعمل على مستوى debug
 *    ويكتب على القرص، فطباعة رسالة مستخدم هناك تسريب دائم لبيانات طالب.
 *  - لا يعرف شيئًا عن الأدوار أو المواضيع المحظورة؛ ذلك قرار موديول البوت.
 */
interface LlmGateway
{
    public function complete(LlmRequest $request): LlmResult;
}
