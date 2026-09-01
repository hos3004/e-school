import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    type ChangeEvent,
    type PropsWithChildren,
    type ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';

import NotificationBell from '@/Components/NotificationBell';
import { useSupportedLocales } from '@/lib/format';
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
    | 'messages'
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
        { href: '/messages', labelKey: 'navigation.messages', icon: 'messages' },
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
        { href: '/messages', labelKey: 'navigation.messages', icon: 'messages' },
        { href: '/teacher/notifications', labelKey: 'navigation.notifications', icon: 'notifications' },
    ],
    guardian: [
        { href: '/guardian', labelKey: 'navigation.dashboard', icon: 'home' },
        { href: '/guardian/notifications', labelKey: 'navigation.notifications', icon: 'notifications' },
    ],
};

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
        case 'messages':
            return (
                <svg {...commonProps}>
                    <path d="M4 5.5h16v11H9l-5 3v-14Z" />
                    <path d="M8 10h8M8 13h5" />
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
    const navigationTriggerRef = useRef<HTMLButtonElement>(null);
    const navigationDialogRef = useRef<HTMLElement>(null);
    const localeOptions = useSupportedLocales();

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

    useEffect(() => {
        if (!isNavigationOpen) {
            return;
        }

        const dialog = navigationDialogRef.current;
        const navigationTrigger = navigationTriggerRef.current;
        const previousOverflow = document.body.style.overflow;
        const focusableSelector =
            'a[href], button:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

        document.body.style.overflow = 'hidden';
        const focusableElements = dialog
            ? Array.from(dialog.querySelectorAll<HTMLElement>(focusableSelector))
            : [];
        focusableElements[0]?.focus();

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                setIsNavigationOpen(false);
                return;
            }

            if (event.key !== 'Tab' || focusableElements.length === 0) {
                return;
            }

            const first = focusableElements[0];
            const last = focusableElements.at(-1);

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = previousOverflow;
            navigationTrigger?.focus();
        };
    }, [isNavigationOpen]);

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
            className="flex flex-col gap-1.5 p-4"
        >
            {navigationItems.map((item) => {
                const active = isActive(item.href);

                return (
                    <Link
                        key={item.href}
                        aria-current={active ? 'page' : undefined}
                        className={[
                            'group relative flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] px-3.5 py-2.5 text-sm font-medium transition-[color,background-color,box-shadow] duration-150',
                            focusRing,
                            active
                                ? 'bg-[var(--brand-soft)] text-[var(--brand-strong)] shadow-[inset_3px_0_0_var(--brand)] rtl:shadow-[inset_-3px_0_0_var(--brand)]'
                                : 'text-[var(--ink-soft)] hover:bg-[var(--surface-muted)] hover:text-[var(--ink)]',
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
            className="min-h-dvh bg-[var(--surface-subtle)] text-[var(--ink)]"
            dir={direction}
        >
            {title ? <Head title={title} /> : null}

            <a
                className={[
                    'fixed start-4 top-4 z-[70] -translate-y-24 rounded-[var(--radius-md)] bg-[var(--brand)] px-4 py-2 font-semibold text-[var(--ink-inverse)] shadow-[var(--shadow-float)] transition-transform focus:translate-y-0',
                    focusRing,
                ].join(' ')}
                href="#main-content"
            >
                {t('accessibility.skip_to_content')}
            </a>

            <header className="sticky top-0 z-40 border-b border-[var(--line)] bg-[color:color-mix(in_srgb,var(--surface)_94%,transparent)] backdrop-blur-md">
                <div className="mx-auto flex min-h-[4.5rem] max-w-[100rem] items-center gap-3 px-4 py-2 sm:px-6">
                    <button
                        aria-controls="mobile-navigation"
                        aria-expanded={isNavigationOpen}
                        aria-label={t('navigation.open')}
                        className={[
                            'inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--surface)] text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.05)] hover:bg-[var(--surface-muted)] lg:hidden',
                            focusRing,
                        ].join(' ')}
                        onClick={() => setIsNavigationOpen(true)}
                        ref={navigationTriggerRef}
                        type="button"
                    >
                        <MenuIcon />
                    </button>

                    <Link
                        className={[
                            'flex min-w-0 shrink items-center gap-3 rounded-[var(--radius-md)] text-base font-semibold text-[var(--ink)] sm:text-lg',
                            focusRing,
                        ].join(' ')}
                        href={homeHref}
                    >
                        <span
                            aria-hidden="true"
                            className="hidden size-9 shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-[var(--brand)] text-[var(--ink-inverse)] shadow-[0_1px_2px_rgb(20_37_54/0.14)] sm:flex"
                        >
                            <svg className="size-5" fill="none" viewBox="0 0 24 24">
                                <path d="m4 6.5 8-3 8 3-8 3-8-3Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.8" />
                                <path d="M7 8.2v5.6c0 1.4 2.2 2.7 5 2.7s5-1.3 5-2.7V8.2M20 7v6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" />
                            </svg>
                        </span>
                        <span className="truncate">{t('app.name')}</span>
                    </Link>

                    <div className="ms-auto flex min-w-0 items-center gap-2 sm:gap-4">
                        <span className="hidden max-w-48 items-center gap-2.5 rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--surface)] px-2.5 py-1.5 text-sm font-medium text-[var(--ink-soft)] sm:flex">
                            <span aria-hidden="true" className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-xs font-semibold text-[var(--brand-strong)]">
                                {user.name.trim().charAt(0).toUpperCase()}
                            </span>
                            <span className="truncate">{user.name}</span>
                        </span>

                        <label className="sr-only" htmlFor="app-locale">
                            {t('common.language')}
                        </label>
                        <select
                            aria-label={t('common.language')}
                            className={[
                                'min-h-11 rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-3 text-sm font-medium text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)]',
                                focusRing,
                            ].join(' ')}
                            id="app-locale"
                            onChange={changeLocale}
                            value={locale}
                        >
                            {localeOptions.map((option) => (
                                <option key={option} value={option}>
                                    {t(`locales.${option}`)}
                                </option>
                            ))}
                        </select>
                        <NotificationBell
                            notificationsUrl={`/${role}/notifications`}
                        />
                    </div>
                </div>
            </header>

            <div className="mx-auto grid max-w-[100rem] lg:grid-cols-[17.5rem_minmax(0,1fr)]">
                <aside className="hidden min-h-[calc(100dvh-4.5rem)] border-e border-[var(--line)] bg-[var(--surface)] lg:block">
                    {renderNavigation()}
                </aside>

                <main
                    className="min-w-0 px-4 py-6 sm:px-6 lg:px-8 lg:py-9 xl:px-10"
                    id="main-content"
                    tabIndex={-1}
                >
                    {page.props.flash?.success ? (
                        <div
                            className="mb-6 rounded-[var(--radius-md)] border border-[color:var(--success)]/30 bg-[var(--success-soft)] px-4 py-3 text-sm font-medium text-[var(--success)] shadow-[0_1px_2px_rgb(20_37_54/0.04)]"
                            role="status"
                        >
                            {page.props.flash.success}
                        </div>
                    ) : null}

                    {page.props.flash?.error ? (
                        <div
                            className="mb-6 rounded-[var(--radius-md)] border border-[color:var(--danger)]/30 bg-[var(--danger-soft)] px-4 py-3 text-sm font-medium text-[var(--danger)] shadow-[0_1px_2px_rgb(20_37_54/0.04)]"
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
                        className="absolute inset-0 h-full w-full cursor-default bg-[var(--surface-inverse)]/55 backdrop-blur-[2px]"
                        onClick={() => setIsNavigationOpen(false)}
                        tabIndex={-1}
                        type="button"
                    />

                    <aside
                        aria-label={t('navigation.primary')}
                        aria-modal="true"
                        className="absolute inset-y-0 start-0 w-[min(20rem,88vw)] overflow-y-auto border-e border-[var(--line)] bg-[var(--surface)] shadow-[var(--shadow-float)]"
                        id="mobile-navigation"
                        ref={navigationDialogRef}
                        role="dialog"
                    >
                        <div className="flex min-h-[4.5rem] items-center gap-3 border-b border-[var(--line)] px-4">
                            <span aria-hidden="true" className="flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-[var(--brand)] text-[var(--ink-inverse)]">
                                <svg className="size-5" fill="none" viewBox="0 0 24 24">
                                    <path d="m4 6.5 8-3 8 3-8 3-8-3Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.8" />
                                    <path d="M7 8.2v5.6c0 1.4 2.2 2.7 5 2.7s5-1.3 5-2.7V8.2M20 7v6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" />
                                </svg>
                            </span>
                            <span className="min-w-0 flex-1 truncate font-semibold">
                                {t('app.name')}
                            </span>
                            <button
                                aria-label={t('navigation.close')}
                                className={[
                                    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-md)] border border-transparent hover:border-[var(--line)] hover:bg-[var(--surface-muted)]',
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
