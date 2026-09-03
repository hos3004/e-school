<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Shared\Support\Locales;

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
            /*
             * مفاتيح الميزات التي تحكم ظهور عناصر تنقّل. الواجهة تخفي الرابط،
             * والمسار نفسه غير مسجَّل حين تكون الميزة مطفأة — فلا يعتمد المنع
             * على الإخفاء وحده.
             */
            'features' => [
                'payroll' => (bool) config('features.payroll'),
            ],
            'locale' => $locale,
            'supportedLocales' => Locales::supported(),
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

        $translations = Arr::dot($lines);
        $marketing = Lang::get('marketing', [], $locale);

        if (!is_array($marketing)) {
            $marketing = Lang::get('marketing', [], (string) config('app.fallback_locale', 'en'));
        }

        if (is_array($marketing)) {
            $translations = [
                ...$translations,
                ...Arr::dot(['marketing' => $marketing]),
            ];
        }

        return array_map(
            static fn (mixed $value): string => (string) $value,
            $translations,
        );
    }
}
