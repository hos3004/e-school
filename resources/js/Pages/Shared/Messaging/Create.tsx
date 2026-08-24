import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';

export default function Create() {
    const [recipientId, setRecipientId] = useState('');
    const [subject, setSubject] = useState('');
    const [initialMessage, setInitialMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!recipientId.trim() || !initialMessage.trim()) {
            setError('يرجى ملء جميع الحقول المطلوبة');
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const res = await fetch('/api/conversations', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    recipient_id: recipientId,
                    subject: subject.trim() || undefined,
                    body: initialMessage,
                }),
            });

            if (res.ok) {
                const data = await res.json();
                const conversationId = data.id || data.data?.id;
                if (conversationId) {
                    router.visit(`/messages/${conversationId}`);
                } else {
                    router.visit('/messages');
                }
            } else {
                const data = await res.json();
                setError(data.message || 'تعذر بدء المحادثة. قد لا تملك صلاحية التراسل مع هذا المستخدم.');
            }
        } catch {
            setError('حدث خطأ في الاتصال بالخادم');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout header={<h2 className="text-xl font-bold text-[var(--ink)]">بدء محادثة جديدة</h2>}>
            <Head title="محادثة جديدة" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center justify-between rounded-xl border border-[var(--ink-muted)]/20 bg-[var(--surface)] p-4 shadow-sm">
                    <Link
                        href="/messages"
                        className="text-xs font-bold text-[var(--brand)] hover:underline"
                    >
                        &rarr; العودة للمحادثات
                    </Link>
                </div>

                <div className="rounded-xl border border-[var(--ink-muted)]/20 bg-[var(--surface)] p-6 shadow-sm">
                    {error && (
                        <div className="mb-4 rounded-lg bg-[var(--danger)]/10 p-3 text-xs font-semibold text-[var(--danger)]">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-xs font-bold text-[var(--ink)] mb-1">
                                معرّف المستلم (User ID) <span className="text-[var(--danger)]">*</span>
                            </label>
                            <input
                                type="text"
                                value={recipientId}
                                onChange={(e) => setRecipientId(e.target.value)}
                                placeholder="مثال: 01H..."
                                required
                                className="w-full rounded-lg border border-[var(--ink-muted)]/20 bg-[var(--surface)] px-4 py-2 text-xs text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-[var(--ink)] mb-1">
                                موضوع المحادثة (اختياري)
                            </label>
                            <input
                                type="text"
                                value={subject}
                                onChange={(e) => setSubject(e.target.value)}
                                placeholder="مثال: استفسار حول واجب الرياضيات"
                                className="w-full rounded-lg border border-[var(--ink-muted)]/20 bg-[var(--surface)] px-4 py-2 text-xs text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-[var(--ink)] mb-1">
                                الرسالة الأولى <span className="text-[var(--danger)]">*</span>
                            </label>
                            <textarea
                                value={initialMessage}
                                onChange={(e) => setInitialMessage(e.target.value)}
                                rows={4}
                                placeholder="اكتب نص الرسالة..."
                                required
                                className="w-full rounded-lg border border-[var(--ink-muted)]/20 bg-[var(--surface)] px-4 py-2 text-xs text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                            />
                        </div>

                        <div className="flex justify-end gap-2 border-t border-[var(--ink-muted)]/10 pt-4">
                            <Link
                                href="/messages"
                                className="rounded-lg border border-[var(--ink-muted)]/20 px-4 py-2 text-xs font-bold text-[var(--ink-muted)] hover:bg-[var(--surface-muted)]"
                            >
                                إلغاء
                            </Link>
                            <button
                                type="submit"
                                disabled={loading}
                                className="rounded-lg bg-[var(--brand)] px-6 py-2 text-xs font-bold text-white hover:bg-[var(--brand)]/90 disabled:opacity-50 transition-colors"
                            >
                                {loading ? 'جاري البدء...' : 'بدء المحادثة'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
