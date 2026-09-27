<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

/**
 * توكن وصول OAuth2 صالح لإرسال FCM عبر Firebase HTTP v1 API.
 */
interface FirebaseAccessTokenProvider
{
    public function token(): string;

    public function projectId(): string;
}
