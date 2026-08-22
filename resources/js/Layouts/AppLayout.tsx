import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    type ChangeEvent,
    type PropsWithChildren,
    useEffect,
    useState,
} from 'react';

import { useI18n } from '@/lib/i18n';

export type AppRole = 'student' | 'teacher' | 'guardian';

type Locale = 'ar' | 'en' | 'fr';

interface AuthenticatedUser {
    id: number | string;
    name: string;
    email: string;
    locale: Locale;
    roles: string[];
}

interface AppPageProps {
    [key: string]: unknown;
    auth: {
        user: AuthenticatedUser;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    locale?: string;
}

interface AppLayoutProps extends PropsWithChildren {
    role?: AppRole;
    title?: string;
    localeEndpoint?: string;
}

interface NavigationItem {
    href: string;
    labelKey: string;
}

const navigationByRole: Record<AppRole, readonly NavigationItem[]> = {
    student: [
        { href: '/student', labelKey: 'navigation.dashboard' },
        { href: '/student/schedule', labelKey: 'navigation.schedule' },
        { href: '/student/assignments', labelKey: 'navigation.assignments' },
        { href: '/student/reports', labelKey: 'navigation.reports' },
    ],
    teacher: [
        { href: '/teacher', labelKey: 'navigation.dashboard' },
        { href: '/teacher/schedule', labelKey: 'navigation.schedule' },
        {
            href: '/teacher/postponements',
            labelKey: 'navigation.postponements',
        },
    ],
    guardian: [
        { href: '/guardian', labelKey: 'navigation.dashboard' },
    ],
};

const localeOptions: ReadonlyArray<{ value: Locale; labelKey: string }> = [
    { value: 'ar', labelKey: 'locales.ar' },
    { value: 'en', labelKey: 'locales.en' },
    { value: 'fr', labelKey: 'locales.fr' },
];

const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

function isLocale(value: string): value is Locale {
    return value === 'ar' || value === 'en' || value === 'fr';
}

function normalizeRole(value: string): AppRole | null {
    const normalized = value.toLowerCase();

    if (
        normalized === 'student' ||
        normalized === 'teacher' ||
        normalized === 'guardian'
    ) {
        return normalized;
    }

    return null;
}

function MenuIcon() {
    return (
        <svg
            aria-hidden="true"
            className="size-6"
            fill="none"
            viewBox="0 0 24 24"
        >
            <path
                d="M4 6h16M4 12h16M4 18h16"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="2"
            />
        </svg>
    );
}

function CloseIcon() {
    return (
        <svg
            aria-hidden="true"
            className="size-6"
            fill="none"
            viewBox="0 0 24 24"
        >
            <path
                d="m6 6 12 12M18 6 6 18"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="2"
            />
        </svg>
    );
}

export default function AppLayout({
    children,
    role,
    title,
    localeEndpoint = '/locale',
}: AppLayoutProps) {
    const page = usePage<AppPageProps>();
    const t = useI18n();
    const { user } = page.props.auth;
    const [isNavigationOpen, setIsNavigationOpen] = useState(false);

    const requestedLocale = page.props.locale ?? user.locale;
    const locale: Locale = isLocale(requestedLocale) ? requestedLocale : 'ar';
    const direction = locale === 'ar' ? 'rtl' : 'ltr';

    const roleFromUser = user.roles
        .map(normalizeRole)
        .find((candidate): candidate is AppRole => candidate !== null);
    const activeRole = role ?? roleFromUser;
    const navigationItems = activeRole ? navigationByRole[activeRole] : [];
    const homeHref = navigationItems[0]?.href ?? '/';
    const currentPath = page.url.split(/[?#]/, 1)[0] ?? page.url;

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
    }, [direction, locale]);

    useEffect(() => {
        setIsNavigationOpen(false);
    }, [page.url]);

    const isActive = (href: string) => {
        if (href === homeHref) {
            return currentPath === href || currentPath === href + '/';
        }

        return currentPath === href || currentPath.startsWith(href + '/');
    };

    const changeLocale = (event: ChangeEvent<HTMLSelectElement>) => {
        const nextLocale = event.target.value;

        if (!isLocale(nextLocale) || nextLocale === locale) {
            return;
        }

        document.documentElement.lang = nextLocale;
        document.documentElement.dir = nextLocale === 'ar' ? 'rtl' : 'ltr';

        router.post(
            localeEndpoint,
            { locale: nextLocale },
            {
                preserveScroll: true,
                onError: () => {
                    document.documentElement.lang = locale;
                    document.documentElement.dir = direction;
                },
            },
        );
    };

    const renderNavigation = () => (
        <nav
            aria-label={t('navigation.primary')}
            className="flex flex-col gap-1 p-3"
        >
            {navigationItems.map((item) => {
                const active = isActive(item.href);

                return (
                    <Link
                        key={item.href}
                        aria-current={active ? 'page' : undefined}
                        className={[
                            'rounded-lg px-4 py-3 text-sm font-semibold transition-colors',
                            focusRing,
                            active
                                ? 'bg-[var(--brand)] text-[var(--surface)]'
                                : 'text-[var(--ink)] hover:bg-[var(--surface-muted)]',
                        ].join(' ')}
                        href={item.href}
                    >
                        {t(item.labelKey)}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div
            className="min-h-dvh bg-[var(--surface-muted)] text-[var(--ink)]"
            dir={direction}
        >
            {title ? <Head title={title} /> : null}

            <a
                className={[
                    'fixed start-4 top-4 z-[70] -translate-y-24 rounded-lg bg-[var(--brand)] px-4 py-2 font-semibold text-[var(--surface)] transition-transform focus:translate-y-0',
                    focusRing,
                ].join(' ')}
                href="#main-content"
            >
                {t('accessibility.skip_to_content')}
            </a>

            <header className="sticky top-0 z-40 border-b border-[var(--ink-muted)]/30 bg-[var(--surface)]">
                <div className="mx-auto flex min-h-16 max-w-screen-2xl items-center gap-3 px-4 py-2 sm:px-6">
                    <button
                        aria-controls="mobile-navigation"
                        aria-expanded={isNavigationOpen}
                        aria-label={t('navigation.open')}
                        className={[
                            'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-[var(--ink)] hover:bg-[var(--surface-muted)] lg:hidden',
                            focusRing,
                        ].join(' ')}
                        onClick={() => setIsNavigationOpen(true)}
                        type="button"
                    >
                        <MenuIcon />
                    </button>

                    <Link
                        className={[
                            'min-w-0 shrink truncate rounded-md text-base font-bold sm:text-lg',
                            focusRing,
                        ].join(' ')}
                        href={homeHref}
                    >
                        {t('app.name')}
                    </Link>

                    <div className="ms-auto flex min-w-0 items-center gap-2 sm:gap-4">
                        <span className="max-w-28 truncate text-sm font-semibold sm:max-w-48">
                            {user.name}
                        </span>

                        <label className="sr-only" htmlFor="app-locale">
                            {t('common.language')}
                        </label>
                        <select
                            aria-label={t('common.language')}
                            className={[
                                'min-h-11 rounded-lg border border-[var(--ink-muted)]/40 bg-[var(--surface)] px-3 text-sm font-semibold text-[var(--ink)]',
                                focusRing,
                            ].join(' ')}
                            id="app-locale"
                            onChange={changeLocale}
                            value={locale}
                        >
                            {localeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {t(option.labelKey)}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </header>

            <div className="mx-auto grid max-w-screen-2xl lg:grid-cols-[16rem_minmax(0,1fr)]">
                <aside className="hidden min-h-[calc(100dvh-4rem)] border-e border-[var(--ink-muted)]/30 bg-[var(--surface)] lg:block">
                    {renderNavigation()}
                </aside>

                <main
                    className="min-w-0 px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    id="main-content"
                    tabIndex={-1}
                >
                    {page.props.flash?.success ? (
                        <div
                            className="mb-6 rounded-lg border border-[var(--success)] bg-[var(--surface)] px-4 py-3 text-sm font-medium"
                            role="status"
                        >
                            {page.props.flash.success}
                        </div>
                    ) : null}

                    {page.props.flash?.error ? (
                        <div
                            className="mb-6 rounded-lg border border-[var(--danger)] bg-[var(--surface)] px-4 py-3 text-sm font-medium"
                            role="alert"
                        >
                            {page.props.flash.error}
                        </div>
                    ) : null}

                    {children}
                </main>
            </div>

            {isNavigationOpen ? (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button
                        aria-label={t('navigation.close')}
                        className="absolute inset-0 h-full w-full cursor-default bg-[var(--ink)]/50"
                        onClick={() => setIsNavigationOpen(false)}
                        type="button"
                    />

                    <aside
                        aria-label={t('navigation.primary')}
                        className="absolute inset-y-0 start-0 w-[min(20rem,88vw)] overflow-y-auto border-e border-[var(--ink-muted)]/30 bg-[var(--surface)]"
                        id="mobile-navigation"
                    >
                        <div className="flex min-h-16 items-center gap-3 border-b border-[var(--ink-muted)]/30 px-4">
                            <span className="min-w-0 flex-1 truncate font-bold">
                                {t('app.name')}
                            </span>
                            <button
                                aria-label={t('navigation.close')}
                                className={[
                                    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-[var(--surface-muted)]',
                                    focusRing,
                                ].join(' ')}
                                onClick={() => setIsNavigationOpen(false)}
                                type="button"
                            >
                                <CloseIcon />
                            </button>
                        </div>
                        {renderNavigation()}
                    </aside>
                </div>
            ) : null}
        </div>
    );
}
