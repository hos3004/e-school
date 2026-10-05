import type {
    HTMLAttributes,
    ReactNode,
} from 'react';

export type CardVariant = 'default' | 'muted' | 'outlined';
export type CardPadding = 'none' | 'sm' | 'md' | 'lg';
export type CardElement = 'article' | 'div' | 'section';

export interface CardProps
    extends Omit<HTMLAttributes<HTMLElement>, 'children'> {
    as?: CardElement;
    children: ReactNode;
    padding?: CardPadding;
    variant?: CardVariant;
}

const variantClasses: Record<CardVariant, string> = {
    default:
        'border-[var(--line)] bg-[var(--surface-raised)] text-[var(--ink)] shadow-[var(--shadow-card)]',
    muted:
        'border-[var(--line)] bg-[var(--surface-muted)] text-[var(--ink)]',
    outlined:
        'border-[var(--line-strong)] bg-transparent text-[var(--ink)]',
};

const paddingClasses: Record<CardPadding, string> = {
    none: 'p-0',
    sm: 'py-3 ps-3 pe-3',
    md: 'py-5 ps-5 pe-5',
    lg: 'py-6 ps-6 pe-6',
};

function classNames(
    ...classes: Array<string | false | null | undefined>
): string {
    return classes.filter(Boolean).join(' ');
}

export default function Card({
    as: Component = 'div',
    children,
    className,
    padding = 'md',
    variant = 'default',
    ...cardProps
}: CardProps) {
    return (
        <Component
            {...cardProps}
            className={classNames(
                'rounded-[var(--radius-lg)] border',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)]',
                'focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]',
                variantClasses[variant],
                paddingClasses[padding],
                className,
            )}
        >
            {children}
        </Component>
    );
}

export type CardHeaderProps = HTMLAttributes<HTMLDivElement>;

export function CardHeader({
    children,
    className,
    ...headerProps
}: CardHeaderProps) {
    return (
        <div
            {...headerProps}
            className={classNames('flex flex-col gap-1', className)}
        >
            {children}
        </div>
    );
}

export interface CardTitleProps
    extends HTMLAttributes<HTMLHeadingElement> {
    as?: 'h2' | 'h3' | 'h4';
}

export function CardTitle({
    as: Component = 'h3',
    children,
    className,
    ...titleProps
}: CardTitleProps) {
    return (
        <Component
            {...titleProps}
            className={classNames(
                'text-lg font-semibold leading-snug text-[var(--ink)] [text-wrap:balance]',
                className,
            )}
        >
            {children}
        </Component>
    );
}

export type CardDescriptionProps = HTMLAttributes<HTMLParagraphElement>;

export function CardDescription({
    children,
    className,
    ...descriptionProps
}: CardDescriptionProps) {
    return (
        <p
            {...descriptionProps}
            className={classNames(
                'text-sm leading-relaxed text-[var(--ink-muted)] [text-wrap:pretty]',
                className,
            )}
        >
            {children}
        </p>
    );
}

export type CardContentProps = HTMLAttributes<HTMLDivElement>;

export function CardContent({
    children,
    className,
    ...contentProps
}: CardContentProps) {
    return (
        <div
            {...contentProps}
            className={classNames('min-w-0', className)}
        >
            {children}
        </div>
    );
}

export type CardFooterProps = HTMLAttributes<HTMLDivElement>;

export function CardFooter({
    children,
    className,
    ...footerProps
}: CardFooterProps) {
    return (
        <div
            {...footerProps}
            className={classNames(
                'flex flex-wrap items-center justify-end gap-3 border-t border-[var(--line)] pt-4',
                className,
            )}
        >
            {children}
        </div>
    );
}
