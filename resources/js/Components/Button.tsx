import { Link } from '@inertiajs/react';
import type {
    ButtonHTMLAttributes,
    ComponentPropsWithoutRef,
    ReactNode,
} from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonSharedProps {
    children: ReactNode;
    className?: string;
    fullWidth?: boolean;
    size?: ButtonSize;
    variant?: ButtonVariant;
}

export type NativeButtonProps = ButtonSharedProps &
    Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children' | 'className'> & {
        as?: 'button';
    };

type InertiaLinkProps = ComponentPropsWithoutRef<typeof Link>;

export type ButtonLinkProps = ButtonSharedProps &
    Omit<
        InertiaLinkProps,
        'as' | 'children' | 'className' | 'disabled' | 'size'
    > & {
        as: 'link';
        disabled?: boolean;
    };

export type ButtonProps = NativeButtonProps | ButtonLinkProps;

const variantClasses: Record<ButtonVariant, string> = {
    primary:
        'border border-[var(--brand)] bg-[var(--brand)] text-[var(--ink-inverse)] shadow-[0_1px_2px_rgb(20_37_54/0.12)] hover:border-[var(--brand-strong)] hover:bg-[var(--brand-strong)]',
    secondary:
        'border border-[var(--line-strong)] bg-[var(--surface)] text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.05)] hover:border-[var(--brand)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand-strong)]',
    danger:
        'border border-[var(--danger)] bg-[var(--surface)] text-[var(--danger)] hover:bg-[var(--danger-soft)]',
    ghost:
        'border border-transparent bg-transparent text-[var(--ink-soft)] hover:bg-[var(--surface-muted)] hover:text-[var(--ink)]',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'min-h-10 ps-3 pe-3 text-sm',
    md: 'min-h-11 ps-4 pe-4 text-sm',
    lg: 'min-h-12 ps-5 pe-5 text-base',
};

function classNames(
    ...classes: Array<string | false | null | undefined>
): string {
    return classes.filter(Boolean).join(' ');
}

function buttonClasses(
    variant: ButtonVariant,
    size: ButtonSize,
    fullWidth: boolean,
    className?: string,
): string {
    return classNames(
        'inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] font-semibold',
        'transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-out active:scale-[0.96] motion-reduce:transform-none',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)]',
        'focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]',
        'disabled:cursor-not-allowed disabled:opacity-55 disabled:active:scale-100',
        variantClasses[variant],
        sizeClasses[size],
        fullWidth && 'w-full',
        className,
    );
}

function NativeButton({
    as: _as,
    children,
    className,
    fullWidth = false,
    size = 'md',
    type = 'button',
    variant = 'primary',
    ...buttonProps
}: NativeButtonProps) {
    return (
        <button
            {...buttonProps}
            type={type}
            className={buttonClasses(variant, size, fullWidth, className)}
        >
            {children}
        </button>
    );
}

function InertiaButtonLink({
    as: _as,
    children,
    className,
    disabled = false,
    fullWidth = false,
    onClick,
    size = 'md',
    tabIndex,
    variant = 'primary',
    ...linkProps
}: ButtonLinkProps) {
    return (
        <Link
            {...linkProps}
            aria-disabled={disabled || undefined}
            className={classNames(
                buttonClasses(variant, size, fullWidth, className),
                disabled && 'cursor-not-allowed opacity-55 active:scale-100',
            )}
            onClick={(event) => {
                if (disabled) {
                    event.preventDefault();
                    return;
                }

                onClick?.(event);
            }}
            tabIndex={tabIndex}
        >
            {children}
        </Link>
    );
}

export default function Button(props: ButtonProps) {
    return props.as === 'link' ? (
        <InertiaButtonLink {...props} />
    ) : (
        <NativeButton {...props} />
    );
}
