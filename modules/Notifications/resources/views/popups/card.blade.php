{{-- مكوّن النافذة المنبثقة الموحد: يخدم الواجهة الحقيقية والمعاينة بنفس الشكل --}}
{{-- المحتوى نص عادي يهرّب تلقائيًا عبر {{ }} — لا HTML من الادمن ابدا --}}

@php
    $colorClasses = match ($popup['color'] ?? 'gray') {
        'danger' => 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950',
        'primary' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950',
        'warning' => 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950',
        'info' => 'border-sky-300 bg-sky-50 dark:border-sky-800 dark:bg-sky-950',
        default => 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900',
    };

    $isPreview = $preview ?? false;
@endphp

<div
    data-popup-card="true"
    data-campaign-id="{{ $popup['id'] }}"
    class="{{ $colorClasses }} w-full max-w-md rounded-xl border shadow-lg p-5 {{ $isPreview ? 'opacity-90 pointer-events-none select-none' : '' }}"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="popup-title-{{ $popup['id'] }}"
    aria-describedby="popup-body-{{ $popup['id'] }}"
>
    <div class="flex items-start gap-3">
        @svg($popup['icon'], 'h-6 w-6 shrink-0 mt-0.5 text-gray-700 dark:text-gray-200')

        <div class="min-w-0 flex-1">
            <h2 id="popup-title-{{ $popup['id'] }}" class="text-base font-bold leading-6 text-gray-950 dark:text-white">
                {{ $popup['title'] }}
            </h2>

            <p id="popup-body-{{ $popup['id'] }}" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">
                {{ $popup['body'] }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if (! empty($popup['action_url']) && ! empty($popup['action_label']))
                    <a
                        href="{{ $popup['action_url'] }}"
                        data-popup-action="click"
                        @if ($isPreview)
                            tabindex="-1"
                            aria-disabled="true"
                            onclick="return false"
                        @endif
                        @if ($popup['action_is_external'])
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                        class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 motion-reduce:transition-none"
                    >
                        {{ $popup['action_label'] }}
                    </a>
                @endif

                @if ($popup['requires_acknowledgement'])
                    <button
                        type="button"
                        data-popup-action="acknowledge"
                        @if ($isPreview) disabled @endif
                        class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                    >
                        {{ $popup['acknowledgement_label'] }}
                    </button>
                @endif

                @if ($popup['is_dismissible'] && ! $isPreview)
                    <button
                        type="button"
                        data-popup-action="dismiss"
                        class="ms-auto inline-flex items-center rounded-md p-1.5 text-gray-500 hover:bg-black/5 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/10"
                        aria-label="{{ __('notifications::popups.frontend.dismiss') }}"
                    >
                        @svg('heroicon-o-x-mark', 'h-5 w-5')
                    </button>
                @endif

                @if (! $popup['is_dismissible'] && ! $popup['requires_acknowledgement'] && ($preview ?? false))
                    <span class="text-xs font-semibold text-red-600">{{ __('notifications::popups.preview.unsafe_exit_warning') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
