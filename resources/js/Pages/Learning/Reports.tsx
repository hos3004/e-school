import { Link } from "@inertiajs/react";
import LearningLayout from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import type { MonthlyReport } from "@/types";
import { date, Empty, percent } from "./shared";
export default function Reports({
  reports,
  timezone,
}: {
  reports: MonthlyReport[];
  timezone: string;
}) {
  const t = useI18n();
  return (
    <LearningLayout kind="student" title={t("learning.progress_reports")}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t("learning.workspace.progress_kicker")}
          </div>
          <h1>{t("learning.progress_reports")}</h1>
        </div>
        <div className="lp-modal-actions">
          <Link className="lp-text-action" href="/learn/student#progress">
            {t("learning.back_portal")}
          </Link>
          <button
            className="lp-btn lp-btn-secondary"
            onClick={() => window.print()}
          >
            {t("learning.services.print_reports")}
          </button>
        </div>
      </div>
      {reports.length ? (
        reports.map((report) => (
          <section className="lp-section lp-report-card" key={report.id}>
            <div className="lp-section-title">
              <h2>{report.title}</h2>
              <span className="lp-tag">{t(`statuses.${report.status}`)}</span>
            </div>
            {report.issuedAt && (
              <p className="lp-help">{date(report.issuedAt, "ar", timezone)}</p>
            )}
            {report.attendanceRate !== null &&
              report.attendanceRate !== undefined && (
                <p>
                  {t("learning.attendance_rate")}{" "}
                  <b dir="ltr">{percent(report.attendanceRate)}</b>
                </p>
              )}
            <p className="lp-preserve-text">{report.summary}</p>
          </section>
        ))
      ) : (
        <Empty>{t("learning.workspace.no_reports")}</Empty>
      )}
    </LearningLayout>
  );
}
