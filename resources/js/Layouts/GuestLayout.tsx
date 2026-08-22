import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';

type SupportedLocale = 'ar' | 'en' | 'fr';

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

function isSupportedLocale(locale: unknown): locale is SupportedLocale {
    return locale === 'ar' || locale === 'en' || locale === 'fr';
}

export default function GuestLayout({ children }: GuestLayoutProps) {
    const { props } = usePage();
    const sharedProps = props as typeof props & GuestSharedProps;
    const requestedLocale =
        sharedProps.locale ?? sharedProps.auth?.user?.locale;
    const locale = isSupportedLocale(requestedLocale)
        ? requestedLocale
        : 'ar';
    const direction = locale === 'ar' ? 'rtl' : 'ltr';

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
    }, [direction, locale]);

    return (
        <div
            className="min-h-dvh bg-[var(--surface)] text-[var(--ink)]"
            dir={direction}
        >
            <main className="flex min-h-dvh w-full items-center justify-center py-8 ps-4 pe-4 sm:ps-6 sm:pe-6">
                <div className="w-full max-w-md rounded-2xl border border-[color:color-mix(in_srgb,var(--ink)_14%,transparent)] bg-[var(--surface)] p-6 shadow-[0_24px_64px_color-mix(in_srgb,var(--ink)_12%,transparent)] sm:p-8">
                    <div
                        className="mb-6 h-1 w-12 rounded-full bg-[var(--brand)]"
                        aria-hidden="true"
                    />
                    {children}
                </div>
            </main>
        </div>
    );
}
