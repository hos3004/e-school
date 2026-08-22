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
        'bg-[var(--ink)] text-[var(--surface)] hover:opacity-90 active:opacity-80',
    secondary:
        'border border-[var(--ink-muted)] bg-[var(--surface)] text-[var(--ink)] hover:bg-[var(--surface-muted)]',
    danger:
        'border border-[var(--danger)] bg-[var(--surface)] text-[var(--ink)] hover:bg-[color-mix(in_srgb,var(--danger)_10%,var(--surface))]',
    ghost:
        'bg-transparent text-[var(--ink)] hover:bg-[var(--surface-muted)]',
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
        'inline-flex items-center justify-center gap-2 rounded-lg font-semibold',
        'transition-colors duration-150',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]',
        'focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]',
        'disabled:cursor-not-allowed disabled:opacity-60',
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
                disabled && 'pointer-events-none cursor-not-allowed opacity-60',
            )}
            onClick={(event) => {
                if (disabled) {
                    event.preventDefault();
                    return;
                }

                onClick?.(event);
            }}
            tabIndex={disabled ? -1 : tabIndex}
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
