<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ينزّل ملفًا مرفقًا عبر رابط موقَّع قصير العمر — بلا ترويسة Authorization،
 * ليعمل مع مدير تنزيل نظام الموبايل مباشرة.
 *
 * الحماية بالكامل عبر middleware `signed`: التوقيع يغطي campaign وmedia
 * وuser معًا، فتغيير أي منها بعد الإصدار (بما فيها معرّف المستخدم) يُبطل
 * التوقيع فورًا — هذا ما يمنع إعادة استخدام الرابط لمستخدم آخر أو بعد
 * انتهاء صلاحيته (expires محسوبة ضمن نفس التوقيع). لا حاجة لفحص مصادقة
 * إضافي هنا، والقرص والمسار كما في ShowPopupAttachmentController: من
 * config دائمًا، والمسار يُرفض إن لم يبدأ بمجلد هذه الحملة بالذات.
 */
final class DownloadPopupAttachmentController extends Controller
{
    public function __invoke(PopupCampaign $campaign, PopupCampaignMedia $media, string $user): StreamedResponse
    {
        abort_unless((string) $media->campaign_id === (string) $campaign->getKey(), 404);
        abort_unless($media->kind === 'file', 404);

        $disk = (string) config('popups.attachments.disk');
        $directory = trim((string) config('popups.attachments.directory'), '/');
        $expectedPrefix = $directory.'/'.((string) $campaign->getKey()).'/';

        $path = (string) $media->path;

        abort_unless(str_starts_with($path, $expectedPrefix), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $media->original_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
