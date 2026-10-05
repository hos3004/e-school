import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import SessionTable, {
  type ReportRow,
} from "@/Components/Console/SessionTable";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import {
  Metric,
  Panel,
  type DayTotal,
  type ReportOverview,
} from "@/Components/Console/DashboardParts";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber, formatTime } from "@/lib/console-format";
import type { AppPageProps } from "@/types";
import "../../../css/console-dashboard.css";

type Props = {
  canSchedule: boolean;
  summary: Record<string, number> | null;
  sessions: ReportRow[];
  timezone: string;
  date: string;
  limitExceeded: boolean;
  overview: ReportOverview | null;
  week: DayTotal[];
  recent: ReportRow[];
  preparation: {
    id: string;
    label: string;
    missing: string[];
    can_activate: boolean;
  }[];
  preparationCount: number;
  registration: { requests: number; accepted: number } | null;
};
export default function Workspace({
  canSchedule,
  summary,
  sessions,
  timezone,
  date,
  limitExceeded,
  overview,
  week,
  recent,
  preparation,
  preparationCount,
  registration,
}: Props) {
  const t = useI18n();
  const { console: data } = usePage<
    AppPageProps & {
      console: { navigation: { key: string; href: string; label: string }[] };
    }
  >().props;
  const links = data?.navigation ?? [];
  const available = (key: string) => links.some((link) => link.key === key);
  const [tab, setTab] = useState("all");
  const [search, setSearch] = useState("");
  const displayed = sessions.filter(
    (row) =>
      (tab === "all" ||
        (tab === "done"
          ? row.status === "completed"
          : ["scheduled", "confirmed", "in_progress", "draft"].includes(
              row.status,
            ))) &&
      [row.title, row.course, row.group, row.actual_teacher]
        .join(" ")
        .includes(search.trim()),
  );
  const followupCount =
    (overview?.review ?? 0) +
    (registration?.accepted ?? 0) +
    (registration?.requests ?? 0);
  const dayFormat = new Intl.DateTimeFormat("ar", {
    weekday: "short",
    numberingSystem: "latn",
    timeZone: "UTC",
  });
  return (
    <ConsoleLayout
      title={t("console.today_title")}
      description={formatDate(date, timezone)}
      section="during"
      actions={
        <>
          {(available("sessions") || available("reports")) && (
            <Link
              href={
                available("sessions")
                  ? "/manage/sessions"
                  : "/manage/reports?preset=this_week#report-details"
              }
              className="console-button"
            >
              <ConsoleIcon name="today" />
              {t("console_dashboard.calendar")}
            </Link>
          )}
          {canSchedule && (
            <Link
              href="/manage/schedules/create"
              className="console-button primary"
            >
              <ConsoleIcon name="placement" />
              {t("console_sessions.add")}
            </Link>
          )}
        </>
      }
    >
      <div className="console-dashboard">
        {limitExceeded && (
          <div className="console-feedback" role="status">
            {t("console.limit_exceeded")}
          </div>
        )}
        {summary && (
          <div className="console-metrics">
            <Metric
              label={t("console.today_sessions")}
              value={summary.total ?? 0}
              icon="today"
              note={t("console_dashboard.today_hint")}
            />
            <Metric
              label={t("console_dashboard.completed")}
              value={summary.completed ?? 0}
              icon="check"
              note={t("console_dashboard.completed_hint")}
            />
            <Metric
              label={t("console_dashboard.live")}
              value={overview?.live ?? 0}
              icon="live"
              note={t("console_dashboard.live_hint")}
            />
            <Metric
              label={t("console_dashboard.review")}
              value={overview?.review ?? 0}
              icon="followup"
              note={t("console_dashboard.review_hint")}
            />
          </div>
        )}
        <div className="dashboard-daily-grid">
          <Panel
            title={t("console.today_sessions")}
            subtitle={t("console.timezone") + " " + timezone}
            action={
              <span className="console-status">
                {formatNumber(sessions.length)} {t("console.session_count")}
              </span>
            }
          >
            <div
              className="dashboard-tabs"
              role="group"
              aria-label={t("console.today_sessions")}
            >
              {[
                ["all", "all_sessions"],
                ["next", "next_sessions"],
                ["done", "done_sessions"],
              ].map(([value, label]) => (
                <button
                  key={value}
                  aria-pressed={tab === value}
                  className={tab === value ? "current" : ""}
                  onClick={() => setTab(value ?? "all")}
                >
                  {t("console_dashboard." + label)}
                </button>
              ))}
            </div>
            <div className="dashboard-table-search console-field">
              <label className="sr-only" htmlFor="today-search">
                {t("console_dashboard.search_sessions")}
              </label>
              <input
                id="today-search"
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={t("console_dashboard.search_sessions")}
              />
            </div>
            {summary ? (
              <SessionTable rows={displayed} timezone={timezone} compact />
            ) : (
              <div className="console-empty">
                {t("console.report_permission")}
              </div>
            )}
            <div className="console-pagination">
              <span>
                {formatNumber(displayed.length)} /{" "}
                {formatNumber(sessions.length)} {t("console.session_count")}
              </span>
              {available("reports") && (
                <Link
                  className="console-link"
                  href="/manage/reports?preset=this_week"
                >
                  {t("console_dashboard.week")} ←
                </Link>
              )}
            </div>
          </Panel>
          <aside>
            <Panel
              title={t("console.needs_followup")}
              action={
                <span className="console-status warning">
                  {formatNumber(followupCount)}
                </span>
              }
            >
              {(overview?.review ?? 0) > 0 && (
                <div className="dashboard-issue">
                  <ConsoleIcon name="followup" />
                  <div>
                    <h3>
                      {formatNumber(overview?.review ?? 0)}{" "}
                      {t("console_dashboard.review")}
                    </h3>
                    <p>{t("console_dashboard.review_hint")}</p>
                    <Link
                      className="console-link"
                      href="/manage/reports?preset=today#report-details"
                    >
                      {t("console_dashboard.review_sessions")} ←
                    </Link>
                  </div>
                </div>
              )}
              {(registration?.accepted ?? 0) > 0 && (
                <div className="dashboard-issue">
                  <ConsoleIcon name="students" />
                  <div>
                    <h3>
                      {formatNumber(registration?.accepted ?? 0)}{" "}
                      {t("console_dashboard.waiting")}
                    </h3>
                    <Link
                      className="console-link"
                      href={
                        available("placement")
                          ? "/manage/placement"
                          : "/manage/registration?stage=accepted"
                      }
                    >
                      {t("console_dashboard.placement")} ←
                    </Link>
                  </div>
                </div>
              )}
              {(registration?.requests ?? 0) > 0 && (
                <div className="dashboard-issue">
                  <ConsoleIcon name="registration" />
                  <div>
                    <h3>
                      {formatNumber(registration?.requests ?? 0)}{" "}
                      {t("console_dashboard.requests")}
                    </h3>
                    <Link
                      className="console-link"
                      href="/manage/registration?stage=requests"
                    >
                      {t("console_dashboard.review_requests")} ←
                    </Link>
                  </div>
                </div>
              )}
              {followupCount === 0 && (
                <div className="dashboard-quiet">
                  <ConsoleIcon name="check" />
                  <span>{t("console_dashboard.followup_clear")}</span>
                </div>
              )}
              {available("followup") && (
                <div className="console-pagination">
                  <Link href="/manage/followup" className="console-link">
                    {t("console_dashboard.followup_open")} ←
                  </Link>
                </div>
              )}
            </Panel>
            {available("reports") && (
              <Panel
                title={t("console_dashboard.week")}
                action={<ConsoleIcon name="today" />}
              >
                <div className="dashboard-week">
                  {week.map((day) => (
                    <Link
                      key={day.date}
                      href={
                        "/manage/reports?preset=custom&from=" +
                        day.date +
                        "&until=" +
                        day.date +
                        "#report-details"
                      }
                      className={day.date === date ? "selected" : ""}
                      aria-label={
                        day.date +
                        " · " +
                        day.planned +
                        " " +
                        t("console.session_count")
                      }
                    >
                      <span>
                        {dayFormat.format(new Date(day.date + "T12:00:00Z"))}
                      </span>
                      <b>{Number(day.date.slice(-2))}</b>
                      <i className={day.planned ? "has-sessions" : ""} />
                    </Link>
                  ))}
                </div>
                <div className="dashboard-quiet">
                  <ConsoleIcon name="check" />
                  <span>{t("console_dashboard.week_hint")}</span>
                </div>
              </Panel>
            )}
          </aside>
        </div>
        <div className="dashboard-secondary-grid">
          {summary && (
            <Panel
              title={t("console_dashboard.recent")}
              action={<ConsoleIcon name="check" />}
            >
              {recent.length ? (
                recent.map((row) => (
                  <div className="dashboard-activity" key={row.id}>
                    <span>
                      <b>{row.title || row.course}</b>
                      <small>
                        {row.actual_teacher} ·{" "}
                        {row.group || row.session_type_label}
                      </small>
                    </span>
                    <span className="dashboard-activity-time">
                      <bdi>{formatDate(row.scheduled_start, timezone)}</bdi>
                      <small>
                        <bdi>{formatTime(row.scheduled_start, timezone)}</bdi>
                      </small>
                    </span>
                  </div>
                ))
              ) : (
                <div className="console-empty">
                  {t("console_dashboard.recent_empty")}
                </div>
              )}
            </Panel>
          )}
          {available("groups") && (
            <Panel
              title={t("console_dashboard.preparation")}
              action={
                <Link href="/manage/groups" className="console-link">
                  {formatNumber(preparationCount)} ·{" "}
                  {t("console_dashboard.open_courses")} ←
                </Link>
              }
            >
              {preparation.length ? (
                preparation.map((group) => (
                  <div className="dashboard-activity" key={group.id}>
                    <span>
                      <Link
                        className="console-link"
                        href={"/manage/groups?group=" + group.id}
                      >
                        {group.label}
                      </Link>
                      <small>
                        {group.missing.length
                          ? t("console_dashboard.missing") +
                            ": " +
                            group.missing
                              .map((key) => t("console_courses.fields." + key))
                              .join("، ")
                          : t("console_dashboard.ready")}
                      </small>
                    </span>
                    <span
                      className={
                        "console-status " +
                        (group.can_activate ? "success" : "warning")
                      }
                    >
                      {t("console_dashboard.preparing")}
                    </span>
                  </div>
                ))
              ) : (
                <div className="console-empty">
                  {t("console_dashboard.preparation_empty")}
                </div>
              )}
            </Panel>
          )}
        </div>
      </div>
    </ConsoleLayout>
  );
}
