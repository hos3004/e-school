import { Head, Link, useForm } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { Metric } from "@/Components/Console/DashboardParts";
import ReportDateRangeFilter, {
  type ReportDateRangeValue,
} from "@/Components/Console/ReportDateRangeFilter";
import { useI18n } from "@/lib/i18n";
import { formatDate } from "@/lib/console-format";

type ReportEntry = {
  sessionId: string;
  submittedAt: string;
  topicsCovered: string | null;
  homeworkAssigned: string | null;
  generalNotes: string | null;
  participation: number | null;
  performance: number | null;
  commitment: number | null;
  strengths: string | null;
  weaknesses: string | null;
  note: string | null;
};
type StudentGroup = {
  studentId: string;
  studentName: string;
  entries: ReportEntry[];
};
type Props = {
  program: { id: string; name: Record<string, string>; code: string };
  filters: { from: string; to: string };
  timezone: string;
  students: StudentGroup[];
  totals: { students: number; reports: number };
  backUrl: string;
};

const MAX_SCORE = 5;

function programLabel(name: Record<string, string>, locale: string): string {
  return name[locale] || name.ar || Object.values(name)[0] || "";
}

export default function ProgramProfile({
  program,
  filters,
  timezone,
  students,
  totals,
  backUrl,
}: Props) {
  const t = useI18n();
  const locale = "ar";
  const monthGuess = filters.from.slice(0, 7);
  const form = useForm<ReportDateRangeValue>({
    view: filters.from.slice(8) === "01" ? "month" : "custom",
    from: filters.from,
    to: filters.to,
    month: monthGuess,
  });

  function submit() {
    form.get(window.location.pathname, {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const label = programLabel(program.name, locale);

  return (
    <ConsoleLayout
      section="records"
      title={label}
      description={t("console_reports.program_profile.title")}
      actions={
        <Link href={backUrl} className="console-button">
          ← {t("console_reports.program_profile.back")}
        </Link>
      }
    >
      <Head title={label} />

      <ReportDateRangeFilter
        value={form.data}
        onChange={(next) => {
          form.setData("view", next.view);
          form.setData("from", next.from);
          form.setData("to", next.to);
          form.setData("month", next.month);
        }}
        onSubmit={submit}
        processing={form.processing}
      />

      {Object.keys(form.errors).length > 0 && (
        <div className="console-feedback is-error" role="alert">
          {Object.values(form.errors).join(" · ")}
        </div>
      )}

      <div className="console-metrics">
        <Metric
          label={t("console_reports.totals.students")}
          value={totals.students}
          icon="check"
        />
        <Metric
          label={t("console_reports.totals.reports")}
          value={totals.reports}
          icon="followup"
        />
      </div>

      {form.processing ? (
        <div className="console-feedback" role="status">
          {t("console_reports.loading")}
        </div>
      ) : students.length === 0 ? (
        <div className="console-feedback" role="status">
          {t("console_reports.program_profile.no_students")}
        </div>
      ) : (
        students.map((student) => (
          <section key={student.studentId} className="console-panel">
            <div className="console-panel-header">
              <h3>{student.studentName}</h3>
            </div>
            {student.entries.map((entry) => (
              <div key={entry.sessionId} className="pp-progress-item">
                <p className="pp-small-text">
                  {formatDate(entry.submittedAt, timezone, locale)}
                </p>
                {(
                  ["participation", "performance", "commitment"] as const
                ).map(
                  (key) =>
                    entry[key] !== null && (
                      <div key={key} className="pp-learning-score">
                        <span>{t("console_reports.columns." + key)}</span>
                        <bdi>
                          {entry[key]} / {MAX_SCORE}
                        </bdi>
                        <progress
                          value={entry[key] ?? 0}
                          max={MAX_SCORE}
                          aria-label={t("console_reports.columns." + key)}
                        />
                      </div>
                    ),
                )}
                {entry.topicsCovered && (
                  <p className="pp-prose">
                    <strong>{t("console_reports.columns.topics")}: </strong>
                    {entry.topicsCovered}
                  </p>
                )}
                {entry.homeworkAssigned && (
                  <p className="pp-prose">
                    <strong>{t("console_reports.columns.homework")}: </strong>
                    {entry.homeworkAssigned}
                  </p>
                )}
                {entry.strengths && (
                  <p className="pp-prose">
                    <strong>{t("console_reports.columns.strengths")}: </strong>
                    {entry.strengths}
                  </p>
                )}
                {entry.weaknesses && (
                  <p className="pp-prose">
                    <strong>
                      {t("console_reports.columns.weaknesses")}:{" "}
                    </strong>
                    {entry.weaknesses}
                  </p>
                )}
                {entry.note && (
                  <p className="pp-prose">
                    <strong>{t("console_reports.columns.note")}: </strong>
                    {entry.note}
                  </p>
                )}
              </div>
            ))}
          </section>
        ))
      )}
    </ConsoleLayout>
  );
}
