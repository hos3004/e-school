import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import Button from '@/Components/Button';
import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { useI18n } from '@/lib/i18n';

const fieldClass =
    'mt-2 min-h-12 w-full rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-4 text-base text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)] placeholder:text-[var(--ink-muted)] focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)] focus:ring-offset-2 sm:text-sm';

export default function Create() {
    const t = useI18n();
    const [recipientId, setRecipientId] = useState('');
    const [subject, setSubject] = useState('');
    const [initialMessage, setInitialMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!recipientId.trim() || !initialMessage.trim()) {
            setError(t('messaging.validation_required'));
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await fetch('/api/conversations', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    recipient_id: recipientId,
                    subject: subject.trim() || undefined,
                    body: initialMessage,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                const conversationId = data.id ?? data.data?.id;
                router.visit(
                    conversationId ? `/messages/${conversationId}` : '/messages',
                );
                return;
            }

            const data = await response.json();
            setError(data.message || t('messaging.create_error'));
        } catch {
            setError(t('messaging.connection_error'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout>
            <Head title={t('messaging.create.title')} />
            <PageHeader
                action={
                    <Button as="link" href="/messages" variant="ghost">
                        {t('messaging.back')}
                    </Button>
                }
                className="mb-6"
                subtitle={t('messaging.create.subtitle')}
                title={t('messaging.create.title')}
            />

            <Card className="mx-auto max-w-2xl" padding="lg">
                {error ? (
                    <div className="mb-5 rounded-[var(--radius-md)] border border-[color:var(--danger)]/30 bg-[var(--danger-soft)] p-4 text-sm font-medium text-[var(--danger)]" role="alert">
                        {error}
                    </div>
                ) : null}

                <form className="space-y-5" onSubmit={handleSubmit}>
                    <div>
                        <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="recipient-id">
                            {t('messaging.create.recipient')}
                        </label>
                        <input
                            aria-describedby="recipient-help"
                            autoComplete="off"
                            className={fieldClass}
                            id="recipient-id"
                            onChange={(event) => setRecipientId(event.target.value)}
                            placeholder={t('messaging.create.recipient_placeholder')}
                            required
                            type="text"
                            value={recipientId}
                        />
                        <p className="mt-2 text-xs leading-5 text-[var(--ink-muted)]" id="recipient-help">
                            {t('messaging.create.recipient_help')}
                        </p>
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="conversation-subject">
                            {t('messaging.create.subject')}
                        </label>
                        <input
                            className={fieldClass}
                            id="conversation-subject"
                            onChange={(event) => setSubject(event.target.value)}
                            placeholder={t('messaging.create.subject_placeholder')}
                            type="text"
                            value={subject}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="initial-message">
                            {t('messaging.create.message')}
                        </label>
                        <textarea
                            className={`${fieldClass} py-3`}
                            id="initial-message"
                            onChange={(event) => setInitialMessage(event.target.value)}
                            placeholder={t('messaging.create.message_placeholder')}
                            required
                            rows={5}
                            value={initialMessage}
                        />
                    </div>

                    <div className="flex flex-col-reverse gap-3 border-t border-[var(--line)] pt-5 sm:flex-row sm:justify-end">
                        <Button as="link" href="/messages" variant="secondary">
                            {t('actions.cancel')}
                        </Button>
                        <Button disabled={loading} type="submit">
                            {loading
                                ? t('messaging.create.submitting')
                                : t('messaging.create.submit')}
                        </Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
