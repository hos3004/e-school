<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-calendar-days"
        icon-color="info"
        :heading="$title"
        :description="$subtitle"
    >
        @if (count($rows) === 0)
            <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-calendar"
                    class="h-10 w-10 text-gray-400 dark:text-gray-500"
                />
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $empty }}
                </p>
            </div>
        @else
            <div class="fi-ta-overflow-x-auto overflow-x-auto">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-gray-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $columns['start_at'] }}
                            </th>
                            <th class="px-4 py-2.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $columns['group'] }}
                            </th>
                            <th class="px-4 py-2.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $columns['teacher'] }}
                            </th>
                            <th class="px-4 py-2.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                <span class="sr-only">{{ $columns['actions'] }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $row['start_at'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $row['group'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $row['teacher'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <a
                                        aria-label="{{ $row['view_label'] }}"
                                        class="inline-flex items-center gap-1 rounded-md text-sm font-semibold text-primary-600 hover:text-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:text-primary-400"
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
