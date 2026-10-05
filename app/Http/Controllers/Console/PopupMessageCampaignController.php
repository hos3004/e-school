<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SavePopupMessageCampaignRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Application\Actions\TransitionPopupCampaignAction;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Presentation\Http\Controllers\StorePopupCampaignMediaController;
use Modules\Notifications\Presentation\Http\Requests\StorePopupCampaignMediaRequest;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء/تعديل/نشر/إيقاف/أرشفة حملة من لوحة /manage.
 *
 * كل عملية كتابة تمر بنفس Actions التي تستخدمها لوحة Filament القديمة
 * (SavePopupCampaignAction وTransitionPopupCampaignAction) — لا منطق حفظ
 * أو انتقال حالة مكرَّر هنا، ورفع الميديا يُفوَّض حرفيًا لمتحكم Phase 1
 * دون إعادة كتابة تحقق النوع/الحجم/التخزين.
 */
final class PopupMessageCampaignController extends Controller
{
    public function store(
        SavePopupMessageCampaignRequest $request,
        SavePopupCampaignAction $save,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        try {
            $save->execute(
                campaign: null,
                organizationId: (string) $user->getAttribute('organization_id'),
                attributes: $request->campaignAttributes(),
                scheduleChanges: null,
                actorId: (string) $user->getAuthIdentifier(),
                reason: $request->reason(),
            );
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['internal_name' => $error->getMessage()])->withInput();
        }

        return back()->with('success', (string) __('console_popup_messages.messages.created'));
    }

    public function update(
        SavePopupMessageCampaignRequest $request,
        PopupCampaign $campaign,
        SavePopupCampaignAction $save,
    ): RedirectResponse {
        $this->authorizeCampaign($request, 'update', $campaign);

        $user = $request->user();

        try {
            $save->execute(
                campaign: $campaign,
                organizationId: (string) $user?->getAttribute('organization_id'),
                attributes: $request->campaignAttributes(),
                scheduleChanges: null,
                actorId: (string) $user?->getAuthIdentifier(),
                reason: $request->reason(),
            );
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['internal_name' => $error->getMessage()])->withInput();
        }

        return back()->with('success', (string) __('console_popup_messages.messages.updated'));
    }

    /**
     * رفع ميديا لحملة — تفويض حرفي لمتحكم Phase 1، بلا نسخ لمنطقه.
     * StorePopupCampaignMediaRequest يفرض صلاحية update على نفس الحملة
     * (route('campaign')) بصرف النظر عن middleware المسار هنا.
     */
    public function storeMedia(
        StorePopupCampaignMediaRequest $request,
        PopupCampaign $campaign,
        StorePopupCampaignMediaController $upload,
    ): JsonResponse {
        return $upload($request, $campaign);
    }

    public function publish(Request $request, PopupCampaign $campaign, TransitionPopupCampaignAction $transition): RedirectResponse
    {
        return $this->transition($request, $campaign, $transition, 'publish', PopupCampaignStatus::Published);
    }

    public function pause(Request $request, PopupCampaign $campaign, TransitionPopupCampaignAction $transition): RedirectResponse
    {
        return $this->transition($request, $campaign, $transition, 'pause', PopupCampaignStatus::Paused);
    }

    public function archive(Request $request, PopupCampaign $campaign, TransitionPopupCampaignAction $transition): RedirectResponse
    {
        return $this->transition($request, $campaign, $transition, 'archive', PopupCampaignStatus::Archived);
    }

    private function transition(
        Request $request,
        PopupCampaign $campaign,
        TransitionPopupCampaignAction $transition,
        string $ability,
        PopupCampaignStatus $target,
    ): RedirectResponse {
        $this->authorizeCampaign($request, $ability, $campaign);

        $input = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $user = $request->user();

        try {
            $transition->execute(
                campaign: $campaign,
                target: $target,
                actorId: (string) $user?->getAuthIdentifier(),
                reason: (string) $input['reason'],
            );
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['campaign' => $error->getMessage()]);
        }

        return back()->with('success', (string) __('console_popup_messages.messages.status_changed'));
    }

    private function authorizeCampaign(Request $request, string $ability, PopupCampaign $campaign): void
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($user->can($ability, $campaign), 403);
    }
}
