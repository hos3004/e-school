<x-filament-panels::page>
    <div class="space-y-6" wire:loading.class="opacity-60">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('reporting::operational.description') }}
        </p>

        @if ($this->getReportError())
            <div role="alert" class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-200">
                {{ $this->getReportError() }}
            </div>
        @endif

        @if ($this->isReportLimitExceeded())
            <div role="status" class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-800 dark:bg-warning-950 dark:text-warning-200">
                {{ __('reporting::operational.limit_exceeded') }}
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-live="polite">
            @foreach ($this->getSummaryCards() as $card)
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $card['value'] }}</p>
                </section>
            @endforeach
        </div>

        <div class="fi-ta-ctn overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-900">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
