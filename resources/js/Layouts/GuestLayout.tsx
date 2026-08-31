import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';

import { useSupportedLocales } from '@/lib/format';

interface GuestLayoutProps {
    children: ReactNode;
}

interface GuestSharedProps {
    locale?: unknown;
    auth?: {
        user?: {
            locale?: unknown;
        } | null;
    };
}

export default function GuestLayout({ children }: GuestLayoutProps) {
    const { props } = usePage();
    const sharedProps = props as typeof props & GuestSharedProps;
    const supportedLocales: readonly string[] = useSupportedLocales();
    const requestedLocale =
        sharedProps.locale ?? sharedProps.auth?.user?.locale;
    const locale =
        typeof requestedLocale === 'string' &&
        supportedLocales.includes(requestedLocale)
            ? requestedLocale
            : 'ar';
    const direction = locale === 'ar' ? 'rtl' : 'ltr';

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
    }, [direction, locale]);

    return (
        <div
            className="relative min-h-dvh overflow-hidden bg-[var(--surface-subtle)] text-[var(--ink)]"
            dir={direction}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 top-0 h-1 bg-[var(--brand)]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute start-[8%] top-[10%] size-48 rounded-full border border-[var(--line)] opacity-60 sm:size-72"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute bottom-[8%] end-[6%] h-44 w-28 border-e border-b border-[var(--line)] opacity-70 sm:h-64 sm:w-40"
            />
            <main className="relative flex min-h-dvh w-full items-center justify-center px-4 py-10 sm:px-6">
                <div className="w-full max-w-md rounded-[var(--radius-xl)] border border-[var(--line)] bg-[var(--surface-raised)] p-6 shadow-[var(--shadow-float)] sm:p-8">
                    <div
                        className="mb-7 flex size-11 items-center justify-center rounded-[var(--radius-md)] bg-[var(--brand-soft)] text-[var(--brand-strong)]"
                        aria-hidden="true"
                    >
                        <svg className="size-6" fill="none" viewBox="0 0 24 24">
                            <path d="m4 6.5 8-3 8 3-8 3-8-3Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
                            <path d="M7 8.2v5.6c0 1.4 2.2 2.7 5 2.7s5-1.3 5-2.7V8.2M20 7v6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
                        </svg>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
