import { Head, router } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

import Button from '@/Components/Button';
import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { useI18n } from '@/lib/i18n';

interface RecipientOption {
    id: string;
    name: string;
    username: string;
}

const fieldClass =
    'mt-2 min-h-12 w-full rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-4 text-base text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)] placeholder:text-[var(--ink-muted)] focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)] focus:ring-offset-2 sm:text-sm';

export default function Create() {
    const t = useI18n();
    const [searchTerm, setSearchTerm] = useState('');
    const [recipients, setRecipients] = useState<RecipientOption[]>([]);
    const [selectedRecipient, setSelectedRecipient] = useState<RecipientOption | null>(null);
    const [searching, setSearching] = useState(false);
    const [subject, setSubject] = useState('');
    const [initialMessage, setInitialMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const term = searchTerm.trim();

        if (selectedRecipient || term.length < 2) {
            setRecipients([]);
            setSearching(false);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setSearching(true);

            try {
                const response = await fetch(
                    `/api/messaging/recipients?q=${encodeURIComponent(term)}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                const data = await response.json();
                setRecipients(response.ok && Array.isArray(data.data) ? data.data : []);
            } catch (requestError) {
                if (!(requestError instanceof DOMException && requestError.name === 'AbortError')) {
                    setRecipients([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setSearching(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [searchTerm, selectedRecipient]);

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedRecipient || !subject.trim() || !initialMessage.trim()) {
            setError(t('messaging.validation_required'));
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await fetch('/api/messaging/direct-conversations', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    recipient_user_id: selectedRecipient.id,
                    subject: subject.trim(),
                    body: initialMessage.trim(),
                }),
            });
            const data = await response.json();

            if (response.ok) {
                const conversationId = data.data?.id;
                router.visit(
                    conversationId ? `/messages/${conversationId}` : '/messages',
                );
                return;
            }

            setError(data.error?.message || data.message || t('messaging.create_error'));
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
                        <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="recipient-search">
                            {t('messaging.create.recipient')}
                        </label>
                        {selectedRecipient ? (
                            <div className="mt-2 flex min-h-12 items-center justify-between gap-3 rounded-[var(--radius-md)] border border-[var(--brand)] bg-[var(--brand-soft)] px-4">
                                <span className="min-w-0">
                                    <strong className="block truncate text-sm text-[var(--ink)]">
                                        {selectedRecipient.name}
                                    </strong>
                                    <span className="block truncate text-xs text-[var(--ink-muted)]">
                                        @{selectedRecipient.username}
                                    </span>
                                </span>
                                <button
                                    className="shrink-0 text-sm font-semibold text-[var(--brand-strong)] underline-offset-4 hover:underline"
                                    onClick={() => {
                                        setSelectedRecipient(null);
                                        setSearchTerm('');
                                    }}
                                    type="button"
                                >
                                    {t('messaging.create.change_recipient')}
                                </button>
                            </div>
                        ) : (
                            <>
                                <input
                                    aria-describedby="recipient-help"
                                    autoComplete="off"
                                    className={fieldClass}
                                    id="recipient-search"
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                    placeholder={t('messaging.create.recipient_placeholder')}
                                    type="search"
                                    value={searchTerm}
                                />
                                <p className="mt-2 text-xs leading-5 text-[var(--ink-muted)]" id="recipient-help">
                                    {t('messaging.create.recipient_help')}
                                </p>
                                {searching ? (
                                    <p className="mt-3 text-sm text-[var(--ink-muted)]" role="status">
                                        {t('messaging.create.searching')}
                                    </p>
                                ) : null}
                                {!searching && searchTerm.trim().length >= 2 ? (
                                    <div className="mt-3 overflow-hidden rounded-[var(--radius-md)] border border-[var(--line)]">
                                        {recipients.length === 0 ? (
                                            <p className="p-4 text-sm text-[var(--ink-muted)]">
                                                {t('messaging.create.no_recipients')}
                                            </p>
                                        ) : (
                                            recipients.map((recipient) => (
                                                <button
                                                    className="flex w-full items-center justify-between gap-3 border-b border-[var(--line)] px-4 py-3 text-start last:border-0 hover:bg-[var(--surface-subtle)]"
                                                    key={recipient.id}
                                                    onClick={() => {
                                                        setSelectedRecipient(recipient);
                                                        setSearchTerm(recipient.name);
                                                        setRecipients([]);
                                                    }}
                                                    type="button"
                                                >
                                                    <span className="font-semibold text-[var(--ink)]">{recipient.name}</span>
                                                    <span className="text-sm text-[var(--ink-muted)]">@{recipient.username}</span>
                                                </button>
                                            ))
                                        )}
                                    </div>
                                ) : null}
                            </>
                        )}
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
                            required
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
                        <Button disabled={loading || !selectedRecipient} type="submit">
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
