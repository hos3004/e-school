import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';

import Button from '@/Components/Button';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';

interface Message {
    id: string;
    user_id: string;
    sender_name?: string;
    body: string;
    created_at?: string;
}

interface Props {
    conversationId: string;
    subject?: string;
}

export default function Show({ conversationId, subject }: Props) {
    const t = useI18n();
    const locale = useLocale();
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessage, setNewMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchMessages = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                `/api/conversations/${conversationId}/messages`,
                { headers: { Accept: 'application/json' } },
            );

            if (response.status === 404 || response.status === 403) {
                setError(t('messaging.forbidden'));
                return;
            }

            if (!response.ok) {
                setError(t('messaging.load_error'));
                return;
            }

            const data = await response.json();
            setMessages(Array.isArray(data) ? data : (data.data ?? []));
        } catch {
            setError(t('messaging.load_error'));
        } finally {
            setLoading(false);
        }
    }, [conversationId, t]);

    useEffect(() => {
        void fetchMessages();
    }, [fetchMessages]);

    const sendMessage = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!newMessage.trim() || sending) return;

        setSending(true);
        setError(null);

        try {
            const response = await fetch(
                `/api/conversations/${conversationId}/messages`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ body: newMessage }),
                },
            );

            if (response.ok) {
                setNewMessage('');
                await fetchMessages();
                return;
            }

            const data = await response.json();
            setError(data.message || t('messaging.send_error'));
        } catch {
            setError(t('messaging.send_error'));
        } finally {
            setSending(false);
        }
    };

    return (
        <AppLayout>
            <Head title={subject || t('messaging.show.title')} />
            <PageHeader
                action={
                    <Button as="link" href="/messages" variant="ghost">
                        {t('messaging.back')}
                    </Button>
                }
                className="mb-6"
                subtitle={t('messaging.show.subtitle')}
                title={subject || t('messaging.show.title')}
            />

            <section className="mx-auto flex min-h-[30rem] max-w-4xl flex-col overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-raised)] shadow-[var(--shadow-card)]">
                <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6" aria-live="polite">
                    {error && messages.length === 0 ? (
                        <ErrorState message={error} onRetry={() => void fetchMessages()} />
                    ) : loading ? (
                        <LoadingState label={t('messaging.loading')} rows={4} />
                    ) : messages.length === 0 ? (
                        <EmptyState
                            description={t('messaging.show.empty_description')}
                            title={t('messaging.show.empty_title')}
                        />
                    ) : (
                        <ol className="space-y-3" aria-label={t('messaging.show.list_label')}>
                            {messages.map((message) => (
                                <li
                                    className="max-w-2xl rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--surface-subtle)] p-4"
                                    key={message.id}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--ink-muted)]">
                                        <span className="font-semibold text-[var(--ink-soft)]">
                                            {message.sender_name || t('messaging.user')}
                                        </span>
                                        {message.created_at ? (
                                            <time className="tabular-nums" dateTime={message.created_at}>
                                                {formatDateTime(message.created_at, locale)}
                                            </time>
                                        ) : null}
                                    </div>
                                    <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-[var(--ink)]">
                                        {message.body}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </div>

                <form className="border-t border-[var(--line)] bg-[var(--surface-subtle)] p-4 sm:p-5" onSubmit={sendMessage}>
                    {error && messages.length > 0 ? (
                        <p className="mb-3 text-sm font-medium text-[var(--danger)]" role="alert">
                            {error}
                        </p>
                    ) : null}
                    <label className="sr-only" htmlFor="new-message">
                        {t('messaging.show.message_label')}
                    </label>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <textarea
                            className="min-h-12 flex-1 resize-y rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-4 py-3 text-base text-[var(--ink)] placeholder:text-[var(--ink-muted)] focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)] focus:ring-offset-2 sm:text-sm"
                            id="new-message"
                            onChange={(event) => setNewMessage(event.target.value)}
                            placeholder={t('messaging.show.message_placeholder')}
                            rows={2}
                            value={newMessage}
                        />
                        <Button disabled={sending || !newMessage.trim()} type="submit">
                            {sending ? t('messaging.sending') : t('messaging.send')}
                        </Button>
                    </div>
                </form>
            </section>
        </AppLayout>
    );
}
