import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/lib/i18n';
import type { AppPageProps } from '@/types';

interface ForgotPasswordProps extends AppPageProps {
    status?: string;
}

interface ForgotPasswordForm {
    email: string;
}

const inputClasses =
    'min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/50 bg-[var(--surface)] ps-3 pe-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const t = useI18n();
    const form = useForm<ForgotPasswordForm>({ email: '' });
    const recentlySuccessful = form.recentlySuccessful || Boolean(status);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post('/forgot-password', {
            preserveScroll: true,
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.forgot_password.title')} />

            <div className="text-center">
                <p className="text-sm font-bold text-[var(--brand)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-bold text-[var(--ink)]">
                    {t('auth.forgot_password.title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)]">
                    {t('auth.forgot_password.subtitle')}
                </p>
            </div>

            {recentlySuccessful ? (
                <div
                    className="mt-6 rounded-lg border border-[var(--success)] bg-[var(--surface-muted)] ps-4 pe-4 py-3 text-sm text-[var(--ink)]"
                    role="status"
                >
                    {status ?? t('auth.forgot_password.sent')}
                </div>
            ) : null}

            <form className="mt-8 space-y-5" onSubmit={submit}>
                <div>
                    <label
                        className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                        htmlFor="email"
                    >
                        {t('auth.forgot_password.email')}
                    </label>
                    <input
                        aria-describedby={
                            form.errors.email ? 'email-error' : undefined
                        }
                        aria-invalid={Boolean(form.errors.email)}
                        autoComplete="username"
                        autoFocus
                        className={inputClasses}
                        id="email"
                        name="email"
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        placeholder={t(
                            'auth.forgot_password.email_placeholder',
                        )}
                        required
                        type="email"
                        value={form.data.email}
                    />
                    {form.errors.email ? (
                        <p
                            className="mt-2 text-sm font-medium text-[var(--danger)]"
                            id="email-error"
                            role="alert"
                        >
                            {form.errors.email}
                        </p>
                    ) : null}
                </div>

                <Button
                    disabled={form.processing}
                    fullWidth
                    type="submit"
                >
                    {form.processing
                        ? t('actions.processing')
                        : t('auth.forgot_password.submit')}
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
