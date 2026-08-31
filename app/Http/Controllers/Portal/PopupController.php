<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Notifications\Application\Actions\RecordPopupInteractionAction;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Contracts\PopupAudienceResolver;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\ValueObjects\ActivePopupData;
use Shared\Support\BusinessRuleViolation;

/**
 * نقاط النافذة المنبثقة للمستخدم النهائي — مصادقة جلسة + عزل مؤسسة.
 *
 * قرار الأهلية والتتبع Server-Side بالكامل؛ المستخدم لا يستطيع تسجيل
 * تفاعل نيابة عن غيره ولا لمؤسسة أخرى (لا IDOR).
 */
final class PopupController extends Controller
{
    public function __construct(
        private readonly PopupQueries $popups,
        private readonly PopupAudienceResolver $audienceResolver,
        private readonly RecordPopupInteractionAction $interactions,
    ) {}

    /** GET /popups/active — نافذة واحدة مؤهلة كحد أقصى أو null. */
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

        // AfterLogin يظهر مرة واحدة على أول صفحة بعد الدخول: الواجهة تطلبه
        // بعلامة after_login فقط في أول تحميل بعد المصادقة.
        $popup = $this->popups->activeForUser(
            organizationId: (string) data_get($user, 'organization_id'),
            userId: (string) $user->getAuthIdentifier(),
            userAudiences: $this->audienceResolver->audiencesFor(self::modelType(), (string) $user->getAuthIdentifier()),
            placement: $placement,
            pageKey: $pageKey,
            loginMarker: self::loginMarker(),
            now: now('UTC')->toImmutable(),
        );

        $payload = $popup === null
            ? null
            : self::serialize($popup);

        return response()->json([
            'popup' => $payload,
            // نفس مكوّن الواجهة الحقيقي يُرسل جاهزًا — المعاينة تستخدمه أيضًا.
            'html' => $payload === null ? null : view('notifications::popups.card', [
                'popup' => $payload,
                'preview' => false,
            ])->render(),
        ]);
    }

    /** POST /popups/{campaign}/{interaction} — idempotent ومحمي بالجلسة. */
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

        try {
            $this->interactions->execute(
                campaignId: $campaign,
                userId: (string) $user->getAuthIdentifier(),
                organizationId: (string) data_get($user, 'organization_id'),
                type: $interaction,
                loginMarker: self::loginMarker(),
            );
        } catch (BusinessRuleViolation) {
            // تفاعل غير مسموح (حملة منتهية/غير قابلة للإغلاق/لم تُشاهد) — بلا كشف تفاصيل.
            return response()->json(['ok' => false], 204);
        }

        return response()->json(['ok' => true]);
    }

    private static function modelType(): string
    {
        return app(UserQueryService::class)->modelType();
    }

    private static function loginMarker(): ?string
    {
        $marker = session()->get('popup.login_marker');

        return is_string($marker) && $marker !== '' ? $marker : null;
    }

    /**
     * مفتاح الصفحة يُنظَّف: مفاتيح قانونية فقط من السجل — لا حقن route.
     */
    private static function safePageKey(string $raw): ?string
    {
        $key = trim(mb_substr($raw, 0, (int) config('popups.content.page_key_max')));

        return PopupPageRegistry::isValid($key)
            ? $key
            : null;
    }

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     icon: string,
     *     color: string,
     *     title: string,
     *     body: string,
     *     acknowledgement_label: string,
     *     action_label: string|null,
     *     action_url: string|null,
     *     action_is_external: bool,
     *     is_dismissible: bool,
     *     requires_acknowledgement: bool,
     *     starts_at: string,
     *     ends_at: string|null
     * }
     */
    private static function serialize(ActivePopupData $popup): array
    {
        return [
            'id' => $popup->campaignId,
            'type' => $popup->type,
            'icon' => $popup->typeIcon,
            'color' => $popup->typeColor,
            'title' => $popup->title['value'],
            'body' => $popup->body['value'],
            'acknowledgement_label' => $popup->acknowledgementLabel ?? __('notifications::popups.frontend.acknowledge_default'),
            'action_label' => $popup->actionLabel,
            'action_url' => $popup->actionUrl,
            'action_is_external' => $popup->actionIsExternal,
            'is_dismissible' => $popup->isDismissible,
            'requires_acknowledgement' => $popup->requiresAcknowledgement,
            'starts_at' => $popup->startsAt->toIso8601String(),
            'ends_at' => $popup->endsAt?->toIso8601String(),
        ];
    }
}
