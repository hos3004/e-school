<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Push;

use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Modules\Notifications\Domain\Contracts\FirebaseAccessTokenProvider;
use RuntimeException;

/**
 * توكن OAuth2 لحساب خدمة Firebase — عبر تبادل JWT موقّع بالمفتاح الخاص
 * (RFC 7523)، مخبّأ حتى قبل انتهائه بدقيقة لتفادي تبادل جديد كل إرسال.
 *
 * ملف الاعتماد لا يُقرأ إلا وقت الحاجة الفعلية (لا في boot)، ومساره خارج
 * الشيفرة تمامًا (env)، فغياب الملف في بيئة الاختبار لا يكسر شيئًا غير
 * قناة push نفسها.
 */
final class ServiceAccountAccessTokenProvider implements FirebaseAccessTokenProvider
{
    private const CACHE_KEY = 'notifications.fcm.access_token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_TTL_SECONDS = 3600;

    /** @var array<string, mixed>|null */
    private ?array $credentialsCache = null;

    public function __construct(private readonly CacheRepository $cache) {}

    public function token(): string
    {
        /** @var string $token */
        $token = $this->cache->remember(
            self::CACHE_KEY,
            self::TOKEN_TTL_SECONDS - 300,
            function (): string {
                $credentials = $this->credentials();
                $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');
                $now = time();

                $assertion = JWT::encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + self::TOKEN_TTL_SECONDS,
                ], (string) $credentials['private_key'], 'RS256');

                $response = Http::asForm()->timeout(10)->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

                if (!$response->successful() || !is_string($response->json('access_token'))) {
                    throw new RuntimeException(
                        'Firebase OAuth2 token exchange failed: '.$response->body(),
                    );
                }

                return (string) $response->json('access_token');
            },
        );

        return $token;
    }

    public function projectId(): string
    {
        return (string) ($this->credentials()['project_id'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        if ($this->credentialsCache !== null) {
            return $this->credentialsCache;
        }

        $path = (string) config('notifications.channels.push.credentials_path');

        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Firebase service-account credentials file not configured.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded) || !isset($decoded['private_key'], $decoded['client_email'], $decoded['project_id'])) {
            throw new RuntimeException('Firebase service-account credentials file is invalid.');
        }

        return $this->credentialsCache = $decoded;
    }
}
