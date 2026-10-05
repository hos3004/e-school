<style>
    .iq-settings {
        padding: 1.25rem;
        background: linear-gradient(135deg, rgb(248 250 252), rgb(240 253 250));
        border-bottom: 1px solid rgb(226 232 240);
    }
    .iq-settings__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem 1.5rem;
        margin-block-end: 1rem;
    }
    .iq-settings__title {
        display: flex;
        align-items: center;
        gap: .75rem;
    }
    .iq-settings__icon {
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        flex: none;
        color: rgb(13 148 136);
        background: rgb(204 251 241);
        border-radius: .75rem;
    }
    .iq-settings__title strong { display: block; font-size: .95rem; color: rgb(15 23 42); }
    .iq-settings__title p { margin-block-start: .15rem; font-size: .78rem; color: rgb(100 116 139); }
    .iq-settings__badge {
        padding: .35rem .65rem;
        color: rgb(15 118 110);
        background: rgb(204 251 241);
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 600;
    }
    .iq-settings__grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(8.5rem, 1fr));
        gap: .75rem;
        align-items: end;
    }
    .iq-field { display: grid; gap: .35rem; min-width: 0; }
    .iq-field > span, .iq-settings__window legend {
        color: rgb(71 85 105);
        font-size: .72rem;
        font-weight: 600;
    }
    .iq-control {
        width: 100%;
        min-height: 2.55rem;
        padding-inline: .7rem;
        color: rgb(15 23 42);
        background: white;
        border: 1px solid rgb(203 213 225);
        border-radius: .65rem;
        outline: none;
    }
    .iq-control:focus {
        border-color: rgb(20 184 166);
        box-shadow: 0 0 0 3px rgb(20 184 166 / .14);
    }
    .iq-settings__window {
        grid-column: span 2;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
        padding: .65rem;
        background: rgb(255 255 255 / .72);
        border: 1px solid rgb(204 251 241);
        border-radius: .8rem;
    }
    .iq-settings__window legend { grid-column: 1 / -1; padding-inline: .15rem; }
    .iq-custom-toggle {
        display: flex;
        align-items: center;
        gap: .4rem;
        margin-block-start: .6rem;
        color: rgb(71 85 105);
        font-size: .72rem;
    }
    .iq-row-custom {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .55rem;
        margin-block-start: .65rem;
        padding: .65rem;
        background: rgb(248 250 252);
        border: 1px solid rgb(226 232 240);
        border-radius: .75rem;
    }
    .iq-field--compact { font-size: .7rem; }
    .iq-field--compact:last-child { grid-column: 1 / -1; }
    .iq-week-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(11rem, 1fr));
        gap: .55rem .75rem;
    }
    .dark .iq-settings { background: linear-gradient(135deg, rgb(15 23 42), rgb(19 78 74 / .45)); border-color: rgb(51 65 85); }
    .dark .iq-settings__title strong, .dark .iq-control { color: rgb(241 245 249); }
    .dark .iq-settings__title p, .dark .iq-field > span, .dark .iq-settings__window legend, .dark .iq-custom-toggle { color: rgb(148 163 184); }
    .dark .iq-control { background: rgb(15 23 42); border-color: rgb(71 85 105); }
    .dark .iq-settings__window, .dark .iq-row-custom { background: rgb(15 23 42 / .6); border-color: rgb(51 65 85); }
    @media (max-width: 72rem) {
        .iq-settings__grid { grid-template-columns: repeat(3, minmax(9rem, 1fr)); }
    }
    @media (max-width: 48rem) {
        .iq-settings { padding: 1rem; }
        .iq-settings__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .iq-settings__window { grid-column: 1 / -1; }
        .iq-week-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 32rem) {
        .iq-settings__grid, .iq-row-custom { grid-template-columns: 1fr; }
        .iq-field--compact:last-child { grid-column: auto; }
    }
</style>

<section class="iq-settings" aria-labelledby="iq-shared-settings-title">
    <header class="iq-settings__header">
        <div class="iq-settings__title">
            <span class="iq-settings__icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-adjustments-horizontal" style="width: 1.25rem; height: 1.25rem" />
            </span>
            <div>
                <strong id="iq-shared-settings-title">{{ __('students::admin.individual_quran.shared_settings') }}</strong>
                <p>{{ __('students::admin.individual_quran.shared_settings_help') }}</p>
            </div>
        </div>
        <span class="iq-settings__badge">
            {{ __('students::admin.individual_quran.shared_applies_to', ['count' => count($this->placementRows)]) }}
        </span>
    </header>

    <div class="iq-settings__grid">
        <label class="iq-field">
            <span>{{ __('students::admin.individual_quran.duration') }}</span>
            <select class="iq-control" wire:model.live="sharedSettings.duration_minutes">
                @foreach ($this->durationOptions() as $minutes => $label)
                    <option value="{{ $minutes }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="iq-field">
            <span>{{ __('students::admin.individual_quran.starts_on') }}</span>
            <input class="iq-control" type="date" wire:model.blur="sharedSettings.starts_on">
        </label>
        <label class="iq-field">
            <span>{{ __('students::admin.individual_quran.ends_on_optional') }}</span>
            <input class="iq-control" type="date" min="{{ $this->sharedSettings['starts_on'] ?? '' }}" wire:model.blur="sharedSettings.ends_on">
        </label>
        <label class="iq-field">
            <span>{{ __('students::admin.individual_quran.interval_weeks') }}</span>
            <input class="iq-control" type="number" min="1" max="{{ (int) config('scheduling.individual_quran.max_interval_weeks') }}" wire:model.blur="sharedSettings.interval_weeks">
        </label>
        <label class="iq-field">
            <span>{{ __('students::admin.individual_quran.timezone') }}</span>
            <input class="iq-control" type="text" wire:model.blur="sharedSettings.timezone">
        </label>
        <fieldset class="iq-settings__window">
            <legend>{{ __('students::admin.individual_quran.selection_window') }}</legend>
            <label class="iq-field">
                <span>{{ __('students::admin.individual_quran.selection_window_start') }}</span>
                <input class="iq-control" type="time" wire:model.live="sharedSettings.selection_window_start">
            </label>
            <label class="iq-field">
                <span>{{ __('students::admin.individual_quran.selection_window_end') }}</span>
                <input class="iq-control" type="time" wire:model.live="sharedSettings.selection_window_end">
            </label>
        </fieldset>
    </div>
</section>
