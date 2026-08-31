<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\VirtualClassroom\Application\Actions\HandleClassroomWebhookAction;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;

/** نقطة استقبال أحداث المزوّد؛ مصادقتها بتوقيع BBB لا بمستخدم التطبيق. */
final class ClassroomWebhookController extends Controller
{
    public function __construct(
        private readonly HandleClassroomWebhookAction $action,
    ) {}

    public function __invoke(Request $request): Response
    {
        try {
            $this->action->execute($request);
        } catch (ClassroomProviderException $exception) {
            if ($exception->reason !== 'invalid_webhook_signature') {
                throw $exception;
            }

            Log::warning('virtualclassroom.webhook_signature_rejected', [
                'ip' => $request->ip(),
            ]);

            return response()->noContent(Response::HTTP_UNAUTHORIZED);
        }

        // BBB يعيد الإرسال عند أي استجابة ليست 2xx، حتى للأحداث غير المهمة.
        return response()->noContent();
    }
}
