import type { ReactNode } from 'react';

import EmptyState from '@/Components/EmptyState';
import ErrorState from '@/Components/ErrorState';
import LoadingState from '@/Components/LoadingState';
import { useI18n } from '@/lib/i18n';

export interface DataTableColumn<Row> {
    key: string;
    header: ReactNode;
    render: (row: Row) => ReactNode;
    headerClassName?: string;
    cellClassName?: string;
}

export interface DataTableProps<Row> {
    columns: readonly DataTableColumn<Row>[];
    rows: readonly Row[];
    rowKey: (row: Row) => string | number;
    caption?: ReactNode;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    loadingLabel?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
}

export default function DataTable<Row>({
    columns,
    rows,
    rowKey,
    caption,
    loading = false,
    error = null,
    onRetry,
    loadingLabel,
    emptyTitle,
    emptyDescription,
    className,
}: DataTableProps<Row>) {
    const t = useI18n();
    const containerClassName = [
        'overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-raised)] shadow-[var(--shadow-card)]',
        className,
    ]
        .filter(Boolean)
        .join(' ');
    const stateCaption =
        caption === undefined || caption === null ? null : (
            <div className="border-b border-[var(--line)] bg-[var(--surface-subtle)] px-5 py-3.5 text-start text-sm font-semibold text-[var(--ink)]">
                {caption}
            </div>
        );

    if (loading) {
        return (
            <section
                aria-busy="true"
                aria-live="polite"
                className={containerClassName}
            >
                {stateCaption}
                <LoadingState
                    className="min-h-48"
                    label={loadingLabel ?? t('states.loading')}
                />
            </section>
        );
    }

    if (error !== null && error !== undefined) {
        return (
            <section aria-live="assertive" className={containerClassName}>
                {stateCaption}
                <ErrorState
                    className="min-h-48"
                    message={error || t('states.error.message')}
                    onRetry={onRetry}
                />
            </section>
        );
    }

    if (rows.length === 0) {
        return (
            <section className={containerClassName}>
                {stateCaption}
                <EmptyState
                    className="min-h-48"
                    description={emptyDescription}
                    title={emptyTitle ?? t('states.empty.title')}
                />
            </section>
        );
    }

    return (
        <div className={containerClassName}>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[40rem] border-collapse text-sm">
                    {caption === undefined || caption === null ? null : (
                        <caption className="caption-top border-b border-[var(--line)] bg-[var(--surface-subtle)] px-5 py-3.5 text-start font-semibold text-[var(--ink)]">
                            {caption}
                        </caption>
                    )}
                    <thead className="bg-[var(--surface-subtle)]">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    className={[
                                        'border-b border-[var(--line)] px-5 py-3.5 text-start text-xs font-semibold uppercase tracking-[0.04em] text-[var(--ink-soft)]',
                                        column.headerClassName,
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    key={column.key}
                                    scope="col"
                                >
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                className="transition-colors duration-150 hover:bg-[var(--surface-subtle)] focus-within:bg-[var(--brand-soft)] [&_*:focus-visible]:outline-2 [&_*:focus-visible]:outline-offset-2 [&_*:focus-visible]:outline-[var(--focus-ring)]"
                                key={rowKey(row)}
                            >
                                {columns.map((column) => (
                                    <td
                                        className={[
                                        'border-b border-[var(--line)] px-5 py-4 align-top text-[var(--ink)] last:[&]:border-b-0',
                                            column.cellClassName,
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                        key={column.key}
                                    >
                                        {column.render(row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
