<x-filament-panels::page>
    <div class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-end">
        <label class="grid gap-1 text-sm font-medium">
            <span>{{ __('sessions::calendar.mode') }}</span>
            <select wire:model.live="calendarMode" class="rounded-lg border-gray-300">
                <option value="week">{{ __('sessions::calendar.week') }}</option>
                <option value="month">{{ __('sessions::calendar.month') }}</option>
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            <span>{{ __('sessions::calendar.group') }}</span>
            <select wire:model.live="groupFilter" class="rounded-lg border-gray-300">
                <option value="">{{ __('sessions::calendar.all') }}</option>
                @foreach ($this->groupOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            <span>{{ __('sessions::calendar.teacher') }}</span>
            <select wire:model.live="teacherFilter" class="rounded-lg border-gray-300">
                <option value="">{{ __('sessions::calendar.all') }}</option>
                @foreach ($this->teacherOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            <span>{{ __('sessions::calendar.status') }}</span>
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300">
                <option value="">{{ __('sessions::calendar.all') }}</option>
                @foreach ($this->statusOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <button type="button" wire:click="previousPeriod" class="rounded-lg border px-3 py-2">{{ __('sessions::calendar.previous') }}</button>
            <button type="button" wire:click="currentPeriod" class="rounded-lg border px-3 py-2">{{ __('sessions::calendar.today') }}</button>
            <button type="button" wire:click="nextPeriod" class="rounded-lg border px-3 py-2">{{ __('sessions::calendar.next') }}</button>
        </div>
    </div>
    <div class="grid gap-3">
        @forelse ($this->getSessions() as $session)
            <a href="{{ $this->sessionUrl($session['id']) }}" class="rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-medium">{{ $session['title'] }}</span>
                    <span class="text-sm text-gray-500">{{ $session['status'] }}</span>
                </div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ $session['start'] }} — {{ $session['end'] }}
                </div>
            </a>
        @empty
            <div class="rounded-xl border p-6 text-gray-500">{{ __('sessions::calendar.empty') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
