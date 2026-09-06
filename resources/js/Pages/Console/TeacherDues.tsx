import { Link, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatNumber } from "@/lib/console-format";
import TeacherDuesDetail, { DuesMoney } from "./TeacherDuesDetail";
import type {
  DuesDetail,
  DuesFilters,
  DuesPeriod,
  DuesTeacher,
} from "./TeacherDuesTypes";

type Props = {
  periods: DuesPeriod[];
  period: DuesPeriod | null;
  teacherOptions: Record<string, string>;
  teachers: DuesTeacher[];
  detail: DuesDetail | null;
  filters: DuesFilters;
  timezone: string;
  currency: string;
  canPropose: boolean;
  adjustmentTypes: string[];
  limitExceeded: boolean;
  pagination: {
    current: number;
    last: number;
    total: number;
    previousUrl: string | null;
    nextUrl: string | null;
  } | null;
};
export default function TeacherDues({
  periods,
  period,
  teacherOptions,
  teachers,
  detail,
  filters,
  timezone,
  currency,
  canPropose,
  adjustmentTypes,
  limitExceeded,
  pagination,
}: Props) {
  const t = useI18n();
  const form = useForm({
    period: filters.period ?? "",
    staff_profile_id: filters.staff_profile_id ?? "",
    track: filters.track ?? "all",
    search: filters.search ?? "",
  });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.get("/manage/teacher-dues", { preserveScroll: true });
  }
  const periodName = period
    ? `${period.year}/${String(period.month).padStart(2, "0")}`
    : t("console_dues.no_period");
  return (
    <ConsoleLayout
      section="after"
      title={t("console_dues.title")}
      description={t("console_dues.description")}
      actions={
        <button
          className="console-button print:hidden"
          onClick={() => window.print()}
        >
          {t("console_dues.print_summary")}
        </button>
      }
    >
      <div className="teacher-dues-view">
        <div className="dues-context">
          <div>
            <b>
              <bdi>{periodName}</bdi>
            </b>
            {period && (
              <span
                className={`console-status ${period.frozen ? "success" : "warning"}`}
              >
                {t(`console_dues.period_status.${period.status}`)}
              </span>
            )}
          </div>
          <span>
            {t("console_dues.times_in")} · <bdi>{timezone}</bdi>
          </span>
        </div>
        <section className="console-panel">
          <form
            onSubmit={submit}
            className="console-filters console-filter-bar print:hidden"
          >
            <div className="console-field">
              <label htmlFor="dues-period">{t("console_dues.period")}</label>
              <select
                id="dues-period"
                value={form.data.period}
                disabled={!periods.length}
                onChange={(e) => form.setData("period", e.target.value)}
              >
                {!periods.length && (
                  <option value="">{t("console_dues.no_period")}</option>
                )}
                {periods.map((item) => (
                  <option key={item.id} value={item.id}>
                    {`${item.year}/${String(item.month).padStart(2, "0")}`} ·{" "}
                    {t(`console_dues.period_status.${item.status}`)}
                  </option>
                ))}
              </select>
            </div>
            <div className="console-field">
              <label htmlFor="dues-teacher">{t("console_dues.teacher")}</label>
              <select
                id="dues-teacher"
                value={form.data.staff_profile_id}
                onChange={(e) =>
                  form.setData("staff_profile_id", e.target.value)
                }
              >
                <option value="">{t("console_dues.all_teachers")}</option>
                {Object.entries(teacherOptions).map(([id, name]) => (
                  <option value={id} key={id}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
            <div className="console-field">
              <label htmlFor="dues-track">{t("console_dues.track")}</label>
              <select
                id="dues-track"
                value={form.data.track}
                onChange={(e) => form.setData("track", e.target.value)}
              >
                {["all", "individual", "group"].map((key) => (
                  <option key={key} value={key}>
                    {t(`console_dues.tracks.${key}`)}
                  </option>
                ))}
              </select>
            </div>
            <div className="console-field console-filter-search">
              <label htmlFor="dues-search">{t("console_dues.search")}</label>
              <input
                id="dues-search"
                value={form.data.search}
                onChange={(e) => form.setData("search", e.target.value)}
              />
            </div>
            <button
              className="console-button primary"
              disabled={form.processing}
            >
              {t(
                form.processing ? "console_dues.loading" : "console_dues.apply",
              )}
            </button>
            {Object.keys(form.errors).length > 0 && (
              <div className="console-feedback is-error" role="alert">
                {Object.values(form.errors).map((error) => (
                  <p key={error}>{error}</p>
                ))}
              </div>
            )}
          </form>
          <div className="console-panel-head">
            <div>
              <h2>{t("console_dues.panel_title")}</h2>
              <p>{t("console_dues.panel_description")}</p>
            </div>
          </div>
          {limitExceeded && (
            <p className="console-feedback" role="status">
              {t("console_dues.limited")}
            </p>
          )}
          {period ? (
            <>
              <div className="toolbar">
                <span className="toolbar-info">
                  {t("console_dues.track_note")}
                </span>
              </div>
              <div className="console-table-wrap">
                <table className="console-table dues-table">
                  <thead>
                    <tr>
                      {[
                        "teacher",
                        "delivered",
                        "pending",
                        "minutes",
                        "session_dues",
                        "bonuses",
                        "adjustments",
                        "paid",
                        "remaining",
                        "statement",
                      ].map((key) => (
                        <th scope="col" key={key}>
                          {t(`console_dues.${key}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {teachers.map((teacher) => (
                      <tr key={teacher.id}>
                        <td>
                          <Link className="cell-title" href={teacher.detailUrl}>
                            {teacher.name}
                          </Link>
                          {teacher.counts && (
                            <small>
                              {formatNumber(teacher.counts.approved)}{" "}
                              {t("console_dues.approved")} ·{" "}
                              {formatNumber(teacher.counts.cancelled)}{" "}
                              {t("console_dues.cancelled")}
                            </small>
                          )}
                        </td>
                        <td>
                          {teacher.counts
                            ? formatNumber(teacher.counts.delivered)
                            : "—"}
                        </td>
                        <td>
                          {teacher.counts ? (
                            <span
                              className={`console-status ${teacher.counts.pending ? "warning" : ""}`}
                            >
                              {formatNumber(teacher.counts.pending)}
                            </span>
                          ) : (
                            "—"
                          )}
                        </td>
                        <td>
                          {teacher.counts
                            ? formatNumber(teacher.counts.minutes)
                            : "—"}
                        </td>
                        {[
                          "entryNet",
                          "bonus",
                          "adjustments",
                          "paid",
                          "remaining",
                        ].map((key) => (
                          <td key={key}>
                            <DuesMoney totals={teacher.totals} field={key} />
                          </td>
                        ))}
                        <td>
                          <Link
                            className="inline-link"
                            href={teacher.detailUrl}
                          >
                            {t("console_dues.open_statement")}{" "}
                            <span aria-hidden="true">←</span>
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {!teachers.length && (
                <div className="console-empty">
                  <h2>{t("console_dues.no_teachers")}</h2>
                  <p>{t("console_dues.no_teachers_help")}</p>
                </div>
              )}
              {pagination && (
                <div className="panel-foot">
                  <span>
                    {t("console_dues.teachers_count")}:{" "}
                    <bdi>{formatNumber(pagination.total)}</bdi>
                  </span>
                  <div className="actions print:hidden">
                    {pagination.previousUrl && (
                      <Link
                        href={pagination.previousUrl}
                        className="console-button"
                      >
                        {t("console_dues.previous")}
                      </Link>
                    )}
                    <bdi>
                      {pagination.current}/{pagination.last}
                    </bdi>
                    {pagination.nextUrl && (
                      <Link
                        href={pagination.nextUrl}
                        className="console-button"
                      >
                        {t("console_dues.next")}
                      </Link>
                    )}
                  </div>
                </div>
              )}
            </>
          ) : (
            <div className="console-empty">
              <h2>{t("console_dues.no_period")}</h2>
              <p>{t("console_dues.no_period_help")}</p>
            </div>
          )}
        </section>
        <p className="detail-note operation-field">
          {t("console_dues.policy_note")}
        </p>
      </div>
      {detail && period && (
        <TeacherDuesDetail
          key={`${detail.id}-${period.id}`}
          detail={detail}
          period={period}
          periods={periods}
          timezone={timezone}
          currency={currency}
          canPropose={canPropose && detail.canPropose}
          types={adjustmentTypes}
          limitExceeded={detail.limitExceeded}
        />
      )}
      <style>{`@media print { .teacher-dues-sheet[open] { position: static!important; width:100%!important; max-width:none!important; height:auto!important; max-height:none!important; overflow:visible!important; padding:0!important; border:0!important; } body:has(.teacher-dues-sheet[open]) .teacher-dues-view { display:none!important; } .teacher-dues-sheet::backdrop { display:none; } .teacher-dues-sheet .console-table-wrap { overflow:visible!important; } .teacher-dues-sheet .teacher-sessions { min-width:0!important; } .teacher-dues-sheet tr { break-inside:avoid; } }`}</style>
    </ConsoleLayout>
  );
}
