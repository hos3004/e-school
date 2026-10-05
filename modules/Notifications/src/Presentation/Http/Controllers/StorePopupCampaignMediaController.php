<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Modules\Notifications\Presentation\Http\Requests\StorePopupCampaignMediaRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * رفع ملف ميديا لحملة منبثقة — الرفع الفعلي هو المُنتِج الشرعي الوحيد
 * لصفوف popup_campaign_media. القرص والمجلد من config دائمًا؛ الطلب لا
 * يستطيع فرض أي منهما. نفس نمط StoreWallPostController في Messaging.
 */
final class StorePopupCampaignMediaController extends Controller
{
    public function __invoke(StorePopupCampaignMediaRequest $request, PopupCampaign $campaign): JsonResponse
    {
        $kind = $request->string('kind')->toString();
        $file = $request->file('file');

        $disk = (string) config('popups.attachments.disk');
        $directory = trim((string) config('popups.attachments.directory'), '/').'/'.((string) $campaign->getKey());
        $path = $file->store($directory, $disk);

        $nextPosition = 1 + (int) PopupCampaignMedia::query()
            ->where('campaign_id', (string) $campaign->getKey())
            ->max('position');

        $media = PopupCampaignMedia::query()->create([
            'campaign_id' => (string) $campaign->getKey(),
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'original_name' => (string) $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'has_sound' => $kind === 'video' ? $request->boolean('has_sound') : null,
            'position' => $nextPosition,
        ]);

        return response()->json([
            'data' => [
                'id' => (string) $media->getKey(),
                'kind' => $media->kind,
                'mime_type' => $media->mime_type,
                'original_name' => $media->original_name,
                'size_bytes' => $media->size_bytes,
                'has_sound' => $media->has_sound,
                'position' => $media->position,
                'url' => route('popups.media.show', [
                    'campaign' => (string) $campaign->getKey(),
                    'media' => (string) $media->getKey(),
                ]),
            ],
        ], Response::HTTP_CREATED);
    }
}
