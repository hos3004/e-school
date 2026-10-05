import type { ReactNode } from 'react';

export interface PageHeaderProps {
    title: ReactNode;
    subtitle?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export default function PageHeader({
    title,
    subtitle,
    action,
    className,
}: PageHeaderProps) {
    return (
        <header
            className={[
                'flex flex-col gap-5 border-b border-[var(--line)] pb-5 sm:flex-row sm:items-start sm:justify-between sm:pb-6',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div className="min-w-0">
                <h1 className="break-words text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance] sm:text-3xl">
                    {title}
                </h1>

                {subtitle && (
                    <div className="mt-2 max-w-3xl text-sm leading-6 text-[var(--ink-muted)] [text-wrap:pretty] sm:text-base">
                        {subtitle}
                    </div>
                )}
            </div>

            {action && (
                <div className="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                    {action}
                </div>
            )}
        </header>
    );
}
