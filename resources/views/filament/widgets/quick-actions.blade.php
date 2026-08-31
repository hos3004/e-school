<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-bolt"
        icon-color="primary"
        :heading="$title"
    >
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($actions as $action)
                <a
                    aria-label="{{ $action['label'] }}"
                    class="group flex min-h-16 items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow-sm transition-[border-color,background-color,box-shadow,scale] duration-150 ease-[cubic-bezier(0.2,0,0,1)] hover:border-primary-200 hover:bg-primary-50/60 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 active:scale-[0.96] dark:border-white/10 dark:bg-white/5 dark:text-gray-100 dark:hover:border-primary-800/60 dark:hover:bg-primary-950/30 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900"
                    href="{{ $action['href'] }}"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-100 transition-colors duration-150 group-hover:bg-primary-100 dark:bg-primary-950/50 dark:text-primary-400 dark:ring-primary-900 dark:group-hover:bg-primary-950/80">
                        <x-filament::icon
                            :icon="$action['icon']"
                            class="size-5"
                        />
                    </span>

                    <span class="min-w-0 flex-1 leading-6">
                        {{ $action['label'] }}
                    </span>

                    <x-filament::icon
                        icon="heroicon-m-arrow-right"
                        class="size-4 shrink-0 text-gray-400 transition-[color,translate] duration-150 ease-[cubic-bezier(0.2,0,0,1)] group-hover:translate-x-0.5 group-hover:text-primary-700 rtl:rotate-180 rtl:group-hover:-translate-x-0.5 dark:text-gray-500 dark:group-hover:text-primary-400"
                    />
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
