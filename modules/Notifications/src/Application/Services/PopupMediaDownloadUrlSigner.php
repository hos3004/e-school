<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * مصدر واحد لتوقيع روابط تنزيل مرفقات الحملات المنبثقة — تُستخدم من مسارين:
 *
 *  - App\Http\Controllers\Api\PopupMessageController::requestMediaDownloadUrl()
 *    لمسار media[] القائم (صورة/فيديو/صوت/ملف) — يطلب رابطًا بشكل منفصل بعد
 *    التحقق من الأهلية.
 *  - Modules\Notifications\Application\Queries\EloquentPopupQueryService::resolveLinks()
 *    لمسار links[] (إشارات popup-media:{id} داخل نص الرسالة) — يُبنى الرابط
 *    مباشرة أثناء activeForUser() لمستخدم معروف بالفعل، دون حاجة لخطوة طلب
 *    منفصلة لأن الأهلية محسومة أصلًا في تلك اللحظة.
 *
 * التوقيع يغطي campaign وmedia وuser معًا (نفس عقد الـroute المسمّى
 * popups.media.download خلف middleware `signed`) — تبديل أي منها بعد
 * الإصدار يُبطل التوقيع فورًا، وهذا وحده كافٍ لمنع إعادة الاستخدام لمستخدم
 * آخر أو بعد انتهاء المهلة القصيرة من config('popups.attachments.download_link_ttl_minutes').
 *
 * الرابط بلا Sanctum عمدًا: مدير تنزيل نظام الموبايل (external launch) لا
 * يرسل ترويسة Authorization، فالحماية بالكامل عبر التوقيع القصير العمر.
 */
final readonly class PopupMediaDownloadUrlSigner
{
    public static function sign(string $campaignId, string $mediaId, string $userId): string
    {
        return URL::temporarySignedRoute(
            'popups.media.download',
            CarbonImmutable::now('UTC')->addMinutes(self::ttlMinutes()),
            [
                'campaign' => $campaignId,
                'media' => $mediaId,
                'user' => $userId,
            ],
        );
    }

    public static function ttlMinutes(): int
    {
        return (int) config('popups.attachments.download_link_ttl_minutes');
    }
}
