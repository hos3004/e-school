import { Head } from '@inertiajs/react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';

interface Props {
    applicationId?: string;
}

export default function RegistrationSubmitted({ applicationId }: Props) {
    return (
        <GuestLayout>
            <Head title="تم تقديم الطلب بنجاح" />

            <div className="text-center">
                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-[var(--success)]/10 text-[var(--success)]">
                    <svg className="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 className="mt-4 text-2xl font-bold text-[var(--ink)]">
                    تم استلام طلب التسجيل بنجاح
                </h1>
                <p className="mt-2 text-sm leading-6 text-[var(--ink-muted)]">
                    شكراً لتقديمك. سيقوم فريق الإدارة بمراجعة الطلب والتواصل معك في أقرب وقت.
                </p>

                {applicationId ? (
                    <div className="mt-6 rounded-lg border border-[var(--ink-muted)]/20 bg-[var(--surface-muted)] p-4 text-center">
                        <p className="text-xs text-[var(--ink-muted)]">رقم مرجع الطلب (الرقم المرجعي):</p>
                        <p className="mt-1 font-mono text-lg font-bold text-[var(--brand)] select-all">{applicationId}</p>
                    </div>
                ) : null}

                <div className="mt-8 space-y-3">
                    {applicationId ? (
                        <Button as="link" fullWidth href={`/register/status/${applicationId}`}>
                            متابعة حالة الطلب
                        </Button>
                    ) : null}

                    <Button as="link" fullWidth href="/login" variant="ghost">
                        العودة لتسجيل الدخول
                    </Button>
                </div>
            </div>
        </GuestLayout>
    );
}
