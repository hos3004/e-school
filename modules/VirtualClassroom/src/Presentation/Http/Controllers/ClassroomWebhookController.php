<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Presentation\Http\Controllers;

use BackedEnum;
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
            $event = $this->action->execute($request);
            $type = $event?->event_type;

            /*
             * أثر لكل تسليم: بدونه لا يُفرَّق بين «المزوّد لم يرسل» و«أرسل
             * وتجاهلناه»، وهو ما أخفى توقف تسجيل الحضور.
             */
            Log::info('virtualclassroom.webhook_received', [
                'recorded' => $event !== null,
                'event_type' => $type instanceof BackedEnum ? $type->value : $type,
                'duplicate' => $event !== null && !$event->wasRecentlyCreated,
            ]);
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
