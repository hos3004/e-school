import { Head, router } from "@inertiajs/react";

import Button from "@/Components/Button";
import Card, {
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/Card";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import StatusPill from "@/Components/StatusPill";
import type { StatusColorMap } from "@/Components/StatusPill";
import AppLayout from "@/Layouts/AppLayout";
import { formatDate, formatDateTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps } from "@/types";

import { MetricTile } from "./Components/TeacherUi";

interface GroupProgram {
  id: string;
  code: string;
  name: string;
}

interface NextSession {
  id: string;
  title: string;
  startsAt: string;
  endsAt: string;
  timezone?: string;
  status: string;
}

interface TeacherGroup {
  id: string;
  code: string;
  name: string;
  capacity: number;
  studentsCount: number;
  status: string;
  timezone: string;
  startsOn?: string | null;
  endsOn?: string | null;
  role: string;
  courseName?: string | null;
  programs: readonly GroupProgram[];
  nextSession?: NextSession | null;
}

interface Props extends LoadablePageProps {
  groups?: readonly TeacherGroup[];
}

const groupStatusColors: StatusColorMap<string> = {
  planning: "warning",
  active: "success",
  completed: "neutral",
};

export default function Groups({
  groups = [],
  loading = false,
  error = null,
}: Props) {
  const t = useI18n();
  const locale = useLocale();
  const studentsCount = groups.reduce(
    (sum, group) => sum + group.studentsCount,
    0,
  );
  const upcomingCount = groups.filter((group) => group.nextSession).length;

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.groups.title")} />
      <div className="space-y-[var(--space-section)]">
        <PageHeader
          title={t("teacher.groups.title")}
          subtitle={t("teacher.groups.subtitle")}
        />

        {!loading && !error && groups.length > 0 ? (
          <section
            aria-label={t("teacher.groups.title")}
            className="grid gap-3 sm:grid-cols-3"
          >
            <MetricTile
              emphasis="brand"
              icon="group"
              label={t("teacher.groups.title")}
              value={groups.length}
            />
            <MetricTile
              icon="profile"
              label={t("teacher.groups.students_label")}
              value={studentsCount}
            />
            <MetricTile
              icon="calendar"
              label={t("teacher.groups.next_session")}
              value={upcomingCount}
            />
          </section>
        ) : null}

        {loading ? (
          <LoadingState label={t("teacher.groups.loading")} rows={3} />
        ) : error ? (
          <ErrorState message={error} onRetry={() => router.reload()} />
        ) : groups.length === 0 ? (
          <EmptyState
            title={t("teacher.groups.empty_title")}
            description={t("teacher.groups.empty_description")}
          />
        ) : (
          <div className="grid gap-5 xl:grid-cols-2">
            {groups.map((group) => (
              <Card
                key={group.id}
                className="overflow-hidden transition-[border-color,box-shadow] duration-150 hover:border-[var(--line-strong)]"
                padding="none"
              >
                <div aria-hidden="true" className="h-1 bg-[var(--brand)]" />
                <div className="p-5 sm:p-6">
                  <CardHeader className="mb-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="font-mono text-sm text-[var(--brand)]">
                          {group.code}
                        </p>
                        <CardTitle className="mt-1">{group.name}</CardTitle>
                      </div>
                      <StatusPill
                        colorMap={groupStatusColors}
                        status={group.status}
                      />
                    </div>
                    {group.programs.length > 0 || group.courseName ? (
                      <CardDescription>
                        {[
                          group.courseName,
                          ...group.programs.map((program) => program.name),
                        ]
                          .filter(Boolean)
                          .join(" · ")}
                      </CardDescription>
                    ) : null}
                  </CardHeader>

                  <dl className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3">
                      <dt className="text-xs text-[var(--ink-muted)]">
                        {t("teacher.groups.students_label")}
                      </dt>
                      <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                        {group.studentsCount} / {group.capacity}
                      </dd>
                    </div>
                    <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3">
                      <dt className="text-xs text-[var(--ink-muted)]">
                        {t("teacher.groups.role")}
                      </dt>
                      <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                        {t(`group_roles.${group.role}`)}
                      </dd>
                    </div>
                    <div className="rounded-[var(--radius-md)] bg-[var(--surface-subtle)] p-3">
                      <dt className="text-xs text-[var(--ink-muted)]">
                        {t("teacher.groups.starts_on")}
                      </dt>
                      <dd className="mt-1 text-sm font-semibold text-[var(--ink)]">
                        {group.startsOn
                          ? formatDate(group.startsOn, locale)
                          : t("common.not_available")}
                      </dd>
                    </div>
                  </dl>

                  {group.nextSession ? (
                    <div className="mt-5 rounded-[var(--radius-md)] border border-[var(--line)] bg-[var(--brand-soft)]/35 p-4">
                      <p className="text-xs font-bold text-[var(--ink)]">
                        {t("teacher.groups.next_session")}
                      </p>
                      <p className="mt-1 text-sm text-[var(--ink)]">
                        {group.nextSession.title}
                      </p>
                      <p className="mt-1 text-sm text-[var(--ink-muted)]">
                        {formatDateTime(
                          group.nextSession.startsAt,
                          locale,
                          group.nextSession.timezone,
                        )}
                      </p>
                      <div className="mt-3">
                        <Button
                          as="link"
                          className="w-full sm:w-auto"
                          href={`/teacher/sessions/${group.nextSession.id}`}
                          size="sm"
                          variant="secondary"
                        >
                          {t("teacher.groups.open_session")}
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <p className="mt-4 text-sm text-[var(--ink-muted)]">
                      {t("teacher.groups.no_next_session")}
                    </p>
                  )}

                  <div className="mt-5 border-t border-[var(--line)] pt-4">
                    <Button
                      as="link"
                      className="w-full"
                      href={`/teacher/groups/${group.id}`}
                      variant="secondary"
                    >
                      {t("teacher.groups.view_details")}
                    </Button>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
