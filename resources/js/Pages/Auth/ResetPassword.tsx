import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/lib/i18n';
import type { AppPageProps } from '@/types';

interface ResetPasswordProps extends AppPageProps {
    token: string;
    email?: string;
}

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

const inputClasses =
    'min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/50 bg-[var(--surface)] ps-3 pe-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

export default function ResetPassword({
    token,
    email = '',
}: ResetPasswordProps) {
    const t = useI18n();
    const form = useForm<ResetPasswordForm>({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post('/reset-password', {
            preserveScroll: true,
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.reset_password.title')} />

            <div className="text-center">
                <p className="text-sm font-bold text-[var(--brand)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-bold text-[var(--ink)]">
                    {t('auth.reset_password.title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)]">
                    {t('auth.reset_password.subtitle')}
                </p>
            </div>

            <form className="mt-8 space-y-5" onSubmit={submit}>
                <input
                    name="token"
                    type="hidden"
                    value={form.data.token}
                />

                <div>
                    <label
                        className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                        htmlFor="email"
                    >
                        {t('auth.forgot_password.email')}
                    </label>
                    <input
                        aria-invalid={Boolean(form.errors.email)}
                        autoComplete="username"
                        className={inputClasses}
                        id="email"
                        name="email"
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        required
                        type="email"
                        value={form.data.email}
                    />
                    {form.errors.email ? (
                        <p
                            className="mt-2 text-sm font-medium text-[var(--danger)]"
                            role="alert"
                        >
                            {form.errors.email}
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
                        aria-invalid={Boolean(form.errors.password)}
                        autoComplete="new-password"
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
                            role="alert"
                        >
                            {form.errors.password}
                        </p>
                    ) : null}
                </div>

                <div>
                    <label
                        className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                        htmlFor="password_confirmation"
                    >
                        {t('auth.reset_password.confirm_password')}
                    </label>
                    <input
                        aria-invalid={Boolean(form.errors.password)}
                        autoComplete="new-password"
                        className={inputClasses}
                        id="password_confirmation"
                        name="password_confirmation"
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        required
                        type="password"
                        value={form.data.password_confirmation}
                    />
                </div>

                <Button
                    disabled={form.processing}
                    fullWidth
                    type="submit"
                >
                    {form.processing
                        ? t('actions.processing')
                        : t('auth.reset_password.submit')}
                </Button>

                <Button
                    as="link"
                    fullWidth
                    href="/login"
                    variant="ghost"
                >
                    {t('auth.back_to_login')}
                </Button>
            </form>
        </GuestLayout>
    );
}
