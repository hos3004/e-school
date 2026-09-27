<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Push;

use Illuminate\Support\Facades\Http;
use Modules\Notifications\Domain\Contracts\FirebaseAccessTokenProvider;
use Throwable;

/**
 * إرسال رسالة واحدة عبر Firebase Cloud Messaging HTTP v1 API لرمز جهاز واحد.
 */
final readonly class FcmSender
{
    public function __construct(private FirebaseAccessTokenProvider $tokenProvider) {}

    /**
     * @param array<string, string> $data
     */
    public function send(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
    ): FcmSendResult {
        try {
            $accessToken = $this->tokenProvider->token();
            $projectId = $this->tokenProvider->projectId();
        } catch (Throwable $error) {
            return FcmSendResult::failed($error->getMessage(), retryable: true);
        }

        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => ['priority' => 'high'],
                ],
            ]);

        if ($response->successful()) {
            return FcmSendResult::accepted();
        }

        $status = (string) $response->json('error.status', '');
        $unregistered = in_array($status, ['UNREGISTERED', 'NOT_FOUND'], true);
        $retryable = !$unregistered && ($response->status() >= 500 || $status === 'UNAVAILABLE' || $status === 'INTERNAL');

        return FcmSendResult::failed(
            (string) ($response->json('error.message') ?? 'fcm_send_failed'),
            retryable: $retryable,
            unregistered: $unregistered,
        );
    }
}
