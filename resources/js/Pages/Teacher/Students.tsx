import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import DataTable from '@/Components/DataTable';
import type { DataTableColumn } from '@/Components/DataTable';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatPercent, useLocale } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import type { LoadablePageProps } from '@/types';

interface TeacherStudent {
    id: string;
    name: string;
    code: string;
    gender?: string | null;
    groupId: string;
    groupName: string;
    joinedAt?: string | null;
    attendanceRate?: number | null;
    openAssignmentsCount: number;
}

interface Props extends LoadablePageProps {
    students?: readonly TeacherStudent[];
}

export default function Students({
    students = [],
    loading = false,
    error = null,
}: Props) {
    const t = useI18n();
    const locale = useLocale();
    const [search, setSearch] = useState('');
    const [groupFilter, setGroupFilter] = useState('');

    const groups = useMemo(() => {
        const seen = new Map<string, string>();

        for (const student of students) {
            seen.set(student.groupId, student.groupName);
        }

        return [...seen.entries()];
    }, [students]);

    const filtered = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase();

        return students.filter((student) => {
            const matchesGroup =
                groupFilter === '' || student.groupId === groupFilter;
            const matchesSearch =
                needle === '' ||
                student.name.toLocaleLowerCase().includes(needle) ||
                student.code.toLocaleLowerCase().includes(needle);

            return matchesGroup && matchesSearch;
        });
    }, [students, search, groupFilter]);

    const columns: readonly DataTableColumn<TeacherStudent>[] = [
        {
            key: 'student',
            header: t('teacher.students.columns.student'),
            render: (student) => (
                <div className="min-w-44">
                    <p className="font-semibold text-[var(--ink)]">
                        {student.name}
                    </p>
                    <p className="mt-1 font-mono text-sm text-[var(--ink-muted)]">
                        {student.code}
                    </p>
                </div>
            ),
        },
        {
            key: 'group',
            header: t('teacher.students.columns.group'),
            render: (student) => student.groupName,
        },
        {
            key: 'attendance_rate',
            header: t('teacher.students.columns.attendance_rate'),
            render: (student) =>
                student.attendanceRate === null ||
                student.attendanceRate === undefined ? (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ) : (
                    formatPercent(student.attendanceRate, locale)
                ),
        },
        {
            key: 'open_assignments',
            header: t('teacher.students.columns.open_assignments'),
            render: (student) => student.openAssignmentsCount,
        },
        {
            key: 'joined_at',
            header: t('teacher.students.columns.joined_at'),
            render: (student) =>
                student.joinedAt ? (
                    formatDate(student.joinedAt, locale)
                ) : (
                    <span className="text-[var(--ink-muted)]">
                        {t('common.not_available')}
                    </span>
                ),
        },
    ];

    return (
        <AppLayout role="teacher">
            <Head title={t('teacher.students.title')} />
            <PageHeader
                className="mb-6"
                title={t('teacher.students.title')}
                subtitle={t('teacher.students.subtitle')}
            />

            <div className="mb-4 flex flex-wrap gap-3">
                <label className="text-sm">
                    <span className="sr-only">
                        {t('teacher.students.search')}
                    </span>
                    <input
                        className="min-h-11 w-64 rounded-lg border border-[var(--ink-muted)] bg-[var(--surface)] px-3 text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('teacher.students.search')}
                        type="search"
                        value={search}
                    />
                </label>

                <label className="text-sm">
                    <span className="sr-only">
                        {t('teacher.students.columns.group')}
                    </span>
                    <select
                        className="min-h-11 rounded-lg border border-[var(--ink-muted)] bg-[var(--surface)] px-3 text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                        onChange={(event) => setGroupFilter(event.target.value)}
                        value={groupFilter}
                    >
                        <option value="">
                            {t('teacher.students.all_groups')}
                        </option>
                        {groups.map(([id, name]) => (
                            <option key={id} value={id}>
                                {name}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <DataTable
                caption={t('teacher.students.table_caption')}
                columns={columns}
                emptyDescription={t('teacher.students.empty_description')}
                emptyTitle={t('teacher.students.empty_title')}
                error={error}
                loading={loading}
                loadingLabel={t('teacher.students.loading')}
                onRetry={() => router.reload()}
                rowKey={(student) => `${student.groupId}:${student.id}`}
                rows={filtered}
            />
        </AppLayout>
    );
}
