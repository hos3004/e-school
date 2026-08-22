import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/lib/i18n';
import type { AppPageProps } from '@/types';

interface LoginProps extends AppPageProps {
    action?: string;
}

interface LoginForm {
    login: string;
    password: string;
    remember: boolean;
}

const inputClasses =
    'min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/50 bg-[var(--surface)] ps-3 pe-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

export default function Login({
    action = '/login',
    flash,
}: LoginProps) {
    const t = useI18n();
    const form = useForm<LoginForm>({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(action, {
            preserveScroll: true,
            onFinish: () => form.reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.login.title')} />

            <div className="text-center">
                <p className="text-sm font-bold text-[var(--brand)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-bold text-[var(--ink)]">
                    {t('auth.login.title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)]">
                    {t('auth.login.subtitle')}
                </p>
            </div>

            {flash.success ? (
                <div
                    className="mt-6 rounded-lg border border-[var(--success)] bg-[var(--surface-muted)] ps-4 pe-4 py-3 text-sm text-[var(--ink)]"
                    role="status"
                >
                    {flash.success}
                </div>
            ) : null}

            {flash.error ? (
                <div
                    className="mt-6 rounded-lg border border-[var(--danger)] bg-[var(--surface-muted)] ps-4 pe-4 py-3 text-sm text-[var(--ink)]"
                    role="alert"
                >
                    {flash.error}
                </div>
            ) : null}

            <form className="mt-8 space-y-5" onSubmit={submit}>
                <div>
                    <label
                        className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                        htmlFor="login"
                    >
                        {t('auth.login.identifier')}
                    </label>
                    <input
                        aria-describedby={
                            form.errors.login ? 'login-error' : undefined
                        }
                        aria-invalid={Boolean(form.errors.login)}
                        autoComplete="username"
                        autoFocus
                        className={inputClasses}
                        id="login"
                        name="login"
                        onChange={(event) =>
                            form.setData('login', event.target.value)
                        }
                        placeholder={t('auth.login.identifier_placeholder')}
                        required
                        type="text"
                        value={form.data.login}
                    />
                    {form.errors.login ? (
                        <p
                            className="mt-2 text-sm font-medium text-[var(--danger)]"
                            id="login-error"
                            role="alert"
                        >
                            {form.errors.login}
                        </p>
                    ) : null}
                </div>

                <div>
                    <label
                        className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                        htmlFor="password"
                    >
                        {t('auth.login.password')}
                    </label>
                    <input
                        aria-describedby={
                            form.errors.password
                                ? 'password-error'
                                : undefined
                        }
                        aria-invalid={Boolean(form.errors.password)}
                        autoComplete="current-password"
                        className={inputClasses}
                        id="password"
                        name="password"
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        required
                        type="password"
                        value={form.data.password}
                    />
                    {form.errors.password ? (
                        <p
                            className="mt-2 text-sm font-medium text-[var(--danger)]"
                            id="password-error"
                            role="alert"
                        >
                            {form.errors.password}
                        </p>
                    ) : null}
                </div>

                <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg text-sm font-medium text-[var(--ink)] focus-within:ring-2 focus-within:ring-[var(--brand)] focus-within:ring-offset-2 focus-within:ring-offset-[var(--surface)]">
                    <input
                        checked={form.data.remember}
                        className="size-5 rounded border-[var(--ink-muted)] text-[var(--brand)] accent-[var(--brand)]"
                        name="remember"
                        onChange={(event) =>
                            form.setData('remember', event.target.checked)
                        }
                        type="checkbox"
                    />
                    <span>{t('auth.login.remember')}</span>
                </label>

                <Button
                    disabled={form.processing}
                    fullWidth
                    type="submit"
                >
                    {form.processing
                        ? t('auth.login.submitting')
                        : t('auth.login.submit')}
                </Button>
            </form>
        </GuestLayout>
    );
}
