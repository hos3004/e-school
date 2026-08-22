import { useI18n } from '@/lib/i18n';

export interface LoadingStateProps {
    label?: string;
    rows?: number;
    className?: string;
}

export default function LoadingState({
    label,
    rows = 3,
    className,
}: LoadingStateProps) {
    const t = useI18n();
    const resolvedLabel = label ?? t('states.loading');
    const skeletonRows = Number.isFinite(rows)
        ? Math.max(1, Math.min(6, Math.trunc(rows)))
        : 3;

    return (
        <section
            aria-busy="true"
            aria-live="polite"
            className={[
                'rounded-2xl border border-[color:var(--ink-muted)]/20 bg-[var(--surface)] p-5 sm:p-6',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            role="status"
        >
            <span className="sr-only">{resolvedLabel}</span>

            <div
                aria-hidden="true"
                className="animate-pulse motion-reduce:animate-none"
            >
                <div className="h-6 w-2/5 rounded bg-[var(--surface-muted)]" />
                <div className="mt-3 h-4 w-4/5 rounded bg-[var(--surface-muted)]" />

                <div className="mt-6 space-y-3">
                    {Array.from(
                        { length: skeletonRows },
                        (_, index) => (
                            <div
                                className="grid min-h-16 grid-cols-[minmax(0,1fr)_auto] items-center gap-4 rounded-xl border border-[color:var(--ink-muted)]/15 p-4"
                                key={index}
                            >
                                <div className="space-y-2">
                                    <div className="h-4 w-3/4 rounded bg-[var(--surface-muted)]" />
                                    <div className="h-3 w-1/2 rounded bg-[var(--surface-muted)]" />
                                </div>
                                <div className="size-9 rounded-full bg-[var(--surface-muted)]" />
                            </div>
                        ),
                    )}
                </div>
            </div>
        </section>
    );
}
