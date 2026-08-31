<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Providers;

use Illuminate\Auth\Events\Login;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Notifications\Application\Actions\SavePopupCampaignAction;
use Modules\Notifications\Application\Console\DispatchDueNotifications;
use Modules\Notifications\Application\Console\RetryFailedNotifications;
use Modules\Notifications\Application\Listeners\MarkPopupLoginMarker;
use Modules\Notifications\Application\Listeners\QueueConfiguredDomainEventNotification;
use Modules\Notifications\Application\Policies\NotificationCategorySettingPolicy;
use Modules\Notifications\Application\Policies\NotificationDeliveryAttemptPolicy;
use Modules\Notifications\Application\Policies\NotificationOutboxPolicy;
use Modules\Notifications\Application\Policies\NotificationPreferencePolicy;
use Modules\Notifications\Application\Policies\NotificationTemplatePolicy;
use Modules\Notifications\Application\Policies\PopupCampaignPolicy;
use Modules\Notifications\Application\Queries\EloquentPopupQueryService;
use Modules\Notifications\Application\Queries\NotificationAdministrationQueryService;
use Modules\Notifications\Application\Services\AccessControlPopupAudienceResolver;
use Modules\Notifications\Application\Services\OutboxDispatcher;
use Modules\Notifications\Application\Services\PayloadDomainEventRecipientResolver;
use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;
use Modules\Notifications\Domain\Contracts\NotificationAdministrationQueries;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Contracts\PopupAudienceResolver;
use Modules\Notifications\Domain\Contracts\PopupQueries;
use Modules\Notifications\Domain\Models\NotificationCategorySetting;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Infrastructure\Persistence\ConfiguredChannelGateway;
use Shared\Module\BaseModuleServiceProvider;

final class NotificationsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Notifications';
    }

    /**
     * ربط Domain Events بمستمعيها داخل الموديول.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        $listeners = [
            // علامة جلسة الدخول لقاعدة OncePerLogin — آمنة في الجلسة لا في المتصفح.
            Login::class => [MarkPopupLoginMarker::class],
        ];

        /** @var array<string, array<string, mixed>> $events */
        $events = (array) config('notifications.events', []);

        foreach ($events as $settings) {
            foreach ((array) ($settings['source_events'] ?? []) as $eventClass) {
                if (is_string($eventClass) && class_exists($eventClass)) {
                    $listeners[$eventClass] = [QueueConfiguredDomainEventNotification::class];
                }
            }
        }

        return $listeners;
    }

    /**
     * ربط الموارد بسياسات الصلاحيات.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            NotificationOutbox::class => NotificationOutboxPolicy::class,
            NotificationPreference::class => NotificationPreferencePolicy::class,
            NotificationDeliveryAttempt::class => NotificationDeliveryAttemptPolicy::class,
            NotificationTemplate::class => NotificationTemplatePolicy::class,
            NotificationCategorySetting::class => NotificationCategorySettingPolicy::class,
            PopupCampaign::class => PopupCampaignPolicy::class,
        ];
    }

    /**
     * ربط الـ Contracts بتنفيذاتها.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            // محرّك الإشعارات — ما تعتمده بقية الموديولات عبر العقد.
            NotificationDispatcher::class => OutboxDispatcher::class,
            DomainEventRecipientResolver::class => PayloadDomainEventRecipientResolver::class,
            NotificationAdministrationQueries::class => NotificationAdministrationQueryService::class,

            // بوابة القنوات: موجّه يقرأ تنفيذ القناة من config، وتنفيذاته
            // الحقيقية (SES · FCM · Meta) تُعلَّم في الإعداد دون استيراد عابر للحدود.
            ChannelGateway::class => ConfiguredChannelGateway::class,
            PopupQueries::class => EloquentPopupQueryService::class,
            PopupAudienceResolver::class => AccessControlPopupAudienceResolver::class,
            SavePopupCampaignAction::class => SavePopupCampaignAction::class,
        ];
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([
            DispatchDueNotifications::class,
            RetryFailedNotifications::class,
        ]);
    }
}
