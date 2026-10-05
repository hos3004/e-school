<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Push;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\UserPushGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;

/**
 * يمرّر نفس محتوى إشعار داخل التطبيق (in_app) كإشعار فعلي عبر FCM لأجهزة
 * المستلم المسجّلة، بدل اعتماد push كقناة مُقولَبة مستقلة.
 *
 * السبب: صف in_app في الصندوق الصادر يحمل بالفعل العنوان والنص المُترجَمين
 * فعليًا من القالب (renderIfAvailable بقناة in_app). قناة push منفصلة بلا
 * قوالب push خاصة بها كانت سترجع محتوى خامًا غير مترجَم — انظر
 * android-app-project-state.md لقرار المالك بتفضيل نفس محتوى in_app بدل
 * كتابة قوالب push جديدة. لذلك هذا الصنف يُستدعى من InAppChannelGateway
 * نفسها، لا من config('notifications.channels.push.gateway') — push ليست
 * قناة مُهيّأة مستقلة في هذا التصميم.
 *
 * فشل هذا الصنف يجب ألا يُفشل أبدًا كتابة صف in_app نفسه؛ المستدعي
 * (InAppChannelGateway) يبتلع أي استثناء.
 */
final readonly class PushMirrorDispatcher
{
    public function __construct(
        private FcmSender $sender,
        private UserPushGateway $recipients,
    ) {}

    public function dispatch(GatewayMessage $message): void
    {
        if (!(bool) config('notifications.channels.push.enabled')) {
            return;
        }

        $body = $this->resolveBody($message);
        if ($body === '') {
            return;
        }

        $devices = $this->recipients->activeDevices($message->organizationId, $message->recipientId);

        if ($devices === []) {
            return;
        }

        $title = $this->resolveTitle($message);
        $targetUrl = $this->resolveTargetUrl($message);
        $data = $targetUrl === null ? [] : ['target_url' => $targetUrl];

        foreach ($devices as $device) {
            $result = $this->sender->send(
                deviceToken: $device->token,
                title: $title,
                body: $body,
                data: $data,
            );

            if ($result->success) {
                Log::info('push.mirror.sent', [
                    'device_id' => $device->id,
                    'user_id' => $message->recipientId,
                    'event_name' => $message->eventName,
                ]);
            } else {
                Log::warning('push.mirror.failed', [
                    'device_id' => $device->id,
                    'user_id' => $message->recipientId,
                    'event_name' => $message->eventName,
                    'error' => $result->error,
                    'retryable' => $result->retryable,
                    'unregistered' => $result->unregistered,
                ]);
            }

            if (!$result->success && $result->unregistered) {
                $this->recipients->revokeUnregisteredDevice($message->organizationId, $message->recipientId, $device);
            }
        }
    }

    private function resolveTitle(GatewayMessage $message): string
    {
        if (is_array($message->subject) && $message->subject !== []) {
            $subject = $message->subject;
            $value = $subject[$message->locale] ?? reset($subject);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return (string) config('app.name', 'Tele Course Academy');
    }

    private function resolveBody(GatewayMessage $message): string
    {
        if ($message->body === []) {
            return '';
        }

        $body = $message->body;
        $value = $body[$message->locale] ?? reset($body);

        return is_string($value) ? $value : '';
    }

    /**
     * نفس منطق NotificationDeepLinkResolver بالضبط، لكن مبني على GatewayMessage
     * (لا يحمل نموذج NotificationOutbox عمدًا — عزل الحدود بين البوابات
     * والنموذج) بدل Request، فيُشتق المستخدم من recipientId مباشرة و
     * فحص الصلاحية يمر عبر عقد Identity بلا حاجة لطلب HTTP.
     */
    private function resolveTargetUrl(GatewayMessage $message): ?string
    {
        $configured = $message->payload['target_url'] ?? null;
        if (is_string($configured) && str_starts_with($configured, '/') && !str_starts_with($configured, '//')) {
            return $configured;
        }

        $can = fn (string $permission): bool => $this->recipients->can(
            $message->organizationId, $message->recipientId, $permission,
        );

        $sessionId = $message->payload['session_id'] ?? null;
        if (is_string($sessionId) && Str::isUlid($sessionId)) {

            if ($can('attendance.record') === true || $can('session_report.create') === true) {
                return "/teacher/sessions/{$sessionId}";
            }

            if ($can('session.view') === true) {
                return "/student/sessions/{$sessionId}";
            }
        }

        if (str_starts_with($message->eventName, 'assignment.') || $message->eventName === 'submission.graded') {

            return $can('assignment.submit') === true ? '/student/assignments' : null;
        }

        if (str_starts_with($message->eventName, 'registration.') || str_starts_with($message->eventName, 'discipline.')) {

            return $can('enrollment.view') === true ? '/student/programs' : null;
        }

        return null;
    }
}
