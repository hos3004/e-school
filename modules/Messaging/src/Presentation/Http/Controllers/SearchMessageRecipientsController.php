<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Presentation\Http\Requests\SearchMessageRecipientsRequest;

final class SearchMessageRecipientsController extends Controller
{
    public function __invoke(
        SearchMessageRecipientsRequest $request,
        UserAccountDirectory $users,
        ClassAudienceQueries $audience,
    ): JsonResponse {
        $actorId = (string) $request->user()->getAuthIdentifier();
        $organizationId = (string) $request->user()->organization_id;

        // مشرف أو أدمن (message.moderate) يبحث بلا قيد؛ غير ذلك يُقيَّد
        // بعلاقته الفعلية (المعلم يرى طلابه فقط) — انظر reachableRecipientUserIds.
        $allowedIds = $request->user()->can('message.moderate')
            ? null
            : $audience->reachableRecipientUserIds($organizationId, $actorId);

        // القيد يُطبَّق بعد البحث لا داخله؛ نوسّع حد الجلب مؤقتًا كي لا يزاحم
        // تطابق خارج قائمة المسموح به أول 20 نتيجة المطلوبة فعلًا.
        $searchLimit = $allowedIds === null ? 20 : 100;

        $recipients = array_values(array_filter(
            $users->search($organizationId, $request->term(), $searchLimit),
            static fn (UserAccountData $user): bool => $user->id !== $actorId
                && $user->status === 'active'
                && ($allowedIds === null || in_array($user->id, $allowedIds, true)),
        ));

        $recipients = array_slice($recipients, 0, 20);

        return response()->json([
            'data' => array_map(
                static fn (UserAccountData $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                ],
                $recipients,
            ),
        ]);
    }
}
