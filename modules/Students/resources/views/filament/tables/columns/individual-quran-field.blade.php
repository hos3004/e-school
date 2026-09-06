@php
    $studentId = (string) $record->getKey();
    $prefix = 'placementRows.'.$studentId;
    $row = $this->placementRows[$studentId] ?? [];
    $isScheduled = $this->isStudentScheduled($studentId);
    $summary = $isScheduled ? $this->scheduleSummary($studentId) : [];
@endphp

@if ($isScheduled)
    @if ($field === 'teacher')
        <span>{{ $this->teacherOptions()[$summary['staff_profile_id'] ?? ''] ?? __('students::admin.common.not_available') }}</span>
    @elseif ($field === 'weekdays')
        <div style="display: grid; gap: .3rem">
            @foreach ((array) ($summary['weekly_slots'] ?? []) as $slot)
                <div style="display: grid; gap: .1rem">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: .5rem">
                        <span>{{ $this->weekdayOptions()[(int) $slot['weekday']] ?? '—' }}</span>
                        <strong>{{ $this->timeLabel((string) $slot['start_time']) }}</strong>
                    </div>
                    @if ($studentTime = $this->studentSlotLabel($record, (array) $slot, $summary))
                        <small class="fi-color-gray">{{ $studentTime }}</small>
                    @endif
                </div>
            @endforeach
            <small class="fi-color-gray">
                {{ __('students::admin.individual_quran.display_timezone', ['timezone' => $summary['timezone'] ?? config('app.timezone')]) }}
            </small>
            <small class="fi-color-gray">
                {{ __('scheduling::filament.schedule.minutes', ['minutes' => $summary['duration_minutes'] ?? 0]) }} ·
                {{ trans_choice('students::admin.individual_quran.every_weeks', (int) ($summary['interval_weeks'] ?? 1), ['count' => $summary['interval_weeks'] ?? 1]) }}
            </small>
            <small class="fi-color-gray">
                {{ $summary['starts_on'] ?? '—' }} — {{ $summary['ends_on'] ?? __('students::admin.individual_quran.open_ended') }}
            </small>
        </div>
    @elseif ($field === 'duration')
        <span>{{ __('scheduling::filament.schedule.minutes', ['minutes' => $summary['duration_minutes'] ?? 0]) }}</span>
    @elseif ($field === 'period')
        <div style="display: grid; gap: .2rem; font-size: .8rem">
            <span>{{ __('students::admin.individual_quran.starts_on') }}: {{ $summary['starts_on'] ?? '—' }}</span>
            <span>{{ __('students::admin.individual_quran.ends_on') }}: {{ $summary['ends_on'] ?? __('students::admin.individual_quran.open_ended') }}</span>
        </div>
    @elseif ($field === 'interval')
        <span>{{ trans_choice('students::admin.individual_quran.every_weeks', (int) ($summary['interval_weeks'] ?? 1), ['count' => $summary['interval_weeks'] ?? 1]) }}</span>
    @endif
