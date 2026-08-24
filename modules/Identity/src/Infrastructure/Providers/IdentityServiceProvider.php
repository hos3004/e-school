<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Identity\Application\Actions\IssuePhonePasswordResetOtp;
use Modules\Identity\Application\Policies\PasswordResetTokenPolicy;
use Modules\Identity\Application\Policies\UserDevicePolicy;
use Modules\Identity\Application\Policies\UserPolicy;
use Modules\Identity\Application\Services\UserAccountProvisioningService;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\PasswordResetToken;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Infrastructure\Delivery\NullPhonePasswordResetOtpDelivery;
use Modules\Identity\Infrastructure\Persistence\EloquentUserQueryService;
use Shared\Module\BaseModuleServiceProvider;

final class IdentityServiceProvider extends BaseModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('identity-phone-reset-request', function (Request $request): Limit {
            $phone = IssuePhonePasswordResetOtp::normalizePhone((string) $request->input('phone')) ?? 'invalid';
            $key = implode('|', [
                (string) $request->ip(),
                (string) $request->input('organization_id'),
                $phone,
            ]);

            return Limit::perHour((int) config('identity.phone_password_reset.edge_requests_per_hour'))
                ->by(hash('sha256', $key));
        });

        RateLimiter::for('identity-phone-reset-verify', function (Request $request): Limit {
            $phone = IssuePhonePasswordResetOtp::normalizePhone((string) $request->input('phone')) ?? 'invalid';
            $key = implode('|', [
                (string) $request->ip(),
                (string) $request->input('organization_id'),
                $phone,
            ]);

            return Limit::perMinute((int) config('identity.phone_password_reset.edge_verifications_per_minute'))
                ->by(hash('sha256', $key));
        });
    }

    protected function moduleName(): string
    {
        return 'Identity';
    }

    /**
     * أحداث Identity تستهلكها موديولات أخرى (Audit، Notifications،
     * AccessControl). لا مستمعين داخليين حتى الآن — يُربطون هنا عند الحاجة.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            User::class => UserPolicy::class,
            UserDevice::class => UserDevicePolicy::class,
            PasswordResetToken::class => PasswordResetTokenPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            PhonePasswordResetOtpDelivery::class => NullPhonePasswordResetOtpDelivery::class,
            UserAccountProvisioner::class => UserAccountProvisioningService::class,
            UserQueryService::class => EloquentUserQueryService::class,
        ];
    }
}
