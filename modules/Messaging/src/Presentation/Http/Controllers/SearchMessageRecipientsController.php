<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Messaging\Presentation\Http\Requests\SearchMessageRecipientsRequest;

final class SearchMessageRecipientsController extends Controller
{
    public function __invoke(
        SearchMessageRecipientsRequest $request,
        UserAccountDirectory $users,
    ): JsonResponse {
        $actorId = (string) $request->user()->getAuthIdentifier();
        $organizationId = (string) $request->user()->organization_id;

        $recipients = array_values(array_filter(
            $users->search($organizationId, $request->term(), 20),
            static fn (UserAccountData $user): bool => $user->id !== $actorId && $user->status === 'active',
        ));

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
