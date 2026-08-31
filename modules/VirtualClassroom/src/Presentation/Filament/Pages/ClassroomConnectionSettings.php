<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Presentation\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\SupportsWebhookRegistration;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Throwable;

final class ClassroomConnectionSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static ?int $navigationSort = 108;

    protected string $view = 'virtualclassroom::filament.classroom-connection-settings';

    public string $provider = '';

    public ?string $baseUrl = null;

    public ?string $webhookCallbackUrl = null;

    public bool $baseUrlConfigured = false;

    public bool $secretConfigured = false;

    public bool $webhookSecretConfigured = false;

    public bool $supportsWebhookRegistration = false;

    public ?string $healthStatus = null;

    public ?string $healthMessage = null;

    public ?bool $webhookRegistered = null;

    /** @var array<string, int> */
    public array $operationsSummary = [];

    public function mount(): void
    {
        $this->loadConfigurationState();
        $this->operationsSummary = app(ClassroomAdministrationQueries::class)
            ->summaryForOrganization((string) data_get(auth()->user(), 'organization_id', ''));
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can((string) config('virtual-classroom.health_check.alert_permission'));
    }

    public static function getNavigationGroup(): string
    {
        return __('virtualclassroom::settings.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('virtualclassroom::settings.navigation_label');
    }

    public function getTitle(): string
    {
        return __('virtualclassroom::settings.title');
    }

    public function runConnectionCheck(): void
    {
        try {
            $provider = app(VirtualClassroomProvider::class);
            $health = $provider->healthCheck();

            $this->healthStatus = $health->status->value;
            $this->healthMessage = $health->message;
            $this->webhookRegistered = null;

            if ($provider instanceof SupportsWebhookRegistration
                && $this->webhookCallbackUrl !== null
                && $this->webhookCallbackUrl !== '') {
                $this->webhookRegistered = collect($provider->registeredWebhooks())
                    ->contains(fn ($hook): bool => hash_equals($this->webhookCallbackUrl ?? '', $hook->callbackUrl));
            }

            Notification::make()
                ->title($health->status->isUsable()
                    ? __('virtualclassroom::settings.check_success')
                    : __('virtualclassroom::settings.check_failed'))
                ->body($health->message)
                ->{$health->status->isUsable() ? 'success' : 'danger'}()
                ->send();
        } catch (Throwable $exception) {
            $this->healthStatus = 'down';
            $this->healthMessage = $exception->getMessage();
            $this->webhookRegistered = null;

            Notification::make()
                ->title(__('virtualclassroom::settings.check_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function loadConfigurationState(): void
    {
        $this->provider = (string) config('virtual-classroom.default');
        $configuration = (array) config('virtual-classroom.providers.'.$this->provider, []);
        $baseUrl = $configuration['base_url'] ?? null;
        $secret = $configuration['secret'] ?? null;
        $webhookSecret = $configuration['webhook_secret'] ?? null;
        $callbackUrl = $configuration['webhook_callback_url'] ?? null;

        $this->baseUrl = is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : null;
        $this->webhookCallbackUrl = is_string($callbackUrl) && $callbackUrl !== '' ? $callbackUrl : null;
        $this->baseUrlConfigured = $this->baseUrl !== null;
        $this->secretConfigured = is_string($secret) && $secret !== '';
        $this->webhookSecretConfigured = is_string($webhookSecret) && $webhookSecret !== '';

        try {
            $this->supportsWebhookRegistration = app(VirtualClassroomProvider::class) instanceof SupportsWebhookRegistration;
        } catch (Throwable) {
            $this->supportsWebhookRegistration = false;
        }
    }
}
