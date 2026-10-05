import BrandLogo from "@/Components/BrandLogo";
import { useI18n } from "@/lib/i18n";
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
    const t = useI18n();
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
                    <a href="/" className="mb-7 block w-40">
                        <BrandLogo label={t('app.name')} />
                    </a>
                    {children}
                </div>
            </main>
        </div>
    );
}
