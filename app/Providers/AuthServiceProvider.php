<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginViewResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\SimpleViewResponse;
use Modules\Identity\Application\Actions\RecordUserLogin;
use Modules\Identity\Domain\Models\User;

/**
 * يربط Fortify بواجهات Inertia وبمصادقة المعرّف الموحد
 * (اسم المستخدم أو البريد أو الهاتف) ويطبّق حدود المحاولات.
 *
 * لا فحص على اسم دور في أي مكان — الحالة تُفحص عبر UserStatus فقط.
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // حقلا username/email يُقرآن من config/fortify.php ('login' و'email').
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->bindViews();
        $this->authenticateByIdentifier();
        $this->recordSuccessfulLogins();
        $this->limitLoginAttempts();
    }

    private function bindViews(): void
    {
        app()->singleton(LoginViewResponse::class, static fn (): LoginViewResponse => new SimpleViewResponse(
            static fn (Request $request) => Inertia::render('Auth/Login', [
                'action' => route('login'),
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'status' => $request->session()->get('status'),
                ],
            ]),
        ));

        app()->singleton(RequestPasswordResetLinkViewResponse::class, static fn (): RequestPasswordResetLinkViewResponse => new SimpleViewResponse(
            static fn (Request $request) => Inertia::render('Auth/ForgotPassword', [
                'status' => $request->session()->get('status'),
            ]),
        ));

        app()->singleton(ResetPasswordViewResponse::class, static fn (): ResetPasswordViewResponse => new SimpleViewResponse(
            static fn (Request $request) => Inertia::render('Auth/ResetPassword', [
                'token' => (string) $request->route('token', ''),
                'email' => (string) $request->input('email', ''),
            ]),
        ));

        app()->singleton(TwoFactorChallengeViewResponse::class, static fn (): TwoFactorChallengeViewResponse => new SimpleViewResponse(
            static fn () => Inertia::render('Auth/TwoFactorChallenge'),
        ));

        /*
         * منع تعداد الحسابات: فشل طلب الاستعادة يعيد نفس رسالة الحالة
         * المحايدة في مفتاح status — لا أخطاء تحقق تكشف وجود البريد.
         */
        app()->singleton(
            FailedPasswordResetLinkRequestResponse::class,
            static fn (): FailedPasswordResetLinkRequestResponse => new class implements FailedPasswordResetLinkRequestResponse
            {
                public function toResponse($request): mixed
                {
                    return back()->with('status', (string) trans('passwords.sent'));
                }
            },
        );
    }

    private function authenticateByIdentifier(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identifier = trim((string) $request->input(Fortify::username(), ''));
            $password = (string) $request->input('password', '');

            if ($identifier === '' || $password === '') {
                return null;
            }

            $user = $this->findByIdentifier($identifier);

            if ($user === null || !$user->canLogIn()) {
                return null;
            }

            /** @var string $hash */
            $hash = (string) $user->getAuthPassword();

            return Hash::check($password, $hash) ? $user : null;
        });
    }

    private function findByIdentifier(string $identifier): ?User
    {
        $isPhoneNumber = preg_match('/^\+?[0-9]{7,15}$/', $identifier) === 1;

        /** @var User|null */
        return User::query()
            ->where(static function ($query) use ($identifier, $isPhoneNumber): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);

                if ($isPhoneNumber) {
                    $query->orWhere(function ($phoneQuery) use ($identifier): void {
                        $digits = ltrim($identifier, '+');

                        $phoneQuery->where(function ($inner) use ($identifier, $digits): void {
                            $inner->where('phone', $identifier)
                                ->orWhere('phone', '+'.$digits);
                        });
                    });
                }
            })
            ->first();
    }

    private function recordSuccessfulLogins(): void
    {
        Event::listen(Login::class, static function (Login $event): void {
            $user = $event->user;

            if (!$user instanceof User || !$user->canLogIn()) {
                return;
            }

            app(RecordUserLogin::class)->execute(
                $user,
                request()->ip(),
                request()->userAgent(),
            );
        });
    }

    private function limitLoginAttempts(): void
    {
        RateLimiter::for('login', static function (Request $request): Limit {
            $identifier = trim((string) $request->input(Fortify::username(), ''));

            // ٥ محاولات لكل ١٥ دقيقة لكل (معرّف + IP) وفق docs/15-security-model.md.
            return Limit::perMinutes(15, 5)->by($identifier.'|'.$request->ip());
        });
    }
}
