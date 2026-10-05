import { useEffect, useState } from 'react';

import Card, { CardDescription, CardHeader, CardTitle } from '@/Components/Card';
import { useI18n } from '@/lib/i18n';

interface Preference {
    category: string;
    channel: string;
    enabled: boolean;
}

interface PreferenceResponse {
    data?: Preference[];
}

const categories = ['schedule_summary', 'session_reminder'] as const;

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '';

export default function SessionEmailPreference() {
    const t = useI18n();
    const [enabled, setEnabled] = useState(true);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [status, setStatus] = useState<'idle' | 'saved' | 'error'>('idle');

    useEffect(() => {
        let active = true;

        void fetch('/api/notification-preferences', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('preference-load-failed');
                return (await response.json()) as PreferenceResponse;
            })
            .then((response) => {
                if (!active) return;
                const emailPreferences = (response.data ?? []).filter(
                    (preference) =>
                        preference.channel === 'email' &&
                        categories.includes(
                            preference.category as (typeof categories)[number],
                        ),
                );
                setEnabled(
                    !emailPreferences.some(
                        (preference) => preference.enabled === false,
                    ),
                );
            })
            .catch(() => {
                if (active) setStatus('error');
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, []);

    const updatePreference = async (nextEnabled: boolean) => {
        const previous = enabled;
        setEnabled(nextEnabled);
        setSaving(true);
        setStatus('idle');

        try {
            const responses = await Promise.all(
                categories.map((category) =>
                    fetch('/api/notification-preferences', {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify({
                            category,
                            channel: 'email',
                            enabled: nextEnabled,
                        }),
                    }),
                ),
            );

            if (responses.some((response) => !response.ok)) {
                throw new Error('preference-save-failed');
            }
            setStatus('saved');
        } catch {
            setEnabled(previous);
            setStatus('error');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Card className='border-[var(--line)]' padding='lg'>
            <CardHeader className='mb-5'>
                <CardTitle>{t('notifications.session_email.title')}</CardTitle>
                <CardDescription>{t('notifications.session_email.description')}</CardDescription>
            </CardHeader>

            <div className='flex flex-wrap items-center justify-between gap-4 rounded-xl bg-[var(--surface-muted)] px-4 py-4'>
                <div>
                    <p className='font-semibold text-[var(--ink)]'>
                        {t('notifications.session_email.label')}
                    </p>
                    <p className='mt-1 text-sm text-[var(--ink-muted)]'>
                        {t('notifications.session_email.note')}
                    </p>
                </div>
                <button
                    aria-checked={enabled}
                    aria-label={t('notifications.session_email.label')}
                    className={[
                        'relative h-7 w-12 rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)]',
                        enabled ? 'bg-[var(--brand)]' : 'bg-[var(--ink-muted)]/45',
                    ].join(' ')}
                    disabled={loading || saving}
                    onClick={() => void updatePreference(!enabled)}
                    role='switch'
                    type='button'
                >
                    <span
                        className={[
                            'absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-transform',
                            enabled ? 'start-6' : 'start-1',
                        ].join(' ')}
                    />
                </button>
            </div>

            <p
                aria-live='polite'
                className={status === 'error' ? 'mt-3 text-sm text-[var(--danger)]' : 'mt-3 text-sm text-[var(--ink-muted)]'}
            >
                {loading
                    ? t('notifications.session_email.loading')
                    : saving
                      ? t('notifications.session_email.saving')
                      : status === 'saved'
                        ? t('notifications.session_email.saved')
                        : status === 'error'
                          ? t('notifications.session_email.error')
                          : enabled
                            ? t('notifications.session_email.enabled')
                            : t('notifications.session_email.disabled')}
            </p>
        </Card>
    );
}
