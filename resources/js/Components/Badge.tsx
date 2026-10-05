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
        'border-[var(--line-strong)] bg-[var(--surface-muted)] text-[var(--ink-soft)]',
    brand:
        'border-[color-mix(in_srgb,var(--brand)_35%,var(--line))] bg-[var(--brand-soft)] text-[var(--brand-strong)]',
    success:
        'border-[color-mix(in_srgb,var(--success)_35%,var(--line))] bg-[var(--success-soft)] text-[var(--success)]',
    warning:
        'border-[color-mix(in_srgb,var(--warning)_35%,var(--line))] bg-[var(--warning-soft)] text-[var(--warning)]',
    danger:
        'border-[color-mix(in_srgb,var(--danger)_35%,var(--line))] bg-[var(--danger-soft)] text-[var(--danger)]',
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
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)]',
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
