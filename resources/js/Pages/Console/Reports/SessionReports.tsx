import { Head, Link, useForm } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { Metric, Panel } from "@/Components/Console/DashboardParts";
import ReportDateRangeFilter, {
  type ReportDateRangeValue,
} from "@/Components/Console/ReportDateRangeFilter";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber } from "@/lib/console-format";

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
type ProgramGroup = {
  programId: string;
  programName: Record<string, string>;
  studentCount: number;
  reportCount: number;
  students: StudentGroup[];
  programUrl: string;
};
type Props = {
  view: "today" | "month" | "custom";
  filters: {
    from: string;
    to: string;
    month: string;
    program_id: string | null;
  };
  timezone: string;
  programs: { id: string; name: Record<string, string>; code: string }[];
  groups: ProgramGroup[];
  totals: { programs: number; students: number; reports: number };
  settingsUrl: string | null;
};

function programLabel(name: Record<string, string>, locale: string): string {
  return name[locale] || name.ar || Object.values(name)[0] || "";
}

export default function SessionReports({
  view,
  filters,
  timezone,
  programs,
  groups,
  totals,
  settingsUrl,
}: Props) {
  const t = useI18n();
  const locale = "ar";
  const form = useForm<ReportDateRangeValue & { program_id: string }>({
    view,
    from: filters.from,
    to: filters.to,
    month: filters.month,
    program_id: filters.program_id ?? "",
  });

  function submit() {
    form.get("/manage/reports/session-reports", {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const hasError = Object.keys(form.errors).length > 0;

  return (
    <ConsoleLayout
      section="records"
      title={t("console_reports.title")}
      description={t("console_reports.description")}
      actions={
        settingsUrl ? (
          <Link href={settingsUrl} className="console-button">
            {t("console_reports.settings.title")}
          </Link>
        ) : undefined
      }
    >
      <Head title={t("console_reports.title")} />

      <ReportDateRangeFilter
        value={{
          view: form.data.view,
          from: form.data.from,
          to: form.data.to,
          month: form.data.month,
        }}
        onChange={(next) => {
          form.setData("view", next.view);
          form.setData("from", next.from);
          form.setData("to", next.to);
          form.setData("month", next.month);
        }}
        onSubmit={submit}
        processing={form.processing}
        extra={
          <div className="field">
            <label htmlFor="report-program">
              {t("console_reports.filters.program")}
            </label>
            <select
              id="report-program"
              className="console-control"
              value={form.data.program_id}
              onChange={(event) => {
                form.setData("program_id", event.target.value);
              }}
            >
              <option value="">
                {t("console_reports.filters.all_programs")}
              </option>
              {programs.map((program) => (
                <option key={program.id} value={program.id}>
                  {programLabel(program.name, locale)} · {program.code}
                </option>
              ))}
            </select>
          </div>
        }
      />

      {hasError && (
        <div className="console-feedback is-error" role="alert">
          {Object.values(form.errors).join(" · ")}
        </div>
      )}

      <div className="console-metrics">
        <Metric
          label={t("console_reports.totals.programs")}
          value={totals.programs}
          icon="today"
        />
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
      ) : groups.length === 0 ? (
        <div className="console-feedback" role="status">
          {t("console_reports.empty")}
        </div>
      ) : (
        groups.map((group) => (
          <Panel
            key={group.programId}
            title={programLabel(group.programName, locale)}
            subtitle={
              formatNumber(group.studentCount, locale) +
              " " +
              t("console_reports.totals.students") +
              " · " +
              formatNumber(group.reportCount, locale) +
              " " +
              t("console_reports.totals.reports")
            }
            action={
              <Link href={group.programUrl} className="console-link">
                {t("console_reports.view_program")} ←
              </Link>
            }
          >
            {group.students.map((student) => (
              <div key={student.studentId} className="pp-progress-item">
                <h4>{student.studentName}</h4>
                {student.entries.map((entry) => (
                  <div key={entry.sessionId}>
                    <p className="pp-small-text">
                      {formatDate(entry.submittedAt, timezone, locale)}
                    </p>
                    {entry.topicsCovered && (
                      <p className="pp-prose">
                        <strong>
                          {t("console_reports.columns.topics")}:{" "}
                        </strong>
                        {entry.topicsCovered}
                      </p>
                    )}
                    {entry.homeworkAssigned && (
                      <p className="pp-prose">
                        <strong>
                          {t("console_reports.columns.homework")}:{" "}
                        </strong>
                        {entry.homeworkAssigned}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            ))}
          </Panel>
        ))
      )}
    </ConsoleLayout>
  );
}
