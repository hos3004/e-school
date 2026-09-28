<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Notifications\Domain\Contracts\PopupAudienceResolver;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * يخدم ملف ميديا مرفقًا بحملة منبثقة (صورة/فيديو/صوت) للعرض المضمَّن داخل
 * التطبيق. القرص ثابت من الإعداد دائمًا — لا نثق بعمود disk المخزَّن ولو
 * كتبه رفعنا نفسه، لأن أي تعديل مستقبلي على الصف (استيراد، تصحيح يدوي)
 * لا يجب أن يقدر يغيّر القرص الفعلي المخدوم. المسار يُرفض إن لم يبدأ
 * بمجلد هذه الحملة بالذات كما يكتبه الرفع الفعلي وحده — نفس نمط
 * Modules\Messaging\...\ShowWallAttachmentController.
 *
 * التفويض هنا يغطي مسارين شرعيين معًا:
 *  - الأدمن الذي يملك popup_campaign.view (معاينة أثناء التحرير، حتى لو
 *    الحملة ما زالت مسودة غير منشورة).
 *  - المستخدم العادي الذي وصلته الحملة فعليًا: نفس مؤسسة الحملة، حالتها
 *    منشورة، **وجمهوره فعليًا مستهدَف بها ولم يُستثنَ منها** — بنفس فحص
 *    PopupCampaign::isEligibleForAudiences() الذي يطبّقه
 *    EloquentPopupQueryService وRecordPopupInteractionAction. الاكتفاء
 *    بمؤسسة+حالة فقط (دون فحص الجمهور) كان يسمح لأي مستخدم في نفس
 *    المؤسسة برؤية ميديا حملة موجَّهة لغيره أو مُستثنى منها صراحة —
 *    وهذا يُبطل معنى audiences/excluded_audiences كحد وصول لا مجرد
 *    تفضيل عرض.
 *    الاعتماد على PopupCampaignPolicy::view() وحدها كان سيمنع كل مستخدم
 *    عادي (طالب/معلم) من رؤية صور حملة موجَّهة له أصلًا، لأن تلك القدرة
 *    إدارية بحتة ولا تُمنح لغير الإدارة والمشرفين.
 *
 * modelType يُحل من الكائن المصادق نفسه (getMorphClass) لا من
 * Modules\Identity\Domain\Contracts\UserQueryService — هذا الموديول
 * ممنوع معماريًا من الاعتماد على Identity (نفس نمط
 * Modules\Messaging\...\ConversationPolicy::hasPermission()).
 */
final class ShowPopupAttachmentController extends Controller
{
    public function __construct(private readonly PopupAudienceResolver $audienceResolver) {}

    public function __invoke(Request $request, PopupCampaign $campaign, PopupCampaignMedia $media): StreamedResponse
    {
        $user = $request->user();

        abort_if($user === null, 404);

        $isAdminView = Gate::forUser($user)->allows('view', $campaign);

        $isRecipientView = !$isAdminView
            && hash_equals(
                (string) data_get($user, 'organization_id'),
                (string) $campaign->organization_id,
            )
            && $campaign->status === PopupCampaignStatus::Published
            && $campaign->isEligibleForAudiences($this->audienceResolver->audiencesFor(
                self::modelType($user),
                (string) $user->getAuthIdentifier(),
            ));

        abort_unless($isAdminView || $isRecipientView, 404);
        abort_unless((string) $media->campaign_id === (string) $campaign->getKey(), 404);

        $disk = (string) config('popups.attachments.disk');
        $directory = trim((string) config('popups.attachments.directory'), '/');
        $expectedPrefix = $directory.'/'.((string) $campaign->getKey()).'/';

        $path = (string) $media->path;

        abort_unless(str_starts_with($path, $expectedPrefix), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, $media->original_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    private static function modelType(Authenticatable $user): string
    {
        return $user instanceof Model ? $user->getMorphClass() : $user::class;
    }
}
