import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    type ChangeEvent,
    type PropsWithChildren,
    type ReactNode,
    useEffect,
    useState,
} from 'react';

import NotificationBell from '@/Components/NotificationBell';
import { useI18n } from '@/lib/i18n';

export type AppRole = 'student' | 'teacher' | 'guardian';

type Locale = 'ar' | 'en' | 'fr';

export type NavigationIconName =
    | 'home'
    | 'schedule'
    | 'assignments'
    | 'reports'
    | 'postponements'
    | 'profile'
    | 'programs'
    | 'group'
    | 'groups'
    | 'students'
    | 'availability'
    | 'earnings'
    | 'notifications';

interface AuthenticatedUser {
    id: number | string;
    name: string;
    email: string;
    locale: Locale;
    roles: string[];
}

type FeatureKey = 'payroll';

interface AppPageProps {
    [key: string]: unknown;
    auth: {
        user: AuthenticatedUser;
    };
    features?: Partial<Record<FeatureKey, boolean>>;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    locale?: string;
}

interface AppLayoutProps extends PropsWithChildren {
    /** Optional heading rendered above the page body, before flash messages. */
    header?: ReactNode;
    role?: AppRole;
    title?: string;
    localeEndpoint?: string;
}

interface NavigationItem {
    href: string;
    labelKey: string;
    icon: NavigationIconName;
    /** يظهر العنصر فقط حين يكون هذا المفتاح مفعّلًا في `features`. */
    feature?: FeatureKey;
}

const navigationByRole: Record<AppRole, readonly NavigationItem[]> = {
    student: [
        { href: '/student', labelKey: 'navigation.dashboard', icon: 'home' },
        { href: '/student/schedule', labelKey: 'navigation.schedule', icon: 'schedule' },
        { href: '/student/assignments', labelKey: 'navigation.assignments', icon: 'assignments' },
        { href: '/student/reports', labelKey: 'navigation.reports', icon: 'reports' },
        { href: '/student/programs', labelKey: 'navigation.programs', icon: 'programs' },
        { href: '/student/group', labelKey: 'navigation.group', icon: 'group' },
        { href: '/student/profile', labelKey: 'navigation.profile', icon: 'profile' },
        { href: '/student/notifications', labelKey: 'navigation.notifications', icon: 'notifications' },
    ],
    teacher: [
        { href: '/teacher', labelKey: 'navigation.dashboard', icon: 'home' },
        { href: '/teacher/schedule', labelKey: 'navigation.schedule', icon: 'schedule' },
        {
            href: '/teacher/postponements',
            labelKey: 'navigation.postponements',
            icon: 'postponements',
        },
        { href: '/teacher/groups', labelKey: 'navigation.groups', icon: 'groups' },
        { href: '/teacher/students', labelKey: 'navigation.students', icon: 'students' },
        { href: '/teacher/availability', labelKey: 'navigation.availability', icon: 'availability' },
        {
            href: '/teacher/earnings',
            labelKey: 'navigation.earnings',
            icon: 'earnings',
            feature: 'payroll',
        },
        { href: '/teacher/profile', labelKey: 'navigation.profile', icon: 'profile' },
        { href: '/teacher/notifications', labelKey: 'navigation.notifications', icon: 'notifications' },
    ],
    guardian: [
        { href: '/guardian', labelKey: 'navigation.dashboard', icon: 'home' },
        { href: '/guardian/notifications', labelKey: 'navigation.notifications', icon: 'notifications' },
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

function NavigationIcon({ name }: { name: NavigationIconName }) {
    const className = 'size-5 shrink-0';
    const commonProps = {
        'aria-hidden': true,
        className,
        fill: 'none',
        stroke: 'currentColor',
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        strokeWidth: 1.8,
        viewBox: '0 0 24 24',
    } as const;

    switch (name) {
        case 'home':
            return (
                <svg {...commonProps}>
                    <path d="M3 11.5 12 3l9 8.5" />
                    <path d="M5.5 9.8V21h13V9.8" />
                    <path d="M10 21v-6h4v6" />
                </svg>
            );
        case 'schedule':
            return (
                <svg {...commonProps}>
                    <rect height="16" rx="2" width="16" x="4" y="5" />
                    <path d="M4 10h16M9 3v4M15 3v4" />
                </svg>
            );
        case 'earnings':
            return (
                <svg {...commonProps}>
                    <circle cx="12" cy="12" r="8" />
                    <path d="M14.5 9.5a2.5 2.5 0 0 0-5 0c0 3 5 1.5 5 4.5a2.5 2.5 0 0 1-5 0M12 6.5v11" />
                </svg>
            );
        case 'assignments':
            return (
                <svg {...commonProps}>
                    <rect height="16" rx="2" width="12" x="6" y="5" />
                    <path d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 11h6M9 15h4" />
                </svg>
            );
        case 'reports':
            return (
                <svg {...commonProps}>
                    <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
                </svg>
            );
        case 'postponements':
            return (
                <svg {...commonProps}>
                    <circle cx="12" cy="13" r="8" />
                    <path d="M12 9v4l2.5 2.5M9 2h6" />
                </svg>
            );
        case 'profile':
            return (
                <svg {...commonProps}>
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
                </svg>
            );
        case 'programs':
            return (
                <svg {...commonProps}>
                    <path d="M4 19V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13" />
                    <path d="M3 19h18M8 8h8M8 12h8" />
                </svg>
            );
        case 'group':
        case 'groups':
            return (
                <svg {...commonProps}>
                    <circle cx="9" cy="9" r="3.2" />
                    <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
                    <circle cx="17" cy="10" r="2.6" />
                    <path d="M14.8 19a4.6 4.6 0 0 1 5.7-4.4" />
                </svg>
            );
        case 'students':
            return (
                <svg {...commonProps}>
                    <path d="m12 4 10 5-10 5L2 9l10-5Z" />
                    <path d="M6.5 11.5V16c0 1.4 2.5 2.6 5.5 2.6s5.5-1.2 5.5-2.6v-4.5" />
                </svg>
            );
        case 'availability':
            return (
                <svg {...commonProps}>
                    <circle cx="12" cy="12" r="9" />
                    <path d="m8.5 12.5 2.5 2.5 4.5-5" />
                </svg>
            );
        case 'notifications':
            return (
                <svg {...commonProps}>
                    <path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z" />
                    <path d="M10.5 19a1.8 1.8 0 0 0 3 0" />
                </svg>
            );
    }
}

export default function AppLayout({
    children,
    header,
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
    const features = page.props.features ?? {};
    const navigationItems = (
        activeRole ? navigationByRole[activeRole] : []
    ).filter(
        (item) => item.feature === undefined || features[item.feature] === true,
    );
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
                            'flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold transition-colors',
                            focusRing,
                            active
                                ? 'bg-[var(--brand)] text-[var(--surface)]'
                                : 'text-[var(--ink)] hover:bg-[var(--surface-muted)]',
                        ].join(' ')}
                        href={item.href}
                    >
                        <NavigationIcon name={item.icon} />
                        <span className="min-w-0 truncate">
                            {t(item.labelKey)}
                        </span>
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
                        <NotificationBell
                            notificationsUrl={`/${role}/notifications`}
                        />
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

                    {header ? <div className="mb-6">{header}</div> : null}

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
