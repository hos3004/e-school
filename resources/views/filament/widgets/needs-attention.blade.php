<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-inbox-stack"
        icon-color="primary"
        :heading="$title"
        :description="$subtitle"
    >
        @if (empty($items))
            <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="h-10 w-10 text-success-500"
                />
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $empty }}
                </p>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <a
                        aria-label="{{ $item['label'] }}"
                        class="block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                        href="{{ $item['href'] }}"
                    >
                        <div
                            @class([
                                'flex items-center gap-3 rounded-xl border p-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/40',
                                'border-danger-200 bg-danger-50 dark:border-danger-800/40 dark:bg-danger-950/30' => $item['color'] === 'danger',
                                'border-warning-200 bg-warning-50 dark:border-warning-800/40 dark:bg-warning-950/30' => $item['color'] === 'warning',
                                'border-info-200 bg-info-50 dark:border-info-800/40 dark:bg-info-950/30' => $item['color'] === 'info',
                            ])
                        >
                            <x-filament::icon
                                :icon="$item['icon']"
                                @class([
                                    'h-6 w-6 shrink-0',
                                    'text-danger-600 dark:text-danger-400' => $item['color'] === 'danger',
                                    'text-warning-600 dark:text-warning-400' => $item['color'] === 'warning',
                                    'text-info-600 dark:text-info-400' => $item['color'] === 'info',
                                ])
                            />

                            <div class="min-w-0">
                                <div class="text-xl font-bold leading-none text-gray-950 dark:text-white">
                                    {{ number_format($item['count']) }}
                                </div>
                                <div class="mt-1 truncate text-sm text-gray-600 dark:text-gray-300">
                                    {{ $item['label'] }}
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
