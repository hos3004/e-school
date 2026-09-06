@php
    $studentId = (string) $record->getKey();
    $prefix = 'placementRows.'.$studentId;
    $row = $this->placementRows[$studentId] ?? [];
    $isScheduled = $this->isStudentScheduled($studentId);
@endphp

@if ($isScheduled)
    <span aria-label="{{ __('students::admin.individual_quran.status_scheduled') }}">—</span>
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
    </div>
@elseif ($field === 'weekdays')
    <fieldset aria-label="{{ __('students::admin.individual_quran.weekdays') }}">
        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .35rem">
            @foreach ($this->weekdayOptions() as $day => $label)
                <label style="display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap; font-size: .8rem">
                    <input
                        type="checkbox"
                        value="{{ $day }}"
                        wire:model.live="{{ $prefix }}.weekdays"
                    >
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error($prefix.'.weekdays')
            <p class="fi-color-danger" style="font-size: .75rem; margin-top: .25rem">{{ $message }}</p>
        @enderror
    </fieldset>
@elseif ($field === 'duration')
    <div>
        <div class="fi-input-wrp">
            <div class="fi-input-wrp-content-ctn">
                <select
                    class="fi-select-input"
                    aria-label="{{ __('students::admin.individual_quran.duration') }}"
                    wire:model.live="{{ $prefix }}.duration_minutes"
                >
                    @foreach ($this->durationOptions() as $minutes => $label)
                        <option value="{{ $minutes }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @error($prefix.'.duration_minutes')
            <p class="fi-color-danger" style="font-size: .75rem; margin-top: .25rem">{{ $message }}</p>
        @enderror
    </div>
@elseif ($field === 'start_time')
    @php
        $times = $this->availableTimesFor($studentId);
        $hasPrerequisites = filled($row['staff_profile_id'] ?? null) && ! empty($row['weekdays'] ?? []);
    @endphp
    <div wire:loading.class="fi-opacity-50" wire:target="{{ $prefix }}.staff_profile_id,{{ $prefix }}.weekdays,{{ $prefix }}.duration_minutes,{{ $prefix }}.starts_on,{{ $prefix }}.ends_on,{{ $prefix }}.interval_weeks">
        <div class="fi-input-wrp">
            <div class="fi-input-wrp-content-ctn">
                <select
                    class="fi-select-input"
                    aria-label="{{ __('students::admin.individual_quran.start_time') }}"
                    wire:model.live="{{ $prefix }}.start_time"
                >
                    <option value="">
                        {{ ! $hasPrerequisites
                            ? __('students::admin.individual_quran.choose_teacher_days')
                            : ($times === []
                                ? __('students::admin.individual_quran.no_available_times')
                                : __('students::admin.individual_quran.choose_time')) }}
                    </option>
                    @foreach ($times as $time)
                        <option value="{{ $time }}">{{ $time }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($hasPrerequisites)
            <p class="fi-color-gray" style="font-size: .75rem; margin-top: .25rem">
                {{ __('students::admin.individual_quran.available_count', ['count' => count($times)]) }}
            </p>
        @endif
        @error($prefix.'.start_time')
            <p class="fi-color-danger" style="font-size: .75rem; margin-top: .25rem">{{ $message }}</p>
        @enderror
    </div>
@elseif ($field === 'period')
    <div style="display: grid; gap: .4rem">
        <label style="display: grid; gap: .2rem; font-size: .75rem">
            <span>{{ __('students::admin.individual_quran.starts_on') }}</span>
            <input
                class="fi-input"
                type="date"
                aria-label="{{ __('students::admin.individual_quran.starts_on') }}"
                wire:model.blur="{{ $prefix }}.starts_on"
            >
        </label>
        @error($prefix.'.starts_on')
            <p class="fi-color-danger" style="font-size: .75rem">{{ $message }}</p>
        @enderror

        <label style="display: grid; gap: .2rem; font-size: .75rem">
            <span>{{ __('students::admin.individual_quran.ends_on_optional') }}</span>
            <input
                class="fi-input"
                type="date"
                aria-label="{{ __('students::admin.individual_quran.ends_on_optional') }}"
                min="{{ $row['starts_on'] ?? '' }}"
                wire:model.blur="{{ $prefix }}.ends_on"
            >
        </label>
        @error($prefix.'.ends_on')
            <p class="fi-color-danger" style="font-size: .75rem">{{ $message }}</p>
        @enderror
    </div>
@elseif ($field === 'interval')
    <div>
        <input
            class="fi-input"
            type="number"
            min="1"
            max="{{ (int) config('scheduling.individual_quran.max_interval_weeks') }}"
            aria-label="{{ __('students::admin.individual_quran.interval_weeks') }}"
            wire:model.blur="{{ $prefix }}.interval_weeks"
        >
        @error($prefix.'.interval_weeks')
            <p class="fi-color-danger" style="font-size: .75rem; margin-top: .25rem">{{ $message }}</p>
        @enderror
    </div>
@endif
