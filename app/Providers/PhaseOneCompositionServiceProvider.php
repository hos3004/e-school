<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\AccessControl\IdentityRoleAssignmentTargetScope;
use App\Infrastructure\Assignments\DatabaseAssignmentAudienceQueries;
use App\Infrastructure\Identity\WhatsAppPhonePasswordResetOtpDeliveryAdapter;
use App\Infrastructure\Messaging\DatabaseClassAudienceQueries;
use App\Infrastructure\Notifications\PhaseOneDomainEventRecipientResolver;
use Illuminate\Support\ServiceProvider;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentTargetScope;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;

/**
 * Cross-module adapters live at the application composition root and are
 * registered after ModuleServiceProvider so module-local safe defaults remain
 * usable in isolation while the platform receives the operational adapters.
 */
final class PhaseOneCompositionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClassAudienceQueries::class, DatabaseClassAudienceQueries::class);
        $this->app->bind(AssignmentAudienceQueries::class, DatabaseAssignmentAudienceQueries::class);
        $this->app->bind(DomainEventRecipientResolver::class, PhaseOneDomainEventRecipientResolver::class);
        $this->app->bind(RoleAssignmentTargetScope::class, IdentityRoleAssignmentTargetScope::class);
        $this->app->bind(
            PhonePasswordResetOtpDelivery::class,
            WhatsAppPhonePasswordResetOtpDeliveryAdapter::class,
        );
    }
}
