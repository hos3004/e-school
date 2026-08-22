import type {
    HTMLAttributes,
    ReactNode,
} from 'react';

export type BadgeTone =
    | 'neutral'
    | 'brand'
    | 'success'
    | 'warning'
    | 'danger';

export type BadgeSize = 'sm' | 'md';

export interface BadgeProps
    extends Omit<HTMLAttributes<HTMLSpanElement>, 'children'> {
    children: ReactNode;
    size?: BadgeSize;
    tone?: BadgeTone;
}

const toneClasses: Record<BadgeTone, string> = {
    neutral:
        'border-[var(--ink-muted)] bg-[var(--surface-muted)] text-[var(--ink)]',
    brand:
        'border-[var(--brand)] bg-[color-mix(in_srgb,var(--brand)_12%,var(--surface))] text-[var(--ink)]',
    success:
        'border-[var(--success)] bg-[color-mix(in_srgb,var(--success)_12%,var(--surface))] text-[var(--ink)]',
    warning:
        'border-[var(--warning)] bg-[color-mix(in_srgb,var(--warning)_12%,var(--surface))] text-[var(--ink)]',
    danger:
        'border-[var(--danger)] bg-[color-mix(in_srgb,var(--danger)_12%,var(--surface))] text-[var(--ink)]',
};

const sizeClasses: Record<BadgeSize, string> = {
    sm: 'min-h-6 ps-2 pe-2 text-xs',
    md: 'min-h-7 ps-2.5 pe-2.5 text-sm',
};

function classNames(
    ...classes: Array<string | false | null | undefined>
): string {
    return classes.filter(Boolean).join(' ');
}

export default function Badge({
    children,
    className,
    size = 'sm',
    tone = 'neutral',
    ...badgeProps
}: BadgeProps) {
    return (
        <span
            {...badgeProps}
            className={classNames(
                'inline-flex max-w-full items-center rounded-full border font-semibold leading-none',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]',
                'focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]',
                toneClasses[tone],
                sizeClasses[size],
                className,
            )}
        >
            <span className="truncate">{children}</span>
        </span>
    );
}
