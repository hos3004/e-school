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
        'overflow-hidden rounded-xl border border-[var(--ink-muted)] bg-[var(--surface)]',
        className,
    ]
        .filter(Boolean)
        .join(' ');
    const stateCaption =
        caption === undefined || caption === null ? null : (
            <div className="border-b border-[var(--ink-muted)] bg-[var(--surface-muted)] ps-4 pe-4 py-3 text-start text-sm font-semibold text-[var(--ink)]">
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
                        <caption className="caption-top border-b border-[var(--ink-muted)] bg-[var(--surface-muted)] ps-4 pe-4 py-3 text-start font-semibold text-[var(--ink)]">
                            {caption}
                        </caption>
                    )}
                    <thead className="bg-[var(--surface-muted)]">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    className={[
                                        'border-b border-[var(--ink-muted)] ps-4 pe-4 py-3 text-start font-semibold text-[var(--ink)]',
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
                                className="transition-colors hover:bg-[var(--surface-muted)] focus-within:bg-[var(--surface-muted)] [&_*:focus-visible]:outline-2 [&_*:focus-visible]:outline-offset-2 [&_*:focus-visible]:outline-[var(--brand)]"
                                key={rowKey(row)}
                            >
                                {columns.map((column) => (
                                    <td
                                        className={[
                                            'border-b border-[var(--surface-muted)] ps-4 pe-4 py-3 align-top text-[var(--ink)]',
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
