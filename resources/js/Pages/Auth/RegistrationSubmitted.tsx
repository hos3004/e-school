import { Head } from '@inertiajs/react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/lib/i18n';

interface Props {
    applicationId?: string;
}

export default function RegistrationSubmitted({ applicationId }: Props) {
    const t = useI18n();

    return (
        <GuestLayout>
            <Head title={t('auth.register.submitted_title')} />

            <div className="text-center">
                <div className="mx-auto flex size-14 items-center justify-center rounded-[var(--radius-lg)] border border-[color:var(--success)]/25 bg-[var(--success-soft)] text-[var(--success)]">
                    <svg className="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 className="mt-5 text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance]">
                    {t('auth.register.submitted_title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)] [text-wrap:pretty]">
                    {t('auth.register.submitted_description')}
                </p>

                {applicationId ? (
                    <div className="mt-6 rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--surface-subtle)] p-4 text-center">
                        <p className="text-xs text-[var(--ink-muted)]">{t('auth.register.submitted_reference')}</p>
                        <p className="mt-1 select-all font-mono text-lg font-semibold text-[var(--brand-strong)]">{applicationId}</p>
                    </div>
                ) : null}

                <div className="mt-8 space-y-3">
                    {applicationId ? (
                        <Button as="link" fullWidth href={`/register/status/${applicationId}`}>
                            {t('auth.register.track_application')}
                        </Button>
                    ) : null}

                    <Button as="link" fullWidth href="/login" variant="ghost">
                        {t('auth.back_to_login')}
                    </Button>
                </div>
            </div>
        </GuestLayout>
    );
}
