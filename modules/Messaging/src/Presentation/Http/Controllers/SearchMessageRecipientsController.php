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

        $term = $request->term();

        $recipients = mb_strlen($term) < 2
            ? $this->defaultRecipients($organizationId, $actorId, $allowedIds, $users)
            : $this->searchRecipients($organizationId, $actorId, $allowedIds, $term, $users);

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

    /**
     * @param list<string>|null $allowedIds
     * @return list<UserAccountData>
     */
    private function searchRecipients(
        string $organizationId,
        string $actorId,
        ?array $allowedIds,
        string $term,
        UserAccountDirectory $users,
    ): array {
        // القيد يُطبَّق بعد البحث لا داخله؛ نوسّع حد الجلب مؤقتًا كي لا يزاحم
        // تطابق خارج قائمة المسموح به أول 20 نتيجة المطلوبة فعلًا.
        $searchLimit = $allowedIds === null ? 20 : 100;

        $recipients = array_values(array_filter(
            $users->search($organizationId, $term, $searchLimit),
            static fn (UserAccountData $user): bool => $user->id !== $actorId
                && $user->isActive()
                && ($allowedIds === null || in_array($user->id, $allowedIds, true)),
        ));

        return array_slice($recipients, 0, 20);
    }

    /**
     * بلا كتابة: نعرض قائمة المستلمين المتاحين مباشرة بدل إجبار المستخدم
     * يعرف الاسم المسجَّل بالظبط (طالب لا يعرف اسم معلمه الكامل في النظام
     * مثلًا). غير المقيَّد (مشرف) يفضل محتاجًا يكتب — قائمة المؤسسة كاملة
     * كبيرة جدًا لتُعرض افتراضيًا.
     *
     * @param list<string>|null $allowedIds
     * @return list<UserAccountData>
     */
    private function defaultRecipients(
        string $organizationId,
        string $actorId,
        ?array $allowedIds,
        UserAccountDirectory $users,
    ): array {
        if ($allowedIds === null || $allowedIds === []) {
            return [];
        }

        $recipients = array_values(array_filter(
            $users->findMany($organizationId, $allowedIds),
            static fn (UserAccountData $user): bool => $user->id !== $actorId && $user->isActive(),
        ));

        usort($recipients, static fn (UserAccountData $a, UserAccountData $b): int => $a->name <=> $b->name);

        return array_slice($recipients, 0, 20);
    }
}
