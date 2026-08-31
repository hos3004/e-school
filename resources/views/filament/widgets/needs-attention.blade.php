<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-inbox-stack"
        icon-color="primary"
        :heading="$title"
        :description="$subtitle"
    >
        @if (empty($items))
            <div class="flex min-h-36 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/70 px-6 py-8 text-center dark:border-white/10 dark:bg-white/5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-success-50 text-success-600 ring-1 ring-inset ring-success-200 dark:bg-success-950/40 dark:text-success-400 dark:ring-success-800/50">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="size-6"
                    />
                </span>
                <p class="max-w-md text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $empty }}
                </p>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($items as $item)
                    <a
                        aria-label="{{ $item['label'] }}: {{ number_format($item['count']) }}"
                        class="group flex min-h-24 items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-[border-color,background-color,box-shadow,scale] duration-150 ease-[cubic-bezier(0.2,0,0,1)] hover:border-gray-300 hover:bg-gray-50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 active:scale-[0.96] dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20 dark:hover:bg-white/10 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900"
                        href="{{ $item['href'] }}"
                    >
                        <span
                            @class([
                                'flex size-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset',
                                'bg-danger-50 text-danger-600 ring-danger-200 dark:bg-danger-950/40 dark:text-danger-400 dark:ring-danger-800/50' => $item['color'] === 'danger',
                                'bg-warning-50 text-warning-700 ring-warning-200 dark:bg-warning-950/40 dark:text-warning-400 dark:ring-warning-800/50' => $item['color'] === 'warning',
                                'bg-info-50 text-info-600 ring-info-200 dark:bg-info-950/40 dark:text-info-400 dark:ring-info-800/50' => $item['color'] === 'info',
                            ])
                        >
                            <x-filament::icon
                                :icon="$item['icon']"
                                class="size-5"
                            />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-2xl font-semibold leading-none tabular-nums text-gray-950 dark:text-white">
                                {{ number_format($item['count']) }}
                            </span>
                            <span class="mt-2 block truncate text-sm font-medium text-gray-600 transition-colors duration-150 group-hover:text-gray-800 dark:text-gray-300 dark:group-hover:text-white">
                                {{ $item['label'] }}
                            </span>
                        </span>

                        <x-filament::icon
                            icon="heroicon-m-chevron-right"
                            class="size-4 shrink-0 text-gray-400 transition-[color,translate] duration-150 ease-[cubic-bezier(0.2,0,0,1)] group-hover:translate-x-0.5 group-hover:text-primary-600 rtl:rotate-180 rtl:group-hover:-translate-x-0.5 dark:text-gray-500 dark:group-hover:text-primary-400"
                        />
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
