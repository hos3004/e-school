import type { HTMLAttributes, ReactNode } from 'react';

interface StudentPageHeroProps {
    title: ReactNode;
    subtitle?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export function StudentPageHero({
    title,
    subtitle,
    action,
    className,
}: StudentPageHeroProps) {
    return (
        <header
            className={[
                'relative isolate overflow-hidden rounded-[1.75rem] border border-[color:var(--brand)]/20',
                'bg-[linear-gradient(135deg,color-mix(in_srgb,var(--brand)_14%,var(--surface)),var(--surface)_64%)]',
                'px-5 py-6 shadow-sm sm:px-8 sm:py-8',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -end-14 -top-20 size-48 rounded-full bg-[color:var(--brand)]/10 blur-3xl"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-20 start-1/3 size-40 rounded-full bg-[color:var(--success)]/10 blur-3xl"
            />

            <div className="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 max-w-3xl">
                    <div
                        aria-hidden="true"
                        className="mb-4 flex size-11 items-center justify-center rounded-2xl bg-[var(--brand)] text-[var(--surface)] shadow-sm"
                    >
                        <svg
                            className="size-6"
                            fill="none"
                            stroke="currentColor"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="2"
                            viewBox="0 0 24 24"
                        >
                            <path d="m4 10 8-4 8 4-8 4-8-4Z" />
                            <path d="M7 12.5V16c0 1.2 2.2 2.5 5 2.5s5-1.3 5-2.5v-3.5" />
                        </svg>
                    </div>
                    <h1 className="break-words text-3xl font-bold leading-tight tracking-tight text-[var(--ink)] sm:text-4xl">
                        {title}
                    </h1>
                    {subtitle ? (
                        <p className="mt-3 max-w-2xl text-sm leading-7 text-[var(--ink-muted)] sm:text-base">
                            {subtitle}
                        </p>
                    ) : null}
                </div>

                {action ? (
                    <div className="flex w-full shrink-0 flex-wrap items-center gap-3 sm:w-auto sm:justify-end">
                        {action}
                    </div>
                ) : null}
            </div>
        </header>
    );
}

interface StudentMetricProps extends HTMLAttributes<HTMLDivElement> {
    label: ReactNode;
    value: ReactNode;
    detail?: ReactNode;
    tone?: 'brand' | 'success' | 'warning' | 'neutral';
}

const metricToneClasses: Record<
    NonNullable<StudentMetricProps['tone']>,
    string
> = {
    brand: 'bg-[color:var(--brand)]/10 text-[var(--brand)]',
    success: 'bg-[color:var(--success)]/10 text-[var(--success)]',
    warning: 'bg-[color:var(--warning)]/10 text-[var(--warning)]',
    neutral: 'bg-[var(--surface-muted)] text-[var(--ink-muted)]',
};

export function StudentMetric({
    label,
    value,
    detail,
    tone = 'brand',
    className,
    ...props
}: StudentMetricProps) {
    return (
        <div
            {...props}
            className={[
                'rounded-2xl border border-[color:var(--ink-muted)]/15 bg-[var(--surface)] p-5 shadow-sm',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div
                className={[
                    'mb-4 size-2.5 rounded-full',
                    metricToneClasses[tone],
                ].join(' ')}
            />
            <p className="text-sm font-semibold text-[var(--ink-muted)]">
                {label}
            </p>
            <p className="mt-1 text-3xl font-bold tabular-nums tracking-tight text-[var(--ink)]">
                {value}
            </p>
            {detail ? (
                <div className="mt-4 text-sm leading-6 text-[var(--ink-muted)]">
                    {detail}
                </div>
            ) : null}
        </div>
    );
}

interface StudentSectionHeadingProps {
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    id?: string;
}

export function StudentSectionHeading({
    title,
    description,
    action,
    id,
}: StudentSectionHeadingProps) {
    return (
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                <h2
                    className="text-xl font-bold tracking-tight text-[var(--ink)] sm:text-2xl"
                    id={id}
                >
                    {title}
                </h2>
                {description ? (
                    <p className="mt-1 max-w-2xl text-sm leading-6 text-[var(--ink-muted)]">
                        {description}
                    </p>
                ) : null}
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
        </div>
    );
}

interface StudentMetaItemProps {
    label: ReactNode;
    value: ReactNode;
    emphasize?: boolean;
}

export function StudentMetaItem({
    label,
    value,
    emphasize = false,
}: StudentMetaItemProps) {
    return (
        <div className="min-w-0 rounded-xl bg-[var(--surface-muted)] px-4 py-3">
            <dt className="text-xs font-semibold text-[var(--ink-muted)]">
                {label}
            </dt>
            <dd
                className={[
                    'mt-1 break-words text-sm text-[var(--ink)]',
                    emphasize && 'font-bold',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                {value}
            </dd>
        </div>
    );
}
