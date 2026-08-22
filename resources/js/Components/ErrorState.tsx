import { useId } from 'react';

import { useI18n } from '@/lib/i18n';

export interface ErrorStateProps {
    message?: string;
    onRetry?: () => void;
    className?: string;
}

export default function ErrorState({
    message,
    onRetry,
    className,
}: ErrorStateProps) {
    const t = useI18n();
    const titleId = useId();
    const messageId = useId();
    const resolvedMessage = message ?? t('states.error.message');

    return (
        <section
            aria-describedby={messageId}
            aria-labelledby={titleId}
            aria-live="assertive"
            className={[
                'rounded-2xl border border-[color:var(--danger)]/40 bg-[var(--surface)] px-5 py-8 text-center sm:px-8',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            role="alert"
        >
            <div
                aria-hidden="true"
                className="mx-auto flex size-12 items-center justify-center rounded-full bg-[color:var(--danger)]/10 text-[var(--danger)]"
            >
                <svg className="size-6" fill="none" viewBox="0 0 24 24">
                    <path
                        d="M12 8v4.5m0 3.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.75"
                    />
                </svg>
            </div>

            <h2
                className="mt-4 text-base font-semibold text-[var(--ink)]"
                id={titleId}
            >
                {t('states.error.title')}
            </h2>

            <p
                className="mx-auto mt-2 max-w-prose text-sm leading-6 text-[var(--ink-muted)]"
                id={messageId}
            >
                {resolvedMessage}
            </p>

            {onRetry && (
                <button
                    className="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-[var(--surface)] transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]"
                    onClick={onRetry}
                    type="button"
                >
                    {t('actions.retry')}
                </button>
            )}
        </section>
    );
}
