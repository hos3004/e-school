import { Head, router } from "@inertiajs/react";

import Button from "@/Components/Button";
import Card, {
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/Card";
import EmptyState from "@/Components/EmptyState";
import ErrorState from "@/Components/ErrorState";
import LoadingState from "@/Components/LoadingState";
import PageHeader from "@/Components/PageHeader";
import StatusPill from "@/Components/StatusPill";
import AppLayout from "@/Layouts/AppLayout";
import { formatDate, formatTime, useLocale } from "@/lib/format";
import { useI18n } from "@/lib/i18n";
import type { LoadablePageProps, Session, StatusColorMap } from "@/types";

import {
  MetricTile,
  SectionHeading,
  TeacherIcon,
} from "./Components/TeacherUi";

interface TeacherDashboardProps extends LoadablePageProps {
  todaysSessions?: Session[];
  pendingAttendance?: Session[];
  lateReports?: Session[];
  statusColors?: StatusColorMap;
}

interface SessionCardProps {
  actionLabelKey: string;
  featured?: boolean;
  session: Session;
  statusColors: StatusColorMap;
}

function SessionCard({
  actionLabelKey,
  featured = false,
  session,
  statusColors,
}: SessionCardProps) {
  const t = useI18n();
  const locale = useLocale();

  return (
    <Card
      as="article"
      className={[
        "flex h-full flex-col overflow-hidden transition-[border-color,box-shadow] duration-150 hover:border-[var(--line-strong)]",
        featured ? "border-[var(--brand)]/35 bg-[var(--brand-soft)]/35" : "",
      ].join(" ")}
      padding="none"
    >
      <div
        aria-hidden="true"
        className={`h-1 ${featured ? "bg-[var(--brand)]" : "bg-[var(--line)]"}`}
      />
      <div className="flex grow flex-col p-5">
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <CardTitle>{session.title}</CardTitle>
              {session.subject ? (
                <CardDescription>{session.subject}</CardDescription>
              ) : null}
            </div>
            <StatusPill colorMap={statusColors} status={session.status} />
          </div>
        </CardHeader>

        <CardContent className="mt-5 grow">
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div className="flex items-start gap-2.5 rounded-[var(--radius-md)] bg-[var(--surface)]/80 p-3">
              <TeacherIcon
                className="mt-0.5 size-4 shrink-0 text-[var(--brand)]"
                name="calendar"
              />
              <div>
                <dt className="font-semibold text-[var(--ink-muted)]">
                  {t("common.date")}
                </dt>
                <dd className="mt-0.5 font-medium text-[var(--ink)]">
                  <time dateTime={session.startsAt}>
                    {formatDate(session.startsAt, locale, session.timezone)}
                  </time>
                </dd>
              </div>
            </div>
            <div className="flex items-start gap-2.5 rounded-[var(--radius-md)] bg-[var(--surface)]/80 p-3">
              <TeacherIcon
                className="mt-0.5 size-4 shrink-0 text-[var(--brand)]"
                name="clock"
              />
              <div>
                <dt className="font-semibold text-[var(--ink-muted)]">
                  {t("common.time")}
                </dt>
                <dd
                  className="mt-0.5 whitespace-nowrap font-medium tabular-nums text-[var(--ink)]"
                  dir="ltr"
                >
                  <time dateTime={session.startsAt}>
                    {formatTime(session.startsAt, locale, session.timezone)}
                  </time>
                  <span aria-hidden="true">
                    {t("common.time_range_separator")}
                  </span>
                  <span className="sr-only">{t("common.until")}</span>
                  <time dateTime={session.endsAt}>
                    {formatTime(session.endsAt, locale, session.timezone)}
                  </time>
                </dd>
              </div>
            </div>
            {session.location ? (
              <div className="sm:col-span-2">
                <dt className="font-semibold text-[var(--ink-muted)]">
                  {t("sessions.location")}
                </dt>
                <dd className="mt-1 break-words text-[var(--ink)]">
                  {session.location}
                </dd>
              </div>
            ) : null}
          </dl>
        </CardContent>

        <CardFooter className="mt-5">
          <Button
            as="link"
            className="w-full sm:w-auto"
            href={"/teacher/sessions/" + session.id}
            variant={featured ? "primary" : "secondary"}
          >
            {t(actionLabelKey)}
          </Button>
        </CardFooter>
      </div>
    </Card>
  );
}

interface SessionSectionProps {
  actionLabelKey: string;
  descriptionKey: string;
  emptyDescriptionKey: string;
  emptyTitleKey: string;
  featuredFirst?: boolean;
  sessions: Session[];
  statusColors: StatusColorMap;
  titleKey: string;
}

function SessionSection({
  actionLabelKey,
  descriptionKey,
  emptyDescriptionKey,
  emptyTitleKey,
  featuredFirst = false,
  sessions,
  statusColors,
  titleKey,
}: SessionSectionProps) {
  const t = useI18n();

  return (
    <section aria-labelledby={titleKey} className="space-y-4">
      <SectionHeading
        description={t(descriptionKey)}
        id={titleKey}
        title={t(titleKey)}
        trailing={
          <span className="inline-flex min-h-8 min-w-8 items-center justify-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-2.5 text-sm font-semibold tabular-nums text-[var(--ink-soft)]">
            {sessions.length}
          </span>
        }
      />

      {sessions.length === 0 ? (
        <EmptyState
          description={t(emptyDescriptionKey)}
          title={t(emptyTitleKey)}
        />
      ) : (
        <div className="grid gap-4 xl:grid-cols-2">
          {sessions.map((session, index) => (
            <SessionCard
              actionLabelKey={actionLabelKey}
              featured={featuredFirst && index === 0}
              key={session.id}
              session={session}
              statusColors={statusColors}
            />
          ))}
        </div>
      )}
    </section>
  );
}

export default function TeacherDashboard({
  todaysSessions = [],
  pendingAttendance = [],
  lateReports = [],
  loading = false,
  error = null,
  statusColors = {},
}: TeacherDashboardProps) {
  const t = useI18n();
  const retry = () => {
    router.reload({
      only: [
        "todaysSessions",
        "pendingAttendance",
        "lateReports",
        "statusColors",
        "error",
      ],
    });
  };
  const isEmpty =
    todaysSessions.length === 0 &&
    pendingAttendance.length === 0 &&
    lateReports.length === 0;

  return (
    <AppLayout role="teacher">
      <Head title={t("teacher.dashboard.title")} />

      <div className="space-y-[var(--space-section)]">
        <PageHeader
          subtitle={t("teacher.dashboard.subtitle")}
          title={t("teacher.dashboard.title")}
        />

        {loading ? (
          <LoadingState label={t("teacher.dashboard.loading")} rows={3} />
        ) : error !== null && error !== undefined ? (
          <ErrorState
            message={error || t("states.error.message")}
            onRetry={retry}
          />
        ) : isEmpty ? (
          <EmptyState
            description={t("teacher.dashboard.empty.description")}
            title={t("teacher.dashboard.empty.title")}
          />
        ) : (
          <>
            <section
              aria-label={t("teacher.dashboard.subtitle")}
              className="grid gap-3 sm:grid-cols-3"
            >
              <MetricTile
                emphasis="brand"
                icon="calendar"
                label={t("teacher.dashboard.today.title")}
                value={todaysSessions.length}
              />
              <MetricTile
                emphasis={
                  pendingAttendance.length > 0 ? "attention" : "default"
                }
                icon="check"
                label={t("teacher.dashboard.pending_attendance.title")}
                value={pendingAttendance.length}
              />
              <MetricTile
                emphasis={lateReports.length > 0 ? "attention" : "default"}
                icon="document"
                label={t("teacher.dashboard.late_reports.title")}
                value={lateReports.length}
              />
            </section>
            <div className="space-y-10">
              <SessionSection
                actionLabelKey="teacher.dashboard.actions.open_session"
                descriptionKey="teacher.dashboard.today.description"
                emptyDescriptionKey="teacher.dashboard.today.empty.description"
                emptyTitleKey="teacher.dashboard.today.empty.title"
                featuredFirst
                sessions={todaysSessions}
                statusColors={statusColors}
                titleKey="teacher.dashboard.today.title"
              />
              <SessionSection
                actionLabelKey="teacher.dashboard.actions.confirm_attendance"
                descriptionKey="teacher.dashboard.pending_attendance.description"
                emptyDescriptionKey="teacher.dashboard.pending_attendance.empty.description"
                emptyTitleKey="teacher.dashboard.pending_attendance.empty.title"
                sessions={pendingAttendance}
                statusColors={statusColors}
                titleKey="teacher.dashboard.pending_attendance.title"
              />
              <SessionSection
                actionLabelKey="teacher.dashboard.actions.submit_report"
                descriptionKey="teacher.dashboard.late_reports.description"
                emptyDescriptionKey="teacher.dashboard.late_reports.empty.description"
                emptyTitleKey="teacher.dashboard.late_reports.empty.title"
                sessions={lateReports}
                statusColors={statusColors}
                titleKey="teacher.dashboard.late_reports.title"
              />
            </div>
          </>
        )}
      </div>
    </AppLayout>
  );
}
