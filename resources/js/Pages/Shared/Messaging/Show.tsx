import { useCallback, useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';

interface Message {
    id: string;
    sender_id: string;
    sender_name?: string;
    content: string;
    created_at?: string;
}

interface Props {
    conversationId: string;
}

export default function Show({ conversationId }: Props) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessage, setNewMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchMessages = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(`/api/conversations/${conversationId}/messages`, {
                headers: { Accept: 'application/json' },
            });
            if (res.status === 404 || res.status === 403) {
                setError('غير مسموح بفتح هذه المحادثة أو أن المحادثة غير موجودة');
                return;
            }
            if (res.ok) {
                const data = await res.json();
                setMessages(Array.isArray(data) ? data : data.data || []);
            }
        } catch {
            setError('حدث خطأ أثناء تحميل الرسائل');
        } finally {
            setLoading(false);
        }
    }, [conversationId]);

    useEffect(() => {
        void fetchMessages();
    }, [fetchMessages]);

    const sendMessage = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newMessage.trim()) return;

        try {
            const res = await fetch(`/api/conversations/${conversationId}/messages`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ body: newMessage }),
            });

            if (res.ok) {
                setNewMessage('');
                void fetchMessages();
            } else {
                const data = await res.json();
                setError(data.message || 'تعذر إرسال الرسالة');
            }
        } catch {
            setError('تعذر إرسال الرسالة');
        }
    };

    return (
        <AppLayout header={<h2 className="text-xl font-bold text-[var(--ink)]">عرض المحادثة</h2>}>
            <Head title="المحادثة" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center justify-between rounded-xl border border-[var(--ink-muted)]/20 bg-[var(--surface)] p-4 shadow-sm">
                    <Link
                        href="/messages"
                        className="text-xs font-bold text-[var(--brand)] hover:underline"
                    >
                        &rarr; العودة للمحادثات
                    </Link>
                </div>

                <div className="rounded-xl border border-[var(--ink-muted)]/20 bg-[var(--surface)] p-6 shadow-sm min-h-[400px] flex flex-col justify-between">
                    {error ? (
                        <div className="py-12 text-center text-sm font-semibold text-[var(--danger)]">{error}</div>
                    ) : loading ? (
                        <div className="py-12 text-center text-sm text-[var(--ink-muted)]">جاري تحميل الرسائل...</div>
                    ) : (
                        <div className="space-y-4 overflow-y-auto max-h-[500px] p-2">
                            {messages.length === 0 ? (
                                <div className="py-12 text-center text-sm text-[var(--ink-muted)]">لا توجد رسائل سابقة في هذه المحادثة</div>
                            ) : (
                                messages.map((m) => (
                                    <div
                                        key={m.id}
                                        className="rounded-lg border border-[var(--ink-muted)]/10 bg-[var(--surface-muted)] p-3 text-xs space-y-1 max-w-lg"
                                    >
                                        <div className="flex items-center justify-between text-[10px] text-[var(--ink-muted)]">
                                            <span>{m.sender_name || 'مستخدم'}</span>
                                            <span>{m.created_at ? new Date(m.created_at).toLocaleTimeString('ar-SA') : ''}</span>
                                        </div>
                                        <p className="text-[var(--ink)]">{m.content}</p>
                                    </div>
                                ))
                            )}
                        </div>
                    )}

                    {!error && (
                        <form onSubmit={sendMessage} className="mt-6 border-t border-[var(--ink-muted)]/10 pt-4 flex gap-3">
                            <input
                                type="text"
                                value={newMessage}
                                onChange={(e) => setNewMessage(e.target.value)}
                                placeholder="اكتب رسالتك هنا..."
                                className="flex-1 rounded-lg border border-[var(--ink-muted)]/20 bg-[var(--surface)] px-4 py-2 text-xs text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                            />
                            <button
                                type="submit"
                                className="rounded-lg bg-[var(--brand)] px-6 py-2 text-xs font-bold text-white hover:bg-[var(--brand)]/90 transition-colors"
                            >
                                إرسال
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
