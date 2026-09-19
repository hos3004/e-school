import { Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatNumber } from "@/lib/console-format";
import "../../../css/console-live.css";

type LiveStudent = {
  id: string;
  name: string;
  inside: boolean;
  everJoined: boolean;
  excused: boolean;
};
type LiveRow = {
  id: string;
  state: string;
  status: string;
  statusLabel: string;
  start: string;
  end: string;
  startsAt: string;
  endsAt: string;
  minutes: number;
  teacher: string;
  teacherIn: boolean;
  course: string;
  lesson: string;
  students: LiveStudent[];
  studentsIn: number;
  studentsExpected: number;
  reportMissing: boolean;
  reviewUrl: string;
};
type Props = {
  rows: LiveRow[];
  counts: Record<string, number>;
  timezone: string;
  now: string;
  date: string;
  refreshSeconds: number;
  canReview: boolean;
};

const TONE: Record<string, string> = {
  running_nobody: "is-alert",
  running_no_teacher: "is-alert",
  running_no_student: "is-warn",
  running_ok: "is-ok",
  upcoming: "",
  ended_unresolved: "is-warn",
  ended: "",
};
const ORDER = [
  "running_nobody",
  "running_no_teacher",
  "running_no_student",
  "running_ok",
  "upcoming",
  "ended_unresolved",
  "ended",
];

export default function LiveBoard({
  rows,
  counts,
  now,
  refreshSeconds,
  canReview,
}: Props) {
  const t = useI18n();
  const [filter, setFilter] = useState("all");

  useEffect(() => {
    if (refreshSeconds <= 0) return;
    const timer = window.setInterval(() => {
      router.reload({ only: ["rows", "counts", "now"] });
    }, refreshSeconds * 1000);
    return () => window.clearInterval(timer);
  }, [refreshSeconds]);

  const visible = rows.filter(
    (row) => filter === "all" || row.state === filter,
  );

  return (
    <ConsoleLayout
      title={t("console_live.title")}
      description={t("console_live.description")}
      section="during"
      actions={
        <span className="live-clock">
          {t("console_live.as_of")} <bdi>{now}</bdi>
        </span>
      }
    >
      <div className="live-counts">
        {ORDER.map((key) => (
          <button
            key={key}
            type="button"
            className={`live-count ${TONE[key]}`}
            aria-pressed={filter === key}
            onClick={() => setFilter(filter === key ? "all" : key)}
          >
            <b>{formatNumber(counts[key] ?? 0)}</b>
            <span>{t(`console_live.states.${key}`)}</span>
          </button>
        ))}
      </div>

      {filter !== "all" && (
        <p className="console-feedback" role="status">
          {t("console_live.filtered")}{" "}
          <button
            type="button"
            className="inline-link"
            onClick={() => setFilter("all")}
          >
            {t("console_live.show_all")}
          </button>
        </p>
      )}

      {visible.length ? (
        <div className="live-grid">
          {visible.map((row) => (
            <article key={row.id} className={`live-card state-${row.state}`}>
              <div className="live-card-top">
                <span className="live-time">
                  <bdi>
                    {row.start} – {row.end}
                  </bdi>
                </span>
                <span className="live-state">
                  {t(`console_live.states.${row.state}`)}
                </span>
              </div>

              <div className="live-who">
                <span>
                  <i
                    className={`live-dot ${row.teacherIn ? "is-in" : "is-out"}`}
                    aria-hidden="true"
                  />
                  {row.teacher}
                  {" · "}
                  {t(
                    row.teacherIn
                      ? "console_live.teacher_in"
                      : "console_live.teacher_out",
                  )}
                </span>
                <span>
                  <i
                    className={`live-dot ${row.studentsIn ? "is-in" : "is-out"}`}
                    aria-hidden="true"
                  />
                  {t("console_live.students")}{" "}
                  <bdi>
                    {formatNumber(row.studentsIn)}/
                    {formatNumber(row.studentsExpected)}
                  </bdi>
                  {row.studentsExpected === 1 && row.students[0]
                    ? ` · ${row.students[0].name}`
                    : ""}
                </span>
              </div>

              <p className="live-meta">
                {row.course}
                {row.lesson ? ` · ${row.lesson}` : ""}
                <br />
                <bdi>{formatNumber(row.minutes)}</bdi>{" "}
                {t("console_live.minute")} · {row.statusLabel}
              </p>

              {row.state === "ended_unresolved" && (
                <span className="live-flag">
                  {t("console_live.needs_decision")}
                  {canReview && (
                    <>
                      {" — "}
                      <Link className="inline-link" href={row.reviewUrl}>
                        {t("console_live.go_review")}
                      </Link>
                    </>
                  )}
                </span>
              )}
              {row.state === "ended" && row.reportMissing && (
                <span className="live-flag">
                  {t("console_live.report_missing")}
                </span>
              )}
            </article>
          ))}
        </div>
      ) : (
        <p className="console-empty">{t("console_live.empty")}</p>
      )}
    </ConsoleLayout>
  );
}
