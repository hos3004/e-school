{{-- معاينة الحملة بنفس مكوّن الواجهة — بلا تتبع إطلاقا --}}

@php
    $popup = [
        'id' => (string) $record->getKey(),
        'icon' => $record->type->icon(),
        'color' => $record->type->color(),
        'title' => (string) ($record->title['ar'] ?? $record->title[app()->getLocale()] ?? '—'),
        'body' => (string) ($record->body['ar'] ?? $record->body[app()->getLocale()] ?? '—'),
        'acknowledgement_label' => (string) ($record->acknowledgement_label['ar']
            ?? __('notifications::popups.frontend.acknowledge_default')),
        'action_label' => (string) ($record->action_label['ar'] ?? ''),
        'action_url' => '#',
        'action_is_external' => false,
        'is_dismissible' => $record->is_dismissible,
        'requires_acknowledgement' => $record->requires_acknowledgement,
    ];
@endphp

<div class="space-y-3">
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">
        {{ __('notifications::popups.preview.banner') }}
    </p>

    <div class="flex justify-center rounded-lg bg-gray-100 p-6 dark:bg-gray-950">
        @include('notifications::popups.card', ['popup' => $popup, 'preview' => true])
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('notifications::popups.preview.no_tracking_note') }}
    </p>
</div>
