<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\PopupController as PortalPopupController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Notifications\Application\Actions\RecordPopupInteractionAction;
use Modules\Notifications\Application\Services\PopupMediaDownloadUrlSigner;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Contracts\PopupAudienceResolver;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignMedia;
use Shared\Support\BusinessRuleViolation;

/**
 * نقاط النافذة المنبثقة للموبايل — Sanctum بدل الجلسة، بلا أي منطق عمل
 * جديد: نفس PopupQueries/PopupAudienceResolver/RecordPopupInteractionAction
 * التي يستخدمها Portal\PopupController تمامًا، ونفس دالة التسلسل
 * PortalPopupController::serialize() الثابتة لضمان بقاء الواجهتين متطابقتين.
 *
 * requestMediaDownloadUrl() يعيش هنا (طبقة App) لا داخل موديول Notifications
 * عمدًا: يحتاج حلّ modelType عبر Modules\Identity\Domain\Contracts\UserQueryService،
 * والموديول ممنوع معماريًا من الاعتماد على Identity (tests/Architecture).
 * نفس القيد الذي يفسّر لماذا active()/interact() أنفسهما في app/ لا في
 * الموديول. توقيع الرابط نفسه مفوَّض لـ
 * Modules\Notifications\Application\Services\PopupMediaDownloadUrlSigner —
 * نفس الدالة التي تستخدمها EloquentPopupQueryService::resolveLinks() لمسار
 * links[] — حتى لا يتكرر منطق التوقيع في مكانين.
 */
final class PopupMessageController extends Controller
{
    public function __construct(
        private readonly PopupQueries $popups,
        private readonly PopupAudienceResolver $audienceResolver,
        private readonly RecordPopupInteractionAction $interactions,
    ) {}

    /** GET /api/popups/active */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['popup' => null]);
        }

        $placement = (string) $request->query('placement', 'dashboard');
        $pageKey = self::safePageKey((string) $request->query('page_key', ''));

        if (!in_array($placement, ['after_login', 'dashboard', 'specific_page', 'all_authenticated_pages'], true)) {
            return response()->json(['popup' => null]);
        }

        $userId = (string) $user->getAuthIdentifier();

        $popup = $this->popups->activeForUser(
            organizationId: (string) data_get($user, 'organization_id'),
            userId: $userId,
            userAudiences: $this->audienceResolver->audiencesFor(self::modelType(), $userId),
            placement: $placement,
            pageKey: $pageKey,
            // الموبايل لا يملك جلسة ويب؛ لا يوجد login_marker — قاعدة
            // OncePerLogin تتساهل بدونه (state->login_marker يبقى null).
            loginMarker: null,
            now: now('UTC')->toImmutable(),
        );

        return response()->json([
            'popup' => $popup === null ? null : PortalPopupController::serialize($popup),
        ]);
    }

    /** POST /api/popups/{campaign}/{interaction} */
    public function interact(Request $request, string $campaign, string $interaction): JsonResponse
    {
        $user = $request->user();

        if ($user === null || !in_array($interaction, [
            RecordPopupInteractionAction::TYPE_IMPRESSION,
            RecordPopupInteractionAction::TYPE_DISMISS,
            RecordPopupInteractionAction::TYPE_ACKNOWLEDGE,
            RecordPopupInteractionAction::TYPE_CLICK,
        ], true)) {
            abort(404);
        }

        $userId = (string) $user->getAuthIdentifier();

        try {
            $this->interactions->execute(
                campaignId: $campaign,
                userId: $userId,
                organizationId: (string) data_get($user, 'organization_id'),
                type: $interaction,
                loginMarker: null,
                userAudiences: $this->audienceResolver->audiencesFor(self::modelType(), $userId),
            );
        } catch (BusinessRuleViolation) {
            return response()->json(['ok' => false], 204);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/popups/{campaign}/media/{media}/download-request
     *
     * يصدر رابط تنزيل موقَّعًا قصير العمر لملف قابل للتنزيل (kind=file) —
     * لاستخدامه من مدير تنزيل نظام الموبايل الذي لا يرسل ترويسة
     * Authorization. الأهلية تُفحص بنفس آلية active() بالضبط: يجب أن تكون
     * هذه الحملة فعليًا النافذة المؤهلة لهذا المستخدم الآن على موضعها
     * المعلن، وإلا يُرفض الطلب (لا تسريب روابط لحملة لم تُعرض له).
     */
    public function requestMediaDownloadUrl(Request $request, string $campaign, string $media): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 404);

        /** @var PopupCampaign|null $campaignModel */
        $campaignModel = PopupCampaign::query()->find($campaign);
        abort_if($campaignModel === null, 404);

        /** @var PopupCampaignMedia|null $mediaModel */
        $mediaModel = PopupCampaignMedia::query()->find($media);
        abort_if($mediaModel === null || (string) $mediaModel->campaign_id !== (string) $campaignModel->getKey(), 404);
        abort_unless($mediaModel->kind === 'file', 422);

        $userId = (string) $user->getAuthIdentifier();

        $eligible = $this->popups->activeForUser(
            organizationId: (string) data_get($user, 'organization_id'),
            userId: $userId,
            userAudiences: $this->audienceResolver->audiencesFor(self::modelType(), $userId),
            placement: $campaignModel->placement->value,
            pageKey: $campaignModel->page_key,
            loginMarker: null,
            now: CarbonImmutable::now('UTC'),
        );

        abort_unless($eligible !== null && $eligible->campaignId === (string) $campaignModel->getKey(), 404);

        $url = PopupMediaDownloadUrlSigner::sign(
            (string) $campaignModel->getKey(),
            (string) $mediaModel->getKey(),
            $userId,
        );

        return response()->json([
            'url' => $url,
            'expires_in_minutes' => PopupMediaDownloadUrlSigner::ttlMinutes(),
        ]);
    }

    private static function modelType(): string
    {
        return app(UserQueryService::class)->modelType();
    }

    private static function safePageKey(string $raw): ?string
    {
        $key = trim(mb_substr($raw, 0, (int) config('popups.content.page_key_max')));

        return PopupPageRegistry::isValid($key)
            ? $key
            : null;
    }
}
