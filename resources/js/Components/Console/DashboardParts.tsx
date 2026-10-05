import { Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import ConsoleIcon from "./ConsoleIcon";
import { useI18n } from "@/lib/i18n";
import { formatNumber } from "@/lib/console-format";

export type DayTotal = {
  date: string;
  planned: number;
  completed: number;
  pending: number;
};
export type GroupTotal = {
  id: string;
  courseId: string;
  label: string;
  planned: number;
  completed: number;
  pending: number;
  absent: number;
};
export type ReportOverview = {
  days: DayTotal[];
  groups: GroupTotal[];
  live: number;
  review: number;
};

export function Metric({
  label,
  value,
  note,
  icon,
}: {
  label: string;
  value: number | null;
  note?: string;
  icon: string;
}) {
  return (
    <div className="console-metric dashboard-metric">
      <div className="dashboard-metric-label">
        <ConsoleIcon name={icon} />
        <span>{label}</span>
      </div>
      <div className="dashboard-metric-bottom">
        <strong>{value === null ? "—" : formatNumber(value)}</strong>
        {note && <small>{note}</small>}
      </div>
    </div>
  );
}
export function Panel({
  title,
  subtitle,
  action,
  children,
  className = "",
}: {
  title: string;
  subtitle?: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <section className={"console-panel " + className}>
      <div className="console-panel-header">
        <div>
          <h2>{title}</h2>
          {subtitle && <p>{subtitle}</p>}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}
export function reportHref(
  filters: Record<string, string | string[] | undefined>,
  overrides: Record<string, string | string[]> = {},
) {
  const parameters = new URLSearchParams();
  Object.entries({ ...filters, ...overrides }).forEach(([key, value]) => {
    if (Array.isArray(value))
      value.forEach((item, index) =>
        parameters.set(key + "[" + index + "]", item),
      );
    else if (value) parameters.set(key, value);
  });
  return "/manage/reports?" + parameters.toString();
}
export function StudyChart({
  days,
  filters = {},
}: {
  days: DayTotal[];
  timezone: string;
  filters?: Record<string, string | string[] | undefined>;
}) {
  const t = useI18n();
  const max = Math.max(1, ...days.map((day) => day.planned));
  const formatter = new Intl.DateTimeFormat("ar", {
    weekday: "short",
    day: "numeric",
    month: days.length > 7 ? "short" : undefined,
    numberingSystem: "latn",
    timeZone: "UTC",
  });
  const title = (day: DayTotal) =>
    formatter.format(new Date(day.date + "T12:00:00Z"));
  const href = (day: DayTotal) =>
    reportHref(filters, { preset: "custom", from: day.date, until: day.date }) +
    "#report-details";
  return (
    <>
      <div className="dashboard-chart-legend">
        <span>
          <i />
          {t("console_dashboard.chart_completed")}
        </span>
        <span>
          <i className="pale" />
          {t("console_dashboard.chart_planned")}
        </span>
      </div>
      {!days.some((day) => day.planned) ? (
        <div className="console-empty">
          {t("console_dashboard.chart_empty")}
        </div>
      ) : (
        <div className="dashboard-chart-scroll">
          <div
            className="dashboard-chart"
            style={{ minWidth: days.length * 58 }}
          >
            {days.map((day) => (
              <Link
                href={href(day)}
                key={day.date}
                className="dashboard-chart-col"
                aria-label={
                  title(day) +
                  ": " +
                  t("console_dashboard.planned") +
                  " " +
                  day.planned +
                  "، " +
                  t("console_dashboard.completed") +
                  " " +
                  day.completed
                }
              >
                <div className="dashboard-chart-bars">
                  <span
                    style={{
                      height: Math.max(
                        day.completed ? 4 : 0,
                        (day.completed / max) * 150,
                      ),
                    }}
                  >
                    <b>{formatNumber(day.completed)}</b>
                  </span>
                  <span
                    className="pale"
                    style={{
                      height: Math.max(
                        day.planned ? 4 : 0,
                        (day.planned / max) * 150,
                      ),
                    }}
                  >
                    <b>{formatNumber(day.planned)}</b>
                  </span>
                </div>
                <small>{title(day)}</small>
              </Link>
            ))}
          </div>
        </div>
      )}
    </>
  );
}
