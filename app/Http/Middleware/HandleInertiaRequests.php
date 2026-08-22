<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;

final class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly AccessControlQuerier $accessControl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => (string) $user->getAuthIdentifier(),
                    'name' => (string) data_get($user, 'name', ''),
                    'email' => (string) data_get($user, 'email', ''),
                    'locale' => (string) data_get($user, 'locale', $locale),
                    'roles' => array_map(
                        static fn ($role): string => $role->name,
                        $this->accessControl->rolesForModel(
                            method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class,
                            (string) $user->getAuthIdentifier(),
                        ),
                    ),
                ],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'locale' => $locale,
            'direction' => in_array($locale, (array) config('app.rtl_locales', ['ar']), true)
                ? 'rtl'
                : 'ltr',
            'translations' => $this->translations($locale),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $lines = Lang::get('portal', [], $locale);

        if (!is_array($lines)) {
            $lines = Lang::get('portal', [], (string) config('app.fallback_locale', 'en'));
        }

        if (!is_array($lines)) {
            return [];
        }

        return array_map(
            static fn (mixed $value): string => (string) $value,
            Arr::dot($lines),
        );
    }
}
