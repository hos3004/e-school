<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Support\PageTranslations;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Shared\Support\Locales;

final class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly AccessControlQuerier $accessControl,
    ) {}

    /**
     * @return array<array-key, mixed>
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
            'console' => $request->is('manage', 'manage/*', 'learn', 'learn/*')
                ? app(ConsoleContext::class)->forRequest($request)
                : null,
            'features' => [
                'payroll' => (bool) config('features.payroll') && ($user === null || $user->can('payroll.view')),
            ],
            'locale' => $locale,
            'supportedLocales' => $request->is('manage', 'manage/*', 'learn', 'learn/*') ? ['ar'] : Locales::supported(),
            'direction' => in_array($locale, (array) config('app.rtl_locales', ['ar']), true)
                ? 'rtl'
                : 'ltr',
            new PageTranslations,
        ];
    }
}
