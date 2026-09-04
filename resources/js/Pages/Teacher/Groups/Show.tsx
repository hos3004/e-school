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
import AppLayout from "@/Layouts/AppLayout";
import { formatDate, formatDateTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps, StatusColorMap } from "@/types";

interface GroupStudent {
  id: string;
  name: string;
  code: string;
  joinedAt?: string | null;
}

interface GroupSession {
  id: string;
  title: string;
  startsAt: string;
  endsAt: string;
  timezone?: string;
  status: string;
}

interface GroupProgram {
  id: string;
  code: string;
  name: string;
}

interface TeacherGroupDetails {
  id: string;
  code: string;
  name: string;
  capacity: number;
  status: string;
  timezone: string;
  startsOn?: string | null;
  endsOn?: string | null;
  role: string;
  courseName?: string | null;
  programs: readonly GroupProgram[];
  students: readonly GroupStudent[];
  sessions: readonly GroupSession[];
}

interface Props extends LoadablePageProps {
  group?: TeacherGroupDetails | null;
  statusColors?: StatusColorMap;
}

export default function TeacherGroupShow({
  group = null,
  statusColors = {},
  loading = false,
  error = null,
}: Props) {
  const t = useI18n();
  const locale = useLocale();

  return (
    <AppLayout role="teacher">
      <Head title={group?.name ?? t("teacher.groups.details.title")} />
      <div className="space-y-[var(--space-section)]">
        <PageHeader
          action={
            <Button as="link" href="/teacher/groups" variant="secondary">
              {t("teacher.groups.details.back")}
            </Button>
          }
          subtitle={group?.code ?? t("teacher.groups.details.subtitle")}
          title={group?.name ?? t("teacher.groups.details.title")}
        />

        {loading ? (
          <LoadingState label={t("teacher.groups.loading")} rows={4} />
        ) : error ? (
          <ErrorState message={error} onRetry={() => router.reload()} />
        ) : !group ? (
          <EmptyState
            description={t("teacher.groups.details.empty_description")}
            title={t("teacher.groups.details.empty_title")}
          />
        ) : (
          <>
            <Card padding="md" variant="muted">
              <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("common.status")}
                  </dt>
                  <dd className="mt-2">
                    <StatusPill colorMap={statusColors} status={group.status} />
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.groups.students_label")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {group.students.length} / {group.capacity}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.groups.starts_on")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {group.startsOn
                      ? formatDate(group.startsOn, locale)
                      : t("common.not_available")}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-[var(--ink-muted)]">
                    {t("teacher.groups.details.programs")}
                  </dt>
                  <dd className="mt-1 font-semibold text-[var(--ink)]">
                    {[group.courseName, ...group.programs.map((item) => item.name)]
                      .filter(Boolean)
                      .join(" · ") || t("common.not_available")}
                  </dd>
                </div>
              </dl>
            </Card>

            <div className="grid items-start gap-6 xl:grid-cols-2">
              <Card as="section">
                <CardHeader>
                  <CardTitle as="h2">
                    {t("teacher.groups.details.sessions_title")}
                  </CardTitle>
                  <CardDescription>
                    {t("teacher.groups.details.sessions_description")}
                  </CardDescription>
                </CardHeader>
                {group.sessions.length === 0 ? (
                  <EmptyState
                    description={t("teacher.groups.details.sessions_empty_description")}
                    title={t("teacher.groups.details.sessions_empty_title")}
                  />
                ) : (
                  <ul className="mt-5 divide-y divide-[var(--line)] overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)]">
                    {group.sessions.map((session) => (
                      <li
                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        key={session.id}
                      >
                        <div>
                          <p className="font-semibold text-[var(--ink)]">
                            {session.title}
                          </p>
                          <p className="mt-1 text-sm text-[var(--ink-muted)]">
                            {formatDateTime(
                              session.startsAt,
                              locale,
                              session.timezone ?? group.timezone,
                            )}
                          </p>
                        </div>
                        <Button
                          as="link"
                          href={`/teacher/sessions/${session.id}`}
                          size="sm"
                          variant="secondary"
                        >
                          {t("teacher.groups.open_session")}
                        </Button>
                      </li>
                    ))}
                  </ul>
                )}
              </Card>

              <Card as="section">
                <CardHeader>
                  <CardTitle as="h2">
                    {t("teacher.groups.details.students_title")}
                  </CardTitle>
                  <CardDescription>
                    {t("teacher.groups.details.students_description")}
                  </CardDescription>
                </CardHeader>
                {group.students.length === 0 ? (
                  <EmptyState
                    description={t("teacher.groups.details.students_empty_description")}
                    title={t("teacher.groups.details.students_empty_title")}
                  />
                ) : (
                  <ul className="mt-5 divide-y divide-[var(--line)] overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)]">
                    {group.students.map((student) => (
                      <li
                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        key={student.id}
                      >
                        <div>
                          <p className="font-semibold text-[var(--ink)]">
                            {student.name}
                          </p>
                          <p className="mt-1 font-mono text-sm text-[var(--ink-muted)]">
                            {student.code}
                          </p>
                        </div>
                        <Button
                          as="link"
                          href={`/teacher/students/${student.id}`}
                          size="sm"
                          variant="secondary"
                        >
                          {t("teacher.students.view_profile")}
                        </Button>
                      </li>
                    ))}
                  </ul>
                )}
              </Card>
            </div>
          </>
        )}
      </div>
    </AppLayout>
  );
}
