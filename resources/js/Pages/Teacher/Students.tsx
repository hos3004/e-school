import { Head, router } from "@inertiajs/react";
import { useMemo, useState } from "react";

import Card from "@/Components/Card";
import DataTable from "@/Components/DataTable";
import type { DataTableColumn } from "@/Components/DataTable";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import AppLayout from "@/Layouts/AppLayout";
import { formatDate, formatPercent, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps } from "@/types";

import { MetricTile, teacherFieldClasses } from "./Components/TeacherUi";

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
  const [search, setSearch] = useState("");
  const [groupFilter, setGroupFilter] = useState("");

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
        groupFilter === "" || student.groupId === groupFilter;
      const matchesSearch =
        needle === "" ||
        student.name.toLocaleLowerCase().includes(needle) ||
        student.code.toLocaleLowerCase().includes(needle);

      return matchesGroup && matchesSearch;
    });
  }, [students, search, groupFilter]);
  const openAssignmentsCount = students.reduce(
    (sum, student) => sum + student.openAssignmentsCount,
    0,
  );

  const columns: readonly DataTableColumn<TeacherStudent>[] = [
    {
      key: "student",
      header: t("teacher.students.columns.student"),
      render: (student) => (
        <div className="min-w-44">
          <p className="font-semibold text-[var(--ink)]">{student.name}</p>
          <p className="mt-1 font-mono text-sm text-[var(--ink-muted)]">
            {student.code}
          </p>
        </div>
      ),
    },
    {
      key: "group",
      header: t("teacher.students.columns.group"),
      render: (student) => student.groupName,
    },
    {
      key: "attendance_rate",
      header: t("teacher.students.columns.attendance_rate"),
      render: (student) =>
        student.attendanceRate === null ||
        student.attendanceRate === undefined ? (
          <span className="text-[var(--ink-muted)]">
            {t("common.not_available")}
          </span>
        ) : (
          formatPercent(student.attendanceRate, locale)
        ),
    },
    {
      key: "open_assignments",
      header: t("teacher.students.columns.open_assignments"),
      render: (student) => student.openAssignmentsCount,
    },
    {
      key: "joined_at",
      header: t("teacher.students.columns.joined_at"),
      render: (student) =>
        student.joinedAt ? (
          formatDate(student.joinedAt, locale)
        ) : (
          <span className="text-[var(--ink-muted)]">
            {t("common.not_available")}
          </span>
        ),
    },
  ];

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.students.title")} />
      <div className="space-y-[var(--space-section)]">
        <PageHeader
          title={t("teacher.students.title")}
          subtitle={t("teacher.students.subtitle")}
        />

        {!loading && !error && students.length > 0 ? (
          <section
            aria-label={t("teacher.students.title")}
            className="grid gap-3 sm:grid-cols-3"
          >
            <MetricTile
              emphasis="brand"
              icon="profile"
              label={t("teacher.students.title")}
              value={students.length}
            />
            <MetricTile
              icon="group"
              label={t("teacher.students.all_groups")}
              value={groups.length}
            />
            <MetricTile
              icon="document"
              label={t("teacher.students.columns.open_assignments")}
              value={openAssignmentsCount}
            />
          </section>
        ) : null}

        <Card
          className="grid gap-4 sm:grid-cols-2"
          padding="md"
          variant="muted"
        >
          <label className="text-sm font-semibold text-[var(--ink)]">
            <span>{t("teacher.students.search")}</span>
            <input
              className={teacherFieldClasses}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t("teacher.students.search")}
              type="search"
              value={search}
            />
          </label>

          <label className="text-sm font-semibold text-[var(--ink)]">
            <span>{t("teacher.students.columns.group")}</span>
            <select
              className={teacherFieldClasses}
              onChange={(event) => setGroupFilter(event.target.value)}
              value={groupFilter}
            >
              <option value="">{t("teacher.students.all_groups")}</option>
              {groups.map(([id, name]) => (
                <option key={id} value={id}>
                  {name}
                </option>
              ))}
            </select>
          </label>
        </Card>

        <div className="lg:hidden">
          {loading ? (
            <LoadingState label={t("teacher.students.loading")} rows={4} />
          ) : error !== null && error !== undefined ? (
            <ErrorState
              message={error || t("states.error.message")}
              onRetry={() => router.reload()}
            />
          ) : filtered.length === 0 ? (
            <EmptyState
              title={t("teacher.students.empty_title")}
              description={t("teacher.students.empty_description")}
            />
          ) : (
            <ul className="space-y-3">
              {filtered.map((student) => (
                <li key={`${student.groupId}:${student.id}`}>
                  <Card as="article">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h2 className="font-semibold text-[var(--ink)]">
                          {student.name}
                        </h2>
                        <p
                          className="mt-1 font-mono text-sm text-[var(--ink-muted)]"
                          dir="ltr"
                        >
                          {student.code}
                        </p>
                      </div>
                      <span className="rounded-full bg-[var(--brand-soft)] px-3 py-1 text-xs font-semibold text-[var(--brand-strong)]">
                        {student.groupName}
                      </span>
                    </div>
                    <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-[var(--line)] pt-4 text-sm">
                      <div>
                        <dt className="text-xs text-[var(--ink-muted)]">
                          {t("teacher.students.columns.attendance_rate")}
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums text-[var(--ink)]">
                          {student.attendanceRate === null ||
                          student.attendanceRate === undefined
                            ? t("common.not_available")
                            : formatPercent(student.attendanceRate, locale)}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-xs text-[var(--ink-muted)]">
                          {t("teacher.students.columns.open_assignments")}
                        </dt>
                        <dd className="mt-1 font-semibold tabular-nums text-[var(--ink)]">
                          {student.openAssignmentsCount}
                        </dd>
                      </div>
                    </dl>
                  </Card>
                </li>
              ))}
            </ul>
          )}
        </div>

        <DataTable
          className="hidden lg:block"
          caption={t("teacher.students.table_caption")}
          columns={columns}
          emptyDescription={t("teacher.students.empty_description")}
          emptyTitle={t("teacher.students.empty_title")}
          error={error}
          loading={loading}
          loadingLabel={t("teacher.students.loading")}
          onRetry={() => router.reload()}
          rowKey={(student) => `${student.groupId}:${student.id}`}
          rows={filtered}
        />
      </div>
    </AppLayout>
  );
}
