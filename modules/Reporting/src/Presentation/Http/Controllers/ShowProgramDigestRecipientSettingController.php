<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Reporting\Domain\Contracts\ProgramDigestRecipientSettings;

final class ShowProgramDigestRecipientSettingController extends Controller
{
    public function __invoke(Request $request, ProgramDigestRecipientSettings $settings): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('reporting.settings.manage'), 403);

        $organizationId = (string) data_get($request->user(), 'organization_id');
        $current = $settings->current($organizationId);

        return response()->json($current === null ? ['configured' => false] : [
            'configured' => true,
            'id' => $current->id,
            'recipient_type' => $current->recipientType,
            'recipient_user_id' => $current->recipientUserId,
            'custom_email' => $current->customEmail,
            'version' => $current->version,
        ]);
    }
}
