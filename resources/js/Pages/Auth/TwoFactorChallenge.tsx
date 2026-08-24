import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/lib/i18n';
import type { AppPageProps } from '@/types';

interface TwoFactorChallengeProps extends AppPageProps {
    usingRecoveryCode?: boolean;
}

interface TwoFactorForm {
    code: string;
    recovery_code: string;
}

const inputClasses =
    'min-h-11 w-full rounded-lg border border-[var(--ink-muted)]/50 bg-[var(--surface)] ps-3 pe-3 text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface)]';

export default function TwoFactorChallenge({
    usingRecoveryCode = false,
}: TwoFactorChallengeProps) {
    const t = useI18n();
    const form = useForm<TwoFactorForm>({ code: '', recovery_code: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post('/two-factor-challenge', {
            preserveScroll: true,
            onFinish: () => form.reset(),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.two_factor.title')} />

            <div className="text-center">
                <p className="text-sm font-bold text-[var(--brand)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-bold text-[var(--ink)]">
                    {t('auth.two_factor.title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)]">
                    {t(
                        usingRecoveryCode
                            ? 'auth.two_factor.recovery_hint'
                            : 'auth.two_factor.code_hint',
                    )}
                </p>
            </div>

            <form className="mt-8 space-y-5" onSubmit={submit}>
                {usingRecoveryCode ? (
                    <div>
                        <label
                            className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                            htmlFor="recovery_code"
                        >
                            {t('auth.two_factor.recovery_code')}
                        </label>
                        <input
                            aria-invalid={Boolean(form.errors.recovery_code)}
                            autoFocus
                            className={inputClasses}
                            id="recovery_code"
                            name="recovery_code"
                            onChange={(event) =>
                                form.setData(
                                    'recovery_code',
                                    event.target.value,
                                )
                            }
                            required
                            type="text"
                            value={form.data.recovery_code}
                        />
                        {form.errors.recovery_code ? (
                            <p
                                className="mt-2 text-sm font-medium text-[var(--danger)]"
                                role="alert"
                            >
                                {form.errors.recovery_code}
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <div>
                        <label
                            className="mb-2 block text-sm font-semibold text-[var(--ink)]"
                            htmlFor="code"
                        >
                            {t('auth.two_factor.code')}
                        </label>
                        <input
                            aria-invalid={Boolean(form.errors.code)}
                            autoComplete="one-time-code"
                            autoFocus
                            className={inputClasses}
                            id="code"
                            inputMode="numeric"
                            name="code"
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                            required
                            type="text"
                            value={form.data.code}
                        />
                        {form.errors.code ? (
                            <p
                                className="mt-2 text-sm font-medium text-[var(--danger)]"
                                role="alert"
                            >
                                {form.errors.code}
                            </p>
                        ) : null}
                    </div>
                )}

                <Button
                    disabled={form.processing}
                    fullWidth
                    type="submit"
                >
                    {form.processing
                        ? t('actions.processing')
                        : t('auth.login.submit')}
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
