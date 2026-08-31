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
    'min-h-12 w-full rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-4 text-base text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)] placeholder:text-[var(--ink-muted)] focus-visible:border-[var(--brand)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)] sm:text-sm';

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
                <p className="text-sm font-semibold text-[var(--brand-strong)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance]">
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
