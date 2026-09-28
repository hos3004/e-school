<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\PopupMessageData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Notifications\Domain\Models\PopupCampaign;

/**
 * صفحة الرسائل المنبثقة (Pop Messages) في /manage.
 *
 * هذا مدخل ثانٍ لنفس نظام الحملات الذي تديره لوحة Filament القديمة
 * (PopupCampaignResource) — نفس Action للحفظ ونفس Action للانتقال ونفس
 * الصلاحيات popup_campaign.*، فلا يفترق السلوك بين اللوحتين.
 */
final class PopupMessageController extends Controller
{
    public function __construct(
        private readonly PopupMessageData $data,
        private readonly ConsoleContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $organizationId = (string) $user->getAttribute('organization_id');

        return Inertia::render('Console/PopupMessages', [
            ...$this->data->forOrganization($organizationId),
            'abilities' => [
                'create' => (bool) $user->can('create', PopupCampaign::class),
                // update/publish/pause/archive تُفحص لكل حملة عبر can_transition_to
                // وeditable في summary()، لكن الأزرار تبقى مخفية كليًا إن لم يملك
                // المستخدم الصلاحية الأساسية إطلاقًا.
                'update' => (bool) $user->can('popup_campaign.update'),
                'publish' => (bool) $user->can('popup_campaign.publish'),
                'pause' => (bool) $user->can('popup_campaign.pause'),
                'archive' => (bool) $user->can('popup_campaign.archive'),
                'viewAnalytics' => (bool) $user->can('popup_campaign.view_analytics'),
            ],
            'urls' => [
                'store' => route('console.popup-messages.store'),
            ],
            'displayTimezone' => (string) $this->context->forRequest($request)['timezone'],
        ]);
    }
}
