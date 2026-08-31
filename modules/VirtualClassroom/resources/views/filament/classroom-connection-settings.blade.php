<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach (['total', 'pending', 'provisioned', 'running', 'ended', 'failed'] as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('virtualclassroom::settings.metric_'.$metric) }}</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $operationsSummary[$metric] ?? 0 }}</p>
            </div>
        @endforeach
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <x-filament::section class="xl:col-span-2" icon="heroicon-o-video-camera" :heading="__('virtualclassroom::settings.connection_heading')" :description="__('virtualclassroom::settings.connection_description')">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><p class="text-sm text-gray-500 dark:text-gray-400">{{ __('virtualclassroom::settings.provider') }}</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $provider }}</p></div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><p class="text-sm text-gray-500 dark:text-gray-400">{{ __('virtualclassroom::settings.base_url') }}</p><p class="mt-1 break-all font-semibold text-gray-950 dark:text-white">{{ $baseUrl ?? __('virtualclassroom::settings.not_configured') }}</p></div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><p class="text-sm text-gray-500 dark:text-gray-400">{{ __('virtualclassroom::settings.api_secret') }}</p><p @class(['mt-1 font-semibold', 'text-success-600 dark:text-success-400' => $secretConfigured, 'text-danger-600 dark:text-danger-400' => ! $secretConfigured])>{{ $secretConfigured ? __('virtualclassroom::settings.configured') : __('virtualclassroom::settings.not_configured') }}</p></div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><p class="text-sm text-gray-500 dark:text-gray-400">{{ __('virtualclassroom::settings.webhook_secret') }}</p><p @class(['mt-1 font-semibold', 'text-success-600 dark:text-success-400' => $webhookSecretConfigured || $secretConfigured, 'text-danger-600 dark:text-danger-400' => ! $webhookSecretConfigured && ! $secretConfigured])>{{ $webhookSecretConfigured || $secretConfigured ? __('virtualclassroom::settings.configured') : __('virtualclassroom::settings.not_configured') }}</p></div>
            </div>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="runConnectionCheck" icon="heroicon-o-arrow-path">{{ __('virtualclassroom::settings.run_check') }}</x-filament::button>
                @if ($healthStatus !== null)<x-filament::badge :color="$healthStatus === 'healthy' ? 'success' : 'danger'">{{ __('virtualclassroom::settings.health_'.$healthStatus) }}</x-filament::badge>@endif
            </div>
            @if ($healthMessage)<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $healthMessage }}</p>@endif
        </x-filament::section>
        <x-filament::section icon="heroicon-o-shield-check" :heading="__('virtualclassroom::settings.webhook_heading')" :description="__('virtualclassroom::settings.webhook_description')">
            <p class="break-all rounded-lg bg-gray-50 p-3 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ $webhookCallbackUrl ?? __('virtualclassroom::settings.callback_missing') }}</p>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">{{ $supportsWebhookRegistration ? __('virtualclassroom::settings.webhook_supported') : __('virtualclassroom::settings.webhook_unsupported') }}</p>
            @if ($webhookRegistered !== null)<p @class(['mt-3 text-sm font-semibold', 'text-success-600 dark:text-success-400' => $webhookRegistered, 'text-danger-600 dark:text-danger-400' => ! $webhookRegistered])>{{ $webhookRegistered ? __('virtualclassroom::settings.webhook_registered') : __('virtualclassroom::settings.webhook_not_registered') }}</p>@endif
        </x-filament::section>
    </div>
    <x-filament::section icon="heroicon-o-list-bullet" :heading="__('virtualclassroom::settings.preparation_heading')" :description="__('virtualclassroom::settings.preparation_description')">
        <ol class="list-decimal space-y-3 ps-5 text-sm leading-6 text-gray-700 dark:text-gray-200">
            <li>{{ __('virtualclassroom::settings.preparation_url') }}</li><li>{{ __('virtualclassroom::settings.preparation_secret') }}</li><li>{{ __('virtualclassroom::settings.preparation_webhook') }}</li><li>{{ __('virtualclassroom::settings.preparation_recording') }}</li>
        </ol>
        <p class="mt-5 text-sm text-gray-600 dark:text-gray-300">{{ __('virtualclassroom::settings.env_hint') }}</p>
    </x-filament::section>
</x-filament-panels::page>
