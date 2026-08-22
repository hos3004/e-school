import { useId, type ReactNode } from 'react';

import { useI18n } from '@/lib/i18n';

export interface EmptyStateProps {
    title?: string;
    description?: string;
    action?: ReactNode;
    icon?: ReactNode;
    className?: string;
}

export default function EmptyState({
    title,
    description,
    action,
    icon,
    className,
}: EmptyStateProps) {
    const t = useI18n();
    const titleId = useId();
    const descriptionId = useId();
    const resolvedTitle = title ?? t('states.empty.title');
    const resolvedDescription =
        description ?? t('states.empty.description');

    return (
        <section
            aria-describedby={resolvedDescription ? descriptionId : undefined}
            aria-labelledby={titleId}
            className={[
                'rounded-2xl border border-[color:var(--ink-muted)]/25 bg-[var(--surface)] px-5 py-10 text-center sm:px-8',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div
                aria-hidden="true"
                className="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--surface-muted)] text-[var(--ink-muted)]"
            >
                {icon ?? (
                    <svg
                        className="size-6"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M4 7.75A2.75 2.75 0 0 1 6.75 5h10.5A2.75 2.75 0 0 1 20 7.75v8.5A2.75 2.75 0 0 1 17.25 19H6.75A2.75 2.75 0 0 1 4 16.25v-8.5Z"
                            stroke="currentColor"
                            strokeWidth="1.75"
                        />
                        <path
                            d="M4.5 14h4l1.25 2h4.5l1.25-2h4"
                            stroke="currentColor"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="1.75"
                        />
                    </svg>
                )}
            </div>

            <h2
                className="mt-4 text-base font-semibold text-[var(--ink)]"
                id={titleId}
            >
                {resolvedTitle}
            </h2>

            {resolvedDescription && (
                <p
                    className="mx-auto mt-2 max-w-prose text-sm leading-6 text-[var(--ink-muted)]"
                    id={descriptionId}
                >
                    {resolvedDescription}
                </p>
            )}

            {action && (
                <div className="mt-6 flex justify-center">{action}</div>
            )}
        </section>
    );
}
