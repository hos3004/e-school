<?php

declare(strict_types=1);

namespace App\Http\Controllers\SupportBot;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportBot\AskSupportBotRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SupportBot\Application\Actions\AskSupportBotAction;
use Modules\SupportBot\Application\Services\AccessResolver;
use Modules\SupportBot\Application\Services\ConversationStore;
use Modules\SupportBot\Domain\Enums\MessageRole;
use Modules\SupportBot\Domain\Models\BotMessage;
use Throwable;

/**
 * نقطة الاتصال بالبوت من الواجهة.
 *
 * متزامنة لا مؤجَّلة عن عمد: جدول المهام في هذا المشروع يشرف على طابورين فقط
 * بعاملين اثنين يشاركهما إرسال الإشعارات، وجدول failed_jobs غير موجود في
 * الإنتاج مع tries=1 — أي أن وظيفة تفشل تختفي بلا أثر. لا يصلح ذلك لشيء ينتظره
 * مستخدم أمام الشاشة، فالانتظار محكوم بمهلة صريحة في البوابة بدل طابور.
 *
 * لا يصعد استثناء إلى المستخدم: أسوأ ما يراه اعتذار مهذّب وإحالة إلى الواتساب.
 */
final class SupportBotChatController extends Controller
{
    public function ask(AskSupportBotRequest $request, AskSupportBotAction $action): JsonResponse
    {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');

        abort_if($organizationId === '', 403);

        try {
            $reply = $action->execute(
                $organizationId,
                (string) $user->getAuthIdentifier(),
                $user->getMorphClass(),
                $request->message(),
                app()->getLocale(),
            );
        } catch (Throwable) {
            /*
             * إعداد مزوّد مكسور أو عطل غير متوقع. لا نكشف التفصيل للمستخدم ولا
             * نكتب محتوى رسالته في أي سجل — سجلّ الموقع يعمل على debug ويكتب على
             * القرص، فطباعة رسالة طالب هناك تسريب دائم.
             */
            return response()->json([
                'reply' => (string) __('supportbot::replies.last_resort'),
                'conversationId' => '',
                'withheld' => true,
            ], 200);
        }

        return response()->json([
            'reply' => $reply->body,
            'conversationId' => $reply->conversationId,
            'topic' => $reply->topic?->value,
            'withheld' => $reply->wasWithheld(),
        ]);
    }

    /**
     * سجلّ الجلسة الجارية — يستعيده الودجت بعد تنقّل أو إعادة تحميل.
     *
     * الجلسة المنتهية بالخمول لا تُستأنف: يبدأ المستخدم محادثة نظيفة، وهو ما
     * يعنيه «تتصفّر الذاكرة بانتهاء الجلسة».
     */
    public function history(
        Request $request,
        AccessResolver $access,
        ConversationStore $conversations,
    ): JsonResponse {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');

        abort_if($organizationId === '', 403);

        $decision = $access->resolve(
            $organizationId,
            (string) $user->getAuthIdentifier(),
            $user->getMorphClass(),
        );

        if (!$decision->allowed || $decision->audience === null) {
            return response()->json(['available' => false, 'messages' => []]);
        }

        $conversation = $conversations->current(
            $organizationId,
            (string) $user->getAuthIdentifier(),
            $decision->audience,
            app()->getLocale(),
        );

        $messages = BotMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (BotMessage $message): array => [
                'role' => $message->role === MessageRole::Bot ? 'bot' : 'user',
                'body' => $message->body,
                'at' => $message->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'available' => true,
            'conversationId' => (string) $conversation->id,
            'messages' => $messages,
        ]);
    }
}
