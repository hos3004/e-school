<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;
use Modules\Reporting\Presentation\Http\Requests\SaveProgramDigestRecipientSettingRequest;

final class SaveProgramDigestRecipientSettingController extends Controller
{
    public function __invoke(SaveProgramDigestRecipientSettingRequest $request, ProgramDigestRecipientSettings $settings): JsonResponse
    {
        $data = $request->validated();
        $organizationId = (string) data_get($request->user(), 'organization_id');

        $saved = $settings->saveGlobal(
            $organizationId,
            (string) $data['recipient_type'],
            $data['recipient_user_id'] ?? null,
            $data['custom_email'] ?? null,
            (string) $request->user()?->getAuthIdentifier(),
            (string) $data['reason'],
            $data['version'] ?? null,
        );

        return response()->json([
            'id' => $saved->id,
            'recipient_type' => $saved->recipientType,
            'recipient_user_id' => $saved->recipientUserId,
            'custom_email' => $saved->customEmail,
            'version' => $saved->version,
        ]);
    }
}
