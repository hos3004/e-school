import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';

interface ConversationItem {
    id: string;
    subject?: string;
    last_message?: string;
    updated_at?: string;
    unread_count?: number;
    participants_count?: number;
}

export default function Inbox() {
    const [conversations, setConversations] = useState<ConversationItem[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchConversations = async () => {
            setLoading(true);
            try {
                const res = await fetch('/api/conversations', {
                    headers: { Accept: 'application/json' },
                });
                if (res.ok) {
                    const data = await res.json();
                    setConversations(Array.isArray(data) ? data : data.data || []);
                }
            } catch {
                // Fallback gracefully
            } finally {
                setLoading(false);
            }
        };

        fetchConversations();
    }, []);

    return (
        <AppLayout header={<h2 className="text-xl font-bold text-[var(--ink)]">الرسائل والمحادثات</h2>}>
            <Head title="صندوق المحادثات" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center justify-between rounded-xl border border-[var(--ink-muted)]/20 bg-[var(--surface)] p-4 shadow-sm">
                    <h3 className="text-base font-bold text-[var(--ink)]">محادثاتي</h3>
                    <Link
                        href="/messages/create"
                        className="rounded-lg bg-[var(--brand)] px-4 py-2 text-xs font-bold text-white hover:bg-[var(--brand)]/90 transition-colors"
                    >
                        محادثة جديدة +
                    </Link>
                </div>

                <div className="rounded-xl border border-[var(--ink-muted)]/20 bg-[var(--surface)] p-6 shadow-sm">
                    {loading ? (
                        <div className="py-12 text-center text-sm text-[var(--ink-muted)]">جاري تحميل المحادثات...</div>
                    ) : conversations.length === 0 ? (
                        <div className="py-12 text-center text-sm text-[var(--ink-muted)]">لا توجد محادثات نشطة حالياً</div>
                    ) : (
                        <div className="divide-y divide-[var(--ink-muted)]/10">
                            {conversations.map((item) => (
                                <Link
                                    key={item.id}
                                    href={`/messages/${item.id}`}
                                    className="block py-4 hover:bg-[var(--surface-muted)]/50 px-2 rounded-lg transition-colors first:pt-0 last:pb-0"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-[var(--ink)]">
                                                    {item.subject || 'محادثة خاصة'}
                                                </span>
                                                {(item.unread_count ?? 0) > 0 && (
                                                    <span className="rounded-full bg-[var(--brand)] px-2 py-0.5 text-[10px] font-bold text-white">
                                                        {item.unread_count} جديد
                                                    </span>
                                                )}
                                            </div>
                                            {item.last_message && (
                                                <p className="text-xs text-[var(--ink-muted)] line-clamp-1">
                                                    {item.last_message}
                                                </p>
                                            )}
                                        </div>

                                        <span className="text-[10px] text-[var(--ink-muted)]">
                                            {item.updated_at ? new Date(item.updated_at).toLocaleDateString('ar-SA') : ''}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