@elseif ($field === 'teacher')
    <div>
        <div class="fi-input-wrp">
            <div class="fi-input-wrp-content-ctn">
                <select
                    class="fi-select-input"
                    aria-label="{{ __('students::admin.individual_quran.teacher') }}"
                    wire:model.live="{{ $prefix }}.staff_profile_id"
                >
                    <option value="">{{ __('students::admin.individual_quran.choose_teacher') }}</option>
                    @foreach ($this->teacherOptions() as $teacherId => $teacherName)
                        <option value="{{ $teacherId }}">{{ $teacherName }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @error($prefix.'.staff_profile_id')
            <p class="fi-color-danger" style="font-size: .75rem; margin-top: .25rem">{{ $message }}</p>
        @enderror
        <label class="iq-custom-toggle">
            <input type="checkbox" wire:model.live="{{ $prefix }}.use_custom_settings">
            <span>{{ __('students::admin.individual_quran.custom_settings') }}</span>
        </label>
        @if ((bool) ($row['use_custom_settings'] ?? false))
            <div class="iq-row-custom">
                <label class="iq-field iq-field--compact">
                    <span>{{ __('students::admin.individual_quran.duration') }}</span>
                    <select class="iq-control" wire:model.live="{{ $prefix }}.duration_minutes">
                        @foreach ($this->durationOptions() as $minutes => $label)
                            <option value="{{ $minutes }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="iq-field iq-field--compact">
                    <span>{{ __('students::admin.individual_quran.starts_on') }}</span>
                    <input class="iq-control" type="date" wire:model.blur="{{ $prefix }}.starts_on">
                </label>
                <label class="iq-field iq-field--compact">
                    <span>{{ __('students::admin.individual_quran.ends_on_optional') }}</span>
                    <input class="iq-control" type="date" min="{{ $row['starts_on'] ?? '' }}" wire:model.blur="{{ $prefix }}.ends_on">
                </label>
                <label class="iq-field iq-field--compact">
                    <span>{{ __('students::admin.individual_quran.interval_weeks') }}</span>
                    <input class="iq-control" type="number" min="1" max="{{ (int) config('scheduling.individual_quran.max_interval_weeks') }}" wire:model.blur="{{ $prefix }}.interval_weeks">
                </label>
                <label class="iq-field iq-field--compact">
                    <span>{{ __('students::admin.individual_quran.timezone') }}</span>
                    <input class="iq-control" type="text" wire:model.blur="{{ $prefix }}.timezone">
                </label>
            </div>
        @endif
    </div>
@elseif ($field === 'weekdays')
    @php
        $selectedDays = array_map('intval', (array) ($row['weekdays'] ?? []));
        $hasTeacher = filled($row['staff_profile_id'] ?? null);
    @endphp
    <fieldset aria-label="{{ __('students::admin.individual_quran.days_and_times') }}">
        <div class="iq-week-grid">
            @foreach ($this->weekdayOptions() as $day => $label)
                @php
                    $isSelected = in_array((int) $day, $selectedDays, true);
                    $availability = $isSelected && $hasTeacher
                        ? $this->availabilityForDay($studentId, (int) $day)
                        : ['times' => [], 'confirmed' => false];
                    $times = $availability['times'];
                    $timeLabel = __('students::admin.individual_quran.day_time', ['day' => $label]);
                @endphp
                <div wire:key="placement-slot-{{ $studentId }}-{{ $day }}">
                    <div style="display: grid; grid-template-columns: minmax(5.5rem, auto) minmax(8rem, 1fr); align-items: center; gap: .45rem">
                        <label style="display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap; font-size: .8rem">
                            <input
                                type="checkbox"
                                value="{{ $day }}"
                                wire:model.live="{{ $prefix }}.weekdays"
                            >
                            <span>{{ $label }}</span>
                        </label>
                        @if ($isSelected)
                            <div
                                class="fi-input-wrp"
                                wire:loading.class="fi-opacity-50"
                                wire:target="{{ $prefix }}.staff_profile_id,{{ $prefix }}.weekdays,{{ $prefix }}.duration_minutes,{{ $prefix }}.starts_on,{{ $prefix }}.ends_on,{{ $prefix }}.interval_weeks"
                            >
                                <div class="fi-input-wrp-content-ctn">
                                    <select
                                        class="fi-select-input"
                                        aria-label="{{ $timeLabel }}"
                                        wire:model.live="{{ $prefix }}.slot_times.{{ $day }}"
                                    >
                                        <option value="">
                                            {{ ! $hasTeacher
                                                ? __('students::admin.individual_quran.choose_teacher')
                                                : ($times === []
                                                    ? __('students::admin.individual_quran.no_available_times')
                                                    : __('students::admin.individual_quran.choose_time')) }}
                                        </option>
                                        @foreach ($times as $time)
                                            <option value="{{ $time }}">{{ $this->timeLabel($time) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                    @if ($isSelected && $hasTeacher)
                        <p class="fi-color-gray" style="font-size: .7rem; margin-inline-start: 6rem; margin-top: .2rem">
                            {{ __('students::admin.individual_quran.available_count', ['count' => count($times)]) }} ·
                            {{ $availability['confirmed']
                                ? __('students::admin.individual_quran.confirmed_availability')
                                : __('students::admin.individual_quran.unbooked_only') }}
                        </p>
                    @endif
                    @error($prefix.'.slot_times.'.$day)
                        <p class="fi-color-danger" style="font-size: .75rem; margin-inline-start: 6rem; margin-top: .2rem">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
        @error($prefix.'.weekdays')
            <p class="fi-color-danger" style="font-size: .75rem; margin-top: .25rem">{{ $message }}</p>
        @enderror
    </fieldset>
@endif
