import { Head, router } from "@inertiajs/react";

import Button from "@/Components/Button";
import Card from "@/Components/Card";
import DataTable from "@/Components/DataTable";
import type { DataTableColumn } from "@/Components/DataTable";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import StatusPill from "@/Components/StatusPill";
import AppLayout from "@/Layouts/AppLayout";
import { formatDate, formatTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps, Session, StatusColorMap } from "@/types";

import { MetricTile, TeacherIcon } from "./Components/TeacherUi";

interface TeacherScheduleProps extends LoadablePageProps {
  sessions?: Session[];
  statusColors?: StatusColorMap;
}

export default function TeacherSchedule({
  sessions = [],
  loading = false,
  error = null,
  statusColors = {},
}: TeacherScheduleProps) {
  const t = useI18n();
  const locale = useLocale();
  const retry = () => {
    router.reload({
      only: ["sessions", "statusColors", "error"],
    });
  };

  const columns: readonly DataTableColumn<Session>[] = [
    {
      key: "session",
      header: t("teacher.schedule.columns.session"),
      render: (session) => (
        <div className="min-w-48">
          <p className="font-semibold text-[var(--ink)]">{session.title}</p>
          {session.subject ? (
            <p className="mt-1 text-sm text-[var(--ink-muted)]">
              {session.subject}
            </p>
          ) : null}
        </div>
      ),
    },
    {
      key: "date",
      header: t("teacher.schedule.columns.date"),
      render: (session) => (
        <time dateTime={session.startsAt}>
          {formatDate(session.startsAt, locale, session.timezone)}
        </time>
      ),
    },
    {
      key: "time",
      header: t("teacher.schedule.columns.time"),
      render: (session) => (
        <div className="whitespace-nowrap">
          <time dateTime={session.startsAt}>
            {formatTime(session.startsAt, locale, session.timezone)}
          </time>
          <span aria-hidden="true">{t("common.time_range_separator")}</span>
          <span className="sr-only">{t("common.until")}</span>
          <time dateTime={session.endsAt}>
            {formatTime(session.endsAt, locale, session.timezone)}
          </time>
        </div>
      ),
    },
    {
      key: "location",
      header: t("teacher.schedule.columns.location"),
      render: (session) => (
        <span className="break-words">
          {session.location ?? t("common.not_available")}
        </span>
      ),
    },
    {
      key: "status",
      header: t("teacher.schedule.columns.status"),
      render: (session) => (
        <StatusPill colorMap={statusColors} status={session.status} />
      ),
    },
    {
      key: "actions",
      header: t("teacher.schedule.columns.actions"),
      render: (session) => (
        <Button
          as="link"
          href={"/teacher/sessions/" + session.id}
          size="sm"
          variant="secondary"
        >
          {t("teacher.schedule.actions.view")}
        </Button>
      ),
    },
  ];

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.schedule.title")} />

      <div className="space-y-[var(--space-section)]">
        <PageHeader
          subtitle={t("teacher.schedule.subtitle")}
          title={t("teacher.schedule.title")}
        />

        {!loading && !error ? (
          <section
            aria-label={t("teacher.schedule.table_caption")}
            className="max-w-sm"
          >
            <MetricTile
              icon="calendar"
              label={t("teacher.schedule.table_caption")}
              value={sessions.length}
              emphasis="brand"
            />
          </section>
        ) : null}

        <div className="lg:hidden">
          {loading ? (
            <LoadingState label={t("teacher.schedule.loading")} rows={4} />
          ) : error !== null && error !== undefined ? (
            <ErrorState
              message={error || t("states.error.message")}
              onRetry={retry}
            />
          ) : sessions.length === 0 ? (
            <EmptyState
              title={t("teacher.schedule.empty.title")}
              description={t("teacher.schedule.empty.description")}
            />
          ) : (
            <ol className="space-y-3">
              {sessions.map((session) => (
                <li key={session.id}>
                  <Card as="article" className="overflow-hidden" padding="none">
                    <div className="border-s-4 border-[var(--brand)] p-4">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                          <h2 className="font-semibold leading-snug text-[var(--ink)] [text-wrap:balance]">
                            {session.title}
                          </h2>
                          {session.subject ? (
                            <p className="mt-1 text-sm text-[var(--ink-muted)]">
                              {session.subject}
                            </p>
                          ) : null}
                        </div>
                        <StatusPill
                          colorMap={statusColors}
                          status={session.status}
                        />
                      </div>
                      <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div className="flex gap-2.5">
                          <TeacherIcon
                            className="mt-0.5 size-4 shrink-0 text-[var(--brand)]"
                            name="calendar"
                          />
                          <div>
                            <dt className="text-xs font-semibold text-[var(--ink-muted)]">
                              {t("teacher.schedule.columns.date")}
                            </dt>
                            <dd className="mt-0.5 text-[var(--ink)]">
                              {formatDate(
                                session.startsAt,
                                locale,
                                session.timezone,
                              )}
                            </dd>
                          </div>
                        </div>
                        <div className="flex gap-2.5">
                          <TeacherIcon
                            className="mt-0.5 size-4 shrink-0 text-[var(--brand)]"
                            name="clock"
                          />
                          <div>
                            <dt className="text-xs font-semibold text-[var(--ink-muted)]">
                              {t("teacher.schedule.columns.time")}
                            </dt>
                            <dd
                              className="mt-0.5 whitespace-nowrap tabular-nums text-[var(--ink)]"
                              dir="ltr"
                            >
                              {formatTime(
                                session.startsAt,
                                locale,
                                session.timezone,
                              )}
                              <span aria-hidden="true">
                                {t("common.time_range_separator")}
                              </span>
                              {formatTime(
                                session.endsAt,
                                locale,
                                session.timezone,
                              )}
                            </dd>
                          </div>
                        </div>
                      </dl>
                      <div className="mt-4 border-t border-[var(--line)] pt-4">
                        <Button
                          as="link"
                          fullWidth
                          href={"/teacher/sessions/" + session.id}
                          variant="secondary"
                        >
                          {t("teacher.schedule.actions.view")}
                        </Button>
                      </div>
                    </div>
                  </Card>
                </li>
              ))}
            </ol>
          )}
        </div>

        <div className="hidden lg:block">
          <DataTable
            caption={t("teacher.schedule.table_caption")}
            columns={columns}
            emptyDescription={t("teacher.schedule.empty.description")}
            emptyTitle={t("teacher.schedule.empty.title")}
            error={error}
            loading={loading}
            loadingLabel={t("teacher.schedule.loading")}
            onRetry={retry}
            rowKey={(session) => session.id}
            rows={sessions}
          />
        </div>
      </div>
    </AppLayout>
  );
}
