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
    'min-h-12 w-full rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-4 text-base text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)] placeholder:text-[var(--ink-muted)] focus-visible:border-[var(--brand)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)] sm:text-sm';

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
                <p className="text-sm font-semibold text-[var(--brand-strong)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance]">
                    {t('auth.forgot_password.title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)]">
                    {t('auth.forgot_password.subtitle')}
                </p>
            </div>

            {recentlySuccessful ? (
                <div
                    className="mt-6 rounded-[var(--radius-md)] border border-[color:var(--success)]/30 bg-[var(--success-soft)] px-4 py-3 text-sm font-medium text-[var(--success)]"
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
