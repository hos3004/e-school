<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-calendar-days"
        icon-color="info"
        :heading="$title"
        :description="$subtitle"
    >
        @if (count($rows) === 0)
            <div class="flex min-h-36 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/70 px-6 py-8 text-center dark:border-white/10 dark:bg-white/5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-info-50 text-info-600 ring-1 ring-inset ring-info-200 dark:bg-info-950/40 dark:text-info-400 dark:ring-info-800/50">
                    <x-filament::icon
                        icon="heroicon-o-calendar"
                        class="size-6"
                    />
                </span>
                <p class="max-w-md text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $empty }}
                </p>
            </div>
        @else
            <div class="fi-ta-overflow-x-auto -mx-1 overflow-x-auto px-1 pb-1">
                <table class="fi-ta-table min-w-[42rem] divide-y divide-gray-200 text-start dark:divide-white/10">
                    <caption class="sr-only">{{ $title }}</caption>
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-white/5">
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ $columns['start_at'] }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ $columns['group'] }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ $columns['teacher'] }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-gray-600 dark:text-gray-300">
                                <span class="sr-only">{{ $columns['actions'] }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-transparent">
                        @foreach ($rows as $row)
                            <tr class="transition-colors duration-150 hover:bg-primary-50/50 focus-within:bg-primary-50/50 dark:hover:bg-primary-950/20 dark:focus-within:bg-primary-950/20">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ $row['start_at'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                    {{ $row['group'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                    {{ $row['teacher'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <a
                                        aria-label="{{ $row['view_label'] }}"
                                        class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-primary-700 transition-[background-color,color,scale] duration-150 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 active:scale-[0.96] dark:text-primary-400 dark:hover:bg-primary-950/40 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900"
                                        href="{{ $row['href'] }}"
                                    >
                                        {{ $columns['actions'] }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
