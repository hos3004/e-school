import { Head, router, useForm } from '@inertiajs/react';

import Button from '@/Components/Button';
import Card, {
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import AppLayout from '@/Layouts/AppLayout';
import {
    formatDateTime,
    formatPercent,
    useLocale,
    useSupportedLocales,
} from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import { StudentPageHero } from '@/Pages/Student/Partials/StudentUi';
import type { LoadablePageProps } from '@/types';

interface StudentSummary {
    name: string;
    code: string;
    email: string;
    phone?: string | null;
    country?: string | null;
    city?: string | null;
    dateOfBirth?: string | null;
}

interface AccountSettings {
    name: string;
    username: string | null;
    email: string;
    phone: string | null;
    phoneCountry: string | null;
    locale: string;
    timezone: string;
    status: string;
    lastLoginAt: string | null;
}

interface Props extends LoadablePageProps {
    student?: StudentSummary | null;
    account?: AccountSettings | null;
    timezones?: readonly string[];
    attendanceRate?: number | null;
    updateUrl?: string;
    passwordUrl?: string;
}

const fieldClasses =
    'mt-2 min-h-12 w-full rounded-xl border border-[color:var(--ink-muted)]/45 bg-[var(--surface)] px-3 text-base text-[var(--ink)] shadow-sm ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2';

export default function Profile({
    student = null,
    account = null,
    timezones = [],
    attendanceRate = null,
    updateUrl = '',
    passwordUrl = '',
    loading = false,
    error = null,
}: Props) {
    const t = useI18n();
    const locale = useLocale();
    const localeOptions = useSupportedLocales();

    const profileForm = useForm({
        name: account?.name ?? '',
        phone: account?.phone ?? '',
        locale: account?.locale ?? 'ar',
        timezone: account?.timezone ?? 'UTC',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const readOnlyFields: ReadonlyArray<readonly [string, string]> = student
        ? [
              ['name', student.name],
              ['code', student.code],
              ['email', student.email],
              ['phone', student.phone || t('common.not_available')],
              ['country', student.country || t('common.not_available')],
              ['city', student.city || t('common.not_available')],
          ]
        : [];

    return (
        <AppLayout role="student">
            <Head title={t('student.profile.title')} />
            <StudentPageHero
                className="mb-8"
                title={t('student.profile.title')}
                subtitle={t('student.profile.subtitle')}
            />

            {loading ? (
                <LoadingState label={t('student.profile.loading')} rows={4} />
            ) : error ? (
                <ErrorState message={error} onRetry={() => router.reload()} />
            ) : !student ? (
                <EmptyState
                    title={t('student.profile.empty_title')}
                    description={t('student.profile.empty_description')}
                />
            ) : (
                <div className="grid gap-6 xl:grid-cols-[minmax(17rem,0.8fr)_minmax(0,1.6fr)]">
                    <Card
                        className="self-start border-[color:var(--brand)]/25 bg-[linear-gradient(145deg,color-mix(in_srgb,var(--brand)_8%,var(--surface)),var(--surface)_60%)] shadow-md"
                        padding="lg"
                    >
                        <CardHeader className="mb-5">
                            <CardTitle>
                                {t('student.profile.summary_title')}
                            </CardTitle>
                            <CardDescription>
                                {t('student.profile.summary_description')}
                            </CardDescription>
                        </CardHeader>

                        <dl className="grid gap-4 sm:grid-cols-2">
                            {readOnlyFields.map(([key, value]) => (
                                <div
                                    className="rounded-xl bg-[var(--surface)]/80 px-4 py-3 shadow-sm"
                                    key={key}
                                >
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t(`student.profile.fields.${key}`)}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                            <div className="rounded-xl bg-[var(--surface)]/80 px-4 py-3 shadow-sm">
                                <dt className="text-xs text-[var(--ink-muted)]">
                                    {t(
                                        'student.profile.fields.attendance_rate',
                                    )}
                                </dt>
                                <dd className="mt-1 font-semibold text-[var(--ink)]">
                                    {attendanceRate === null
                                        ? t('common.not_available')
                                        : formatPercent(attendanceRate, locale)}
                                </dd>
                            </div>
                            {account?.lastLoginAt ? (
                                <div className="rounded-xl bg-[var(--surface)]/80 px-4 py-3 shadow-sm">
                                    <dt className="text-xs text-[var(--ink-muted)]">
                                        {t('account.last_login')}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-[var(--ink)]">
                                        {formatDateTime(
                                            account.lastLoginAt,
                                            locale,
                                        )}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    </Card>

                    <Card
                        className="border-[color:var(--ink-muted)]/15 shadow-md"
                        padding="lg"
                    >
                        <CardHeader className="mb-5">
                            <CardTitle>{t('account.edit_title')}</CardTitle>
                            <CardDescription>
                                {t('account.edit_description')}
                            </CardDescription>
                        </CardHeader>

                        <form
                            className="grid gap-4 sm:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                profileForm.patch(updateUrl, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <label className="text-sm font-semibold">
                                {t('account.fields.name')}
                                <input
                                    className={fieldClasses}
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    type="text"
                                    value={profileForm.data.name}
                                />
                                {profileForm.errors.name ? (
                                    <span className="mt-1 block text-xs text-[var(--danger)]">
                                        {profileForm.errors.name}
                                    </span>
                                ) : null}
                            </label>

                            <label className="text-sm font-semibold">
                                {t('account.fields.phone')}
                                <input
                                    className={fieldClasses}
                                    dir="ltr"
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    type="tel"
                                    value={profileForm.data.phone ?? ''}
                                />
                                {profileForm.errors.phone ? (
                                    <span className="mt-1 block text-xs text-[var(--danger)]">
                                        {profileForm.errors.phone}
                                    </span>
                                ) : null}
                            </label>

                            <label className="text-sm font-semibold">
                                {t('common.language')}
                                <select
                                    className={fieldClasses}
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'locale',
                                            event.target.value,
                                        )
                                    }
                                    value={profileForm.data.locale}
                                >
                                    {localeOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {t(`locales.${option}`)}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="text-sm font-semibold">
                                {t('account.fields.timezone')}
                                <select
                                    className={fieldClasses}
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                    value={profileForm.data.timezone}
                                >
                                    {timezones.map((zone) => (
                                        <option key={zone} value={zone}>
                                            {zone}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <div className="sm:col-span-2">
                                <Button
                                    className="w-full sm:w-auto"
                                    disabled={profileForm.processing}
                                    type="submit"
                                >
                                    {profileForm.processing
                                        ? t('actions.saving')
                                        : t('account.save')}
                                </Button>
                            </div>
                        </form>

                        <p className="mt-4 text-xs leading-6 text-[var(--ink-muted)]">
                            {t('account.identity_locked_note')}
                        </p>
                    </Card>

                    <Card
                        className="border-[color:var(--ink-muted)]/15 shadow-md xl:col-start-2"
                        padding="lg"
                    >
                        <CardHeader className="mb-5">
                            <CardTitle>{t('account.password_title')}</CardTitle>
                            <CardDescription>
                                {t('account.password_description')}
                            </CardDescription>
                        </CardHeader>

                        <form
                            className="grid gap-4 sm:grid-cols-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                passwordForm.put(passwordUrl, {
                                    preserveScroll: true,
                                    onSuccess: () => passwordForm.reset(),
                                });
                            }}
                        >
                            <label className="text-sm font-semibold">
                                {t('account.fields.current_password')}
                                <input
                                    autoComplete="current-password"
                                    className={fieldClasses}
                                    onChange={(event) =>
                                        passwordForm.setData(
                                            'current_password',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    type="password"
                                    value={passwordForm.data.current_password}
                                />
                                {passwordForm.errors.current_password ? (
                                    <span className="mt-1 block text-xs text-[var(--danger)]">
                                        {passwordForm.errors.current_password}
                                    </span>
                                ) : null}
                            </label>

                            <label className="text-sm font-semibold">
                                {t('account.fields.new_password')}
                                <input
                                    autoComplete="new-password"
                                    className={fieldClasses}
                                    onChange={(event) =>
                                        passwordForm.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    type="password"
                                    value={passwordForm.data.password}
                                />
                                {passwordForm.errors.password ? (
                                    <span className="mt-1 block text-xs text-[var(--danger)]">
                                        {passwordForm.errors.password}
                                    </span>
                                ) : null}
                            </label>

                            <label className="text-sm font-semibold">
                                {t('account.fields.confirm_password')}
                                <input
                                    autoComplete="new-password"
                                    className={fieldClasses}
                                    onChange={(event) =>
                                        passwordForm.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    type="password"
                                    value={
                                        passwordForm.data.password_confirmation
                                    }
                                />
                            </label>

                            <div className="sm:col-span-3">
                                <Button
                                    className="w-full sm:w-auto"
                                    disabled={passwordForm.processing}
                                    type="submit"
                                    variant="secondary"
                                >
                                    {passwordForm.processing
                                        ? t('actions.saving')
                                        : t('account.change_password')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
