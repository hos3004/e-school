import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import Button from '@/Components/Button';
import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';

interface ConversationItem {
    id: string;
    subject?: string;
    last_message?: string;
    updated_at?: string;
    unread_count?: number;
    participants_count?: number;
}

export default function Inbox() {
    const t = useI18n();
    const locale = useLocale();
    const [conversations, setConversations] = useState<ConversationItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [hasError, setHasError] = useState(false);

    useEffect(() => {
        const fetchConversations = async () => {
            setLoading(true);
            setHasError(false);

            try {
                const response = await fetch('/api/conversations', {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    setHasError(true);
                    return;
                }

                const data = await response.json();
                setConversations(
                    Array.isArray(data) ? data : (data.data ?? []),
                );
            } catch {
                setHasError(true);
            } finally {
                setLoading(false);
            }
        };

        void fetchConversations();
    }, []);

    return (
        <AppLayout>
            <Head title={t('messaging.inbox.title')} />
            <PageHeader
                action={
                    <Button as="link" href="/messages/create">
                        {t('messaging.inbox.new')}
                    </Button>
                }
                className="mb-6"
                subtitle={t('messaging.inbox.subtitle')}
                title={t('messaging.inbox.title')}
            />

            <div className="mx-auto max-w-4xl">
                {loading ? (
                    <LoadingState label={t('messaging.loading')} rows={4} />
                ) : hasError ? (
                    <ErrorState message={t('messaging.load_error')} />
                ) : conversations.length === 0 ? (
                    <EmptyState
                        action={
                            <Button as="link" href="/messages/create" variant="secondary">
                                {t('messaging.inbox.new')}
                            </Button>
                        }
                        description={t('messaging.inbox.empty_description')}
                        title={t('messaging.inbox.empty_title')}
                    />
                ) : (
                    <section
                        aria-label={t('messaging.inbox.list_label')}
                        className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-raised)] shadow-[var(--shadow-card)]"
                    >
                        <div className="divide-y divide-[var(--line)]">
                            {conversations.map((item) => (
                                <Link
                                    className="group block px-4 py-4 transition-colors duration-150 hover:bg-[var(--surface-subtle)] focus-visible:bg-[var(--brand-soft)] sm:px-5"
                                    href={`/messages/${item.id}`}
                                    key={item.id}
                                >
                                    <div className="flex items-start gap-3">
                                        <span
                                            aria-hidden="true"
                                            className="flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-[var(--brand-soft)] text-[var(--brand-strong)]"
                                        >
                                            <svg className="size-5" fill="none" viewBox="0 0 24 24">
                                                <path d="M4 5.5h16v11H9l-5 3v-14Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
                                            </svg>
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <h2 className="font-semibold text-[var(--ink)]">
                                                    {item.subject || t('messaging.private_conversation')}
                                                </h2>
                                                {item.updated_at ? (
                                                    <time className="text-xs tabular-nums text-[var(--ink-muted)]" dateTime={item.updated_at}>
                                                        {formatDateTime(item.updated_at, locale)}
                                                    </time>
                                                ) : null}
                                            </div>
                                            <div className="mt-1 flex items-center gap-2">
                                                {item.last_message ? (
                                                    <p className="min-w-0 flex-1 truncate text-sm text-[var(--ink-muted)]">
                                                        {item.last_message}
                                                    </p>
                                                ) : null}
                                                {(item.unread_count ?? 0) > 0 ? (
                                                    <span className="inline-flex min-h-6 items-center rounded-full bg-[var(--brand)] px-2 text-xs font-semibold text-[var(--ink-inverse)]">
                                                        {item.unread_count} {t('messaging.unread')}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
