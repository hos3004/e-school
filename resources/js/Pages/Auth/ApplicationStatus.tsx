import { Head } from '@inertiajs/react';

import StatusPill from '@/Components/StatusPill';
import type { StatusColorMap } from '@/Components/StatusPill';
import GuestLayout from '@/Layouts/GuestLayout';
import { formatDate, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';

const applicationStatusColors: StatusColorMap<string> = {
    draft: 'neutral',
    submitted: 'brand',
    under_review: 'warning',
    accepted: 'success',
    rejected: 'danger',
    waiting_assignment: 'warning',
    assigned: 'success',
};

interface Props {
    application?: {
        id: string;
        applicant_name: string;
        status: string;
        created_at?: string;
    } | null;
}

export default function ApplicationStatus({ application = null }: Props) {
    const t = useI18n();
    const locale = useLocale();

    return (
        <GuestLayout>
            <Head title={t('auth.register.status_title')} />

            <div className="text-center">
                <p className="text-sm font-semibold text-[var(--brand-strong)]">
                    {t('app.name')}
                </p>
                <h1 className="mt-2 text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance]">
                    {t('auth.register.status_title')}
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)] [text-wrap:pretty]">
                    {t('auth.register.status_subtitle')}
                </p>
            </div>

            {application ? (
                <dl className="mt-8 divide-y divide-[var(--line)] overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-subtle)]">
                    <div className="grid gap-1 px-5 py-4 sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-center">
                        <dt className="text-sm text-[var(--ink-muted)]">
                            {t('auth.register.applicant')}
                        </dt>
                        <dd className="font-semibold text-[var(--ink)]">
                            {application.applicant_name}
                        </dd>
                    </div>
                    <div className="grid gap-1 px-5 py-4 sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-center">
                        <dt className="text-sm text-[var(--ink-muted)]">
                            {t('auth.register.reference')}
                        </dt>
                        <dd>
                            <code className="select-all rounded-[var(--radius-sm)] bg-[var(--surface-muted)] px-2 py-1 font-mono text-sm font-semibold text-[var(--brand-strong)]">
                                {application.id}
                            </code>
                        </dd>
                    </div>
                    <div className="grid gap-2 px-5 py-4 sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-center">
                        <dt className="text-sm text-[var(--ink-muted)]">
                            {t('auth.register.status')}
                        </dt>
                        <dd>
                            <StatusPill
                                colorMap={applicationStatusColors}
                                status={application.status}
                            />
                        </dd>
                    </div>
                    {application.created_at ? (
                        <div className="px-5 py-3 text-sm tabular-nums text-[var(--ink-muted)]">
                            {formatDate(application.created_at, locale)}
                        </div>
                    ) : null}
                </dl>
            ) : (
                <div className="mt-8 rounded-[var(--radius-lg)] border border-[color:var(--danger)]/30 bg-[var(--danger-soft)] p-6 text-center text-sm font-medium text-[var(--danger)]" role="alert">
                    {t('auth.register.not_found')}
                </div>
            )}
        </GuestLayout>
    );
}
