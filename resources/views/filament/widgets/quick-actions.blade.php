<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-bolt"
        icon-color="primary"
        :heading="$title"
    >
        <div class="flex flex-wrap gap-3">
            @foreach ($actions as $action)
                <a
                    aria-label="{{ $action['label'] }}"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-950 transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-gray-700 dark:bg-gray-900/40 dark:text-white dark:hover:bg-gray-900/70"
                    href="{{ $action['href'] }}"
                >
                    <x-filament::icon
                        :icon="$action['icon']"
                        class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400"
                    />
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
