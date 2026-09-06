import { Link, useForm, usePage } from "@inertiajs/react";
import type { FormEvent } from "react";
import {
  Metric,
  Panel,
  StudyChart,
  reportHref,
  type ReportOverview,
} from "@/Components/Console/DashboardParts";
import ConsoleIcon from "@/Components/Console/ConsoleIcon";
import "../../../css/console-dashboard.css";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import SessionTable, {
  type ReportRow,
} from "@/Components/Console/SessionTable";
import { useI18n } from "@/lib/i18n";
import { formatNumber } from "@/lib/console-format";
import type { AppPageProps } from "@/types";

type Filters = {
  preset?: string;
  from?: string;
  until?: string;
  search?: string;
  student_profile_id?: string;
  staff_profile_id?: string;
  original_staff_profile_id?: string;
  group_id?: string;
  course_id?: string;
  report_status?: string;
  statuses?: string[];
  attendance_statuses?: string[];
  session_types?: string[];
};
type Props = {
  summary: Record<string, number>;
  overview: ReportOverview;
  rows: ReportRow[];
  timezone: string;
  limitExceeded: boolean;
  filters: Filters;
  options: Record<string, Record<string, string>>;
  filterChoices: Record<
    "statuses" | "attendance_statuses" | "session_types",
    { value: string; label: string }[]
  >;
  pagination: {
    current: number;
    last: number;
    total: number;
    previousUrl: string | null;
    nextUrl: string | null;
  };
  exportUrl: string | null;
};
export default function Reports({
  summary,
  overview,
  rows,
  timezone,
  limitExceeded,
  filters,
  options,
  filterChoices,
  pagination,
  exportUrl,
}: Props) {
  const t = useI18n();
  const { locale = "ar", console: context } = usePage<
    AppPageProps & { console?: { navigation: { key: string; href: string }[] } }
  >().props;
  const available = (key: string) =>
    context?.navigation.find((item) => item.key === key);
  const form = useForm({
    preset: filters.preset ?? "this_week",
    from: filters.from ?? "",
    until: filters.until ?? "",
    search: filters.search ?? "",
    student_profile_id: filters.student_profile_id ?? "",
    staff_profile_id: filters.staff_profile_id ?? "",
    original_staff_profile_id: filters.original_staff_profile_id ?? "",
    group_id: filters.group_id ?? "",
    course_id: filters.course_id ?? "",
    report_status: filters.report_status ?? "",
    statuses: filters.statuses ?? [],
    attendance_statuses: filters.attendance_statuses ?? [],
    session_types: filters.session_types ?? [],
  });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.transform((values) => ({
      ...values,
      from: values.preset === "custom" ? values.from : "",
      until: values.preset === "custom" ? values.until : "",
    }));
    form.get("/manage/reports", { preserveState: true, preserveScroll: true });
  }
  const activeFilters = Object.entries(filters).filter(
    ([key, value]) =>
      !["preset", "from", "until"].includes(key) &&
      value !== "" &&
      (!Array.isArray(value) || value.length > 0),
  ).length;
  return (
    <ConsoleLayout
      section="after"
      title={t("console.reports_title")}
      description={t("console.reports_description")}
      actions={
        <>
          <a href="#report-filters" className="console-button">
            <ConsoleIcon name="settings" />
            {t("console_dashboard.filter_title")}
          </a>
          {exportUrl && (
            <a href={exportUrl} className="console-button primary">
              {t("console.export_pdf")}
            </a>
          )}
        </>
      }
    >
      <div className="console-dashboard">
        {limitExceeded && (
          <div className="console-feedback" role="alert">
            {t("console.limit_exceeded")}
          </div>
        )}
        <div id="report-summary" className="console-metrics">
          <Metric
            label={t("console_dashboard.planned")}
            value={summary.total ?? 0}
            icon="today"
            note={t("console.periods." + (filters.preset ?? "this_week"))}
          />
          <Metric
            label={t("console_dashboard.completed")}
            value={summary.completed ?? 0}
            icon="check"
            note={t("console_dashboard.completed_hint")}
          />
          <Metric
            label={t("console_dashboard.postponed")}
            value={summary.postponed ?? 0}
            icon="today"
          />
          <Metric
            label={t("console_dashboard.pending")}
            value={overview.review}
            icon="followup"
            note={t("console_dashboard.review_hint")}
          />
        </div>
        <div className="dashboard-report-grid">
          <Panel
            title={t("console_dashboard.chart_title")}
            subtitle={t("console_dashboard.chart_hint")}
            action={
              <a href="#report-details" className="console-link">
                {t("console_dashboard.all_reports")} ←
              </a>
            }
          >
            <StudyChart
              days={overview.days}
              timezone={timezone}
              filters={filters}
            />
            <div className="console-pagination">
              <span>
                {t("console_dashboard.completed")}:{" "}
                {formatNumber(summary.completed ?? 0)} /{" "}
                {formatNumber(summary.total ?? 0)}
              </span>
              <bdi>{timezone}</bdi>
            </div>
          </Panel>
          <Panel
            title={t("console_dashboard.report_shortcuts")}
            subtitle={t("console_dashboard.report_shortcuts_hint")}
          >
            <Link
              href={
                available("followup")?.href ??
                reportHref(filters, {
                  attendance_statuses: ["absent", "no_show"],
                }) + "#report-details"
              }
              className="dashboard-report-shortcut"
            >
              <ConsoleIcon name="students" />
              <span>
                <h3>{t("console_dashboard.regularity")}</h3>
                <p>{t("console_dashboard.regularity_hint")}</p>
              </span>
              <b>←</b>
            </Link>
            <a href="#report-groups" className="dashboard-report-shortcut">
              <ConsoleIcon name="groups" />
              <span>
                <h3>{t("console_dashboard.group_report")}</h3>
                <p>{t("console_dashboard.group_report_hint")}</p>
              </span>
              <b>←</b>
            </a>
            {available("teacher_dues") && (
              <Link
                href={available("teacher_dues")!.href}
                className="dashboard-report-shortcut"
              >
                <ConsoleIcon name="teachers" />
                <span>
                  <h3>{t("console_dashboard.teacher_report")}</h3>
                  <p>{t("console_dashboard.teacher_report_hint")}</p>
                </span>
                <b>←</b>
              </Link>
            )}
            <Link
              href={
                reportHref(filters, { report_status: "missing" }) +
                "#report-details"
              }
              className="dashboard-report-shortcut"
            >
              <ConsoleIcon name="followup" />
              <span>
                <h3>{t("console_dashboard.exceptions")}</h3>
                <p>{t("console_dashboard.exceptions_hint")}</p>
              </span>
              <b>←</b>
            </Link>
          </Panel>
        </div>
        <div id="report-groups">
          <Panel
            title={t("console_dashboard.group_summary")}
            action={
              <a href="#report-details" className="console-link">
                {t("console_dashboard.all_reports")} ←
              </a>
            }
          >
            {overview.groups.length ? (
              <div className="console-table-wrap">
                <table className="console-table">
                  <thead>
                    <tr>
                      {[
                        "console_dashboard.group_report",
                        "console_dashboard.chart_planned",
                        "console_dashboard.chart_completed",
                        "console_dashboard.review",
                        "console.columns.report",
                      ].map((key) => (
                        <th key={key}>{t(key)}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {overview.groups.map((group) => (
                      <tr key={group.id || group.courseId}>
                        <td>
                          <strong>{group.label}</strong>
                        </td>
                        <td>{formatNumber(group.planned)}</td>
                        <td>{formatNumber(group.completed)}</td>
                        <td>
                          <span
                            className={
                              "console-status " +
                              (group.pending ? "warning" : "success")
                            }
                          >
                            {group.pending
                              ? formatNumber(group.pending) +
                                " " +
                                t("console_dashboard.needs_review")
                              : t("console_dashboard.no_pending")}
                          </span>
                        </td>
                        <td>
                          <Link
                            href={
                              reportHref(
                                filters,
                                group.id
                                  ? { group_id: group.id }
                                  : { group_id: "", course_id: group.courseId },
                              ) + "#report-details"
                            }
                            className="console-link"
                          >
                            {t("console.open")} ←
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="console-empty">
                {t("console_dashboard.chart_empty")}
              </div>
            )}
          </Panel>
        </div>
        <section
          className="console-panel dashboard-report-filter"
          id="report-filters"
        >
          <div className="console-panel-header">
            <div>
              <h2>{t("console_dashboard.filter_title")}</h2>
              <p>{t("console_dashboard.filter_hint")}</p>
            </div>
          </div>
          <form
            onSubmit={submit}
            className="console-filter-bar console-filter-report"
          >
            <div className="dashboard-filter-scope console-filter-row">
              <div className="console-field">
                <label htmlFor="report-period">{t("console.period")}</label>
                <select
                  id="report-period"
                  value={form.data.preset}
                  onChange={(event) =>
                    form.setData("preset", event.target.value)
                  }
                >
                  {[
                    "today",
                    "yesterday",
                    "this_week",
                    "previous_week",
                    "this_month",
                    "custom",
                  ].map((period) => (
                    <option key={period} value={period}>
                      {t("console.periods." + period)}
                    </option>
                  ))}
                </select>
              </div>
              {form.data.preset === "custom" && (
                <div className="console-filter-range">
                  <div className="console-field">
                    <label htmlFor="report-from">{t("console.from")}</label>
                    <input
                      id="report-from"
                      type="date"
                      required
                      value={form.data.from}
                      onChange={(event) =>
                        form.setData("from", event.target.value)
                      }
                    />
                  </div>
                  <div className="console-field">
                    <label htmlFor="report-until">{t("console.until")}</label>
                    <input
                      id="report-until"
                      type="date"
                      required
                      value={form.data.until}
                      onChange={(event) =>
                        form.setData("until", event.target.value)
                      }
                    />
                  </div>
                </div>
              )}
              <div className="console-field console-filter-search">
                <label htmlFor="report-search">{t("console.search")}</label>
                <input
                  id="report-search"
                  value={form.data.search}
                  onChange={(event) =>
                    form.setData("search", event.target.value)
                  }
                />
              </div>
            </div>
            <div className="dashboard-filter-people console-filter-row">
              {(
                [
                  ["student_profile_id", "students", "console.nav.students"],
                  ["staff_profile_id", "teachers", "console.nav.teachers"],
                  [
                    "original_staff_profile_id",
                    "teachers",
                    "console.filters.original_teacher",
                  ],
                  ["group_id", "groups", "console.nav.groups"],
                  ["course_id", "courses", "console.nav.courses"],
                ] as const
              ).map(([key, list, label]) => (
                <div className="console-field" key={key}>
                  <label htmlFor={"filter-" + key}>{t(label)}</label>
                  <select
                    id={"filter-" + key}
                    value={form.data[key]}
                    onChange={(event) => form.setData(key, event.target.value)}
                  >
                    <option value="">{t("console.all")}</option>
                    {Object.entries(options[list] ?? {}).map(([id, name]) => (
                      <option value={id} key={id}>
                        {name}
                      </option>
                    ))}
                  </select>
                </div>
              ))}
              <div className="console-field">
                <label htmlFor="report-state">
                  {t("console.columns.report")}
                </label>
                <select
                  id="report-state"
                  value={form.data.report_status}
                  onChange={(event) =>
                    form.setData("report_status", event.target.value)
                  }
                >
                  <option value="">{t("console.all")}</option>
                  {["submitted", "late", "missing"].map((key) => (
                    <option key={key} value={key}>
                      {t("console.report_states." + key)}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="dashboard-filter-choices console-filter-choices">
              {(
                ["statuses", "attendance_statuses", "session_types"] as const
              ).map((key) => (
                <fieldset key={key} className="dashboard-choice-group">
                  <legend className="dashboard-choice-label">
                    {t("console.filters." + key)}
                  </legend>
                  <div className="dashboard-choice-options">
                    {filterChoices[key].map((choice) => (
                      <label key={choice.value} className="dashboard-choice">
                        <input
                          type="checkbox"
                          className="rounded text-teal-800 focus:ring-teal-700"
                          checked={form.data[key].includes(choice.value)}
                          onChange={(event) =>
                            form.setData(
                              key,
                              event.target.checked
                                ? [...form.data[key], choice.value]
                                : form.data[key].filter(
                                    (value) => value !== choice.value,
                                  ),
                            )
                          }
                        />
                        {choice.label}
                      </label>
                    ))}
                  </div>
                </fieldset>
              ))}
            </div>
            <div className="dashboard-filter-actions console-filter-actions console-filter-full">
              <button
                className="console-button primary"
                disabled={form.processing}
              >
                {t(
                  form.processing ? "console.loading" : "console.apply_filters",
                )}
              </button>
              <Link href="/manage/reports" className="console-button">
                {t("console.reset")}
              </Link>
              {activeFilters > 0 && (
                <span className="text-xs text-teal-800">
                  {formatNumber(activeFilters, locale)}{" "}
                  {t("console.filters.active")}
                </span>
              )}
              {Object.keys(form.errors).length > 0 && (
                <div
                  className="console-feedback is-error console-full"
                  role="alert"
                >
                  {Object.values(form.errors).join(" · ")}
                </div>
              )}
            </div>
          </form>
        </section>
        <section className="console-panel" id="report-details">
          <div className="console-panel-header">
            <div>
              <h2>{t("console.report_details")}</h2>
              <p>
                {t("console.timezone")} <bdi>{timezone}</bdi> ·{" "}
                {t("console.detail_explains_total")}
              </p>
            </div>
            <span>
              {formatNumber(pagination.total, locale)}{" "}
              {t("console.session_count")}
            </span>
          </div>
          <SessionTable rows={rows} timezone={timezone} />
          <div className="console-pagination">
            <span>
              {t("console.page")} {formatNumber(pagination.current, locale)} /{" "}
              {formatNumber(pagination.last, locale)}
            </span>
            <div className="console-actions">
              {pagination.previousUrl && (
                <Link
                  href={pagination.previousUrl}
                  className="console-button small"
                >
                  {t("console.previous")}
                </Link>
              )}
              {pagination.nextUrl && (
                <Link
                  href={pagination.nextUrl}
                  className="console-button small"
                >
                  {t("console.next")}
                </Link>
              )}
            </div>
          </div>
        </section>
      </div>
    </ConsoleLayout>
  );
}
