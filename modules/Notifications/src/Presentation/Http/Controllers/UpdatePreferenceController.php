<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Notifications\Application\Actions\UpdatePreferenceAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Presentation\Http\Requests\UpdatePreferenceRequest;

/**
 * يحدّث المستخدم تفضيله الخاص لفئة×قناة.
 */
final class UpdatePreferenceController extends Controller
{
    public function __construct(
        private readonly UpdatePreferenceAction $action,
    ) {}

    public function __invoke(UpdatePreferenceRequest $request): Response
    {
        /** @var string $userId */
        $userId = auth()->id();

        /** @var string $organizationId */
        $organizationId = auth()->user()->organization_id;

        $this->action->execute(
            organizationId: $organizationId,
            userId: $userId,
            category: (string) $request->validated('category'),
            channel: Channel::from((string) $request->validated('channel')),
            enabled: (bool) $request->validated('enabled'),
            actorId: $userId,
        );

        return response()->noContent();
    }
}
