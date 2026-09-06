import { Link, router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber, formatTime } from "@/lib/console-format";
import { DuesDecision, ProposeDues } from "./TeacherDuesActions";
import type { DuesDetail, DuesPeriod, DuesTotals } from "./TeacherDuesTypes";

export function DuesMoney({
  totals,
  field,
}: {
  totals: DuesTotals[];
  field: string;
}) {
  return totals.length ? (
    <>
      {totals.map((total) => (
        <span className="block" key={total.currency}>
          <bdi>
            {total.amounts[field]} {total.currency}
          </bdi>
        </span>
      ))}
    </>
  ) : (
    <span>—</span>
  );
}
export default function TeacherDuesDetail({
  detail,
  period,
  periods,
  timezone,
  currency,
  canPropose,
  types,
  limitExceeded,
}: {
  detail: DuesDetail;
  period: DuesPeriod;
  periods: DuesPeriod[];
  timezone: string;
  currency: string;
  canPropose: boolean;
  types: string[];
  limitExceeded: boolean;
}) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const [filter, setFilter] = useState("all");
  useEffect(() => {
    const element = dialog.current;
    if (!element) return;
    const previousFocus = document.activeElement;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    element.showModal();
    return () => {
      element.close();
      document.body.style.overflow = overflow;
      if (previousFocus instanceof HTMLElement && previousFocus.isConnected)
        previousFocus.focus();
    };
  }, []);
  const label = (key: string, fallback: string) => {
    const value = t(key);
    return value === key ? t(fallback) : value;
  };
  const close = () => router.visit(detail.closeUrl, { preserveScroll: true });
  const lessons = detail.lessons.filter(
    (lesson) =>
      filter === "all" ||
      (filter === "approved" ? lesson.approved : lesson.awaitingReview),
  );
  const lessonsById = new Map(
    detail.lessons.map((lesson) => [lesson.id, lesson]),
  );
  const periodName = `${period.year}/${String(period.month).padStart(2, "0")}`;
  return (
    <dialog
      ref={dialog}
      className="detail-sheet dues-sheet teacher-dues-sheet"
      aria-labelledby="teacher-dues-title"
      onCancel={(event) => {
        event.preventDefault();
        close();
      }}
    >
      <div className="detail-top">
        <div>
          <h2 id="teacher-dues-title">{detail.name}</h2>
          <p className="cell-sub">
            {t("console_dues.statement")} · <bdi>{periodName}</bdi> ·{" "}
            {t(`console_dues.period_status.${period.status}`)}
          </p>
        </div>
        <button
          type="button"
          className="console-button print:hidden"
          onClick={close}
        >
          {t("console_dues.close")}
        </button>
      </div>
      <div className="actions mb-5 print:hidden">
        {detail.profileUrl && (
          <Link className="inline-link" href={detail.profileUrl}>
            {t("console_dues.full_profile")}
          </Link>
        )}
        <button className="console-button" onClick={() => window.print()}>
          {t("console_dues.print_statement")}
        </button>
      </div>
      <div className="dues-total">
        <span>{t("console_dues.remaining")}</span>
        <strong>
          <DuesMoney totals={detail.totals} field="remaining" />
        </strong>
        <span>{t("console_dues.net_formula")}</span>
      </div>
      {detail.counts && (
        <div className="filters operation-field">
          {["delivered", "approved", "pending", "cancelled", "minutes"].map(
            (key) => (
              <span className="console-status" key={key}>
                {t(`console_dues.${key}`)} ·{" "}
                <bdi>
                  {formatNumber(
                    detail.counts?.[key as keyof typeof detail.counts] ?? 0,
                  )}
                </bdi>
              </span>
            ),
          )}
        </div>
      )}
      {limitExceeded && (
        <p className="console-feedback" role="status">
          {t("console_dues.limited")}
        </p>
      )}
      <section className="detail-block operation-field">
        <h3>{t("console_dues.lesson_details")}</h3>
        <div className="filters print:hidden">
          {["all", "approved", "pending"].map((key) => (
            <button
              type="button"
              key={key}
              className={`pill ${filter === key ? "current" : ""}`}
              aria-pressed={filter === key}
              onClick={() => setFilter(key)}
            >
              {t(`console_dues.lesson_filters.${key}`)}
            </button>
          ))}
        </div>
        <div className="console-table-wrap">
          <table className="console-table teacher-sessions">
            <thead>
              <tr>
                {["lesson", "duration", "status", "snapshot", "counted"].map(
                  (key) => (
                    <th key={key} scope="col">
                      {t(`console_dues.${key}`)}
                    </th>
                  ),
                )}
              </tr>
            </thead>
            <tbody>
              {lessons.map((lesson) => (
                <tr key={lesson.id}>
                  <td>
                    <strong>
                      {formatDate(lesson.startsAt, timezone)} ·{" "}
                      <bdi>{formatTime(lesson.startsAt, timezone)}</bdi>
                    </strong>
                    <small>{lesson.title}</small>
                    <small>
                      {lesson.study} · {lesson.course}
                    </small>
                    {!lesson.isActualTeacher && (
                      <small>
                        {t("console_dues.actual_teacher")}:{" "}
                        {lesson.actualTeacher}
                      </small>
                    )}
                  </td>
                  <td>
                    <bdi>{formatNumber(lesson.durationMinutes)}</bdi>{" "}
                    {t("console_dues.minute")}
                    <small>
                      {t(
                        lesson.actualDuration
                          ? "console_dues.actual_duration"
                          : "console_dues.scheduled_duration",
                      )}
                    </small>
                  </td>
                  <td>
                    <span
                      className={`console-status ${lesson.approved ? "success" : lesson.awaitingReview ? "warning" : ""}`}
                    >
                      {lesson.statusLabel}
                    </span>
                  </td>
                  <td>
                    {lesson.entries.length
                      ? lesson.entries.map((entry) => (
                          <small key={entry.id}>
                            <bdi>
                              {entry.snapshotAmount ?? "—"} {entry.currency}
                            </bdi>
                          </small>
                        ))
                      : t("console_dues.no_snapshot")}
                  </td>
                  <td>
                    {lesson.entries.length
                      ? lesson.entries.map((entry) => (
                          <div key={entry.id}>
                            <bdi>
                              {entry.amount} {entry.currency}
                            </bdi>
                            <small>
                              {t(`console_dues.entry_status.${entry.status}`)}
                            </small>
                          </div>
                        ))
                      : t("console_dues.no_entry")}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!lessons.length && (
          <p className="console-empty">{t("console_dues.no_lessons")}</p>
        )}
        <p className="detail-note operation-field">
          {t("console_dues.lesson_note")}
        </p>
      </section>
      <section className="detail-block">
        <h3>{t("console_dues.ledger")}</h3>
        <p>{t("console_dues.ledger_help")}</p>
        {detail.entries.length ? (
          <div className="console-table-wrap">
            <table className="console-table">
              <thead>
                <tr>
                  {[
                    "recorded_at",
                    "entry_kind",
                    "snapshot",
                    "amount",
                    "status",
                  ].map((key) => (
                    <th key={key} scope="col">
                      {t(`console_dues.${key}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {detail.entries.map((entry) => (
                  <tr key={entry.id}>
                    <td>
                      {entry.recordedAt
                        ? formatDate(entry.recordedAt, timezone)
                        : "—"}
                      {entry.sessionId && lessonsById.has(entry.sessionId) && (
                        <>
                          <strong>
                            {lessonsById.get(entry.sessionId)?.title}
                          </strong>
                          <small>
                            {formatDate(
                              lessonsById.get(entry.sessionId)!.startsAt,
                              timezone,
                            )}{" "}
                            ·{" "}
                            <bdi>
                              {formatTime(
                                lessonsById.get(entry.sessionId)!.startsAt,
                                timezone,
                              )}
                            </bdi>
                          </small>
                        </>
                      )}
                      <small>
                        {t("console_dues.entry_reference")}:{" "}
                        <bdi>{entry.id}</bdi>
                      </small>
                      {entry.sessionId && !lessonsById.has(entry.sessionId) && (
                        <small>
                          {t("console_dues.session_reference")}:{" "}
                          <bdi>{entry.sessionId}</bdi>
                        </small>
                      )}
                    </td>
                    <td>
                      {label(
                        `console_dues.entry_types.${entry.entryType}`,
                        "console_dues.other_entry",
                      )}
                      <small>
                        {label(
                          `console_dues.outcomes.${entry.outcomeKey}`,
                          "console_dues.other_outcome",
                        )}
                      </small>
                    </td>
                    <td>
                      <bdi>
                        {entry.snapshotAmount ?? "—"} {entry.currency}
                      </bdi>
                    </td>
                    <td>
                      <bdi>
                        {entry.amount} {entry.currency}
                      </bdi>
                    </td>
                    <td>{t(`console_dues.entry_status.${entry.status}`)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="console-empty">{t("console_dues.no_entries")}</p>
        )}
      </section>
      <section className="detail-block">
        <h3>{t("console_dues.adjustments_title")}</h3>
        {detail.adjustments.length ? (
          detail.adjustments.map((adjustment) => (
            <article className="ledger-row" key={adjustment.id}>
              <div className="min-w-0 flex-1">
                <b>{t(`console_dues.types.${adjustment.type}`)}</b>{" "}
                <span
                  className={`console-status ${adjustment.status === "approved" ? "success" : adjustment.status === "pending" ? "warning" : ""}`}
                >
                  {t(`console_dues.adjustment_status.${adjustment.status}`)}
                </span>
                <p className="cell-sub">{adjustment.reason}</p>
                <p className="cell-sub">
                  {adjustment.proposedByName} ·{" "}
                  {formatDate(adjustment.proposedAt, timezone)}
                </p>
                {adjustment.approvedByName && (
                  <p className="cell-sub">
                    {t("console_dues.approved_by")}: {adjustment.approvedByName}
                  </p>
                )}
                {adjustment.referencePeriodId && (
                  <p className="cell-sub">
                    {t("console_dues.reference_period")}:{" "}
                    <bdi>
                      {periods.find(
                        (item) => item.id === adjustment.referencePeriodId,
                      )?.startsOn ?? adjustment.referencePeriodId}
                    </bdi>
                  </p>
                )}
                {adjustment.rejectionReason && (
                  <p className="cell-sub">{adjustment.rejectionReason}</p>
                )}
                <DuesDecision adjustment={adjustment} />
              </div>
              <strong>
                <bdi>
                  {adjustment.amount} {adjustment.currency}
                </bdi>
              </strong>
            </article>
          ))
        ) : (
          <p className="console-empty">{t("console_dues.no_adjustments")}</p>
        )}
      </section>
      {!detail.canPropose && (
        <p className="detail-note">{t("console_dues.archived_read_only")}</p>
      )}
      {canPropose && period.canAdjust ? (
        <ProposeDues
          detail={detail}
          periods={periods}
          currency={currency}
          types={types}
        />
      ) : (
        !period.canAdjust && (
          <p className="detail-note">{t("console_dues.closed_help")}</p>
        )
      )}
      <section className="detail-block">
        <h3>{t("console_dues.period_payment")}</h3>
        <p>{t(`console_dues.period_status.${period.status}`)}</p>
        {period.paidAt && (
          <p>
            {t("console_dues.paid_at")}: {formatDate(period.paidAt, timezone)}
          </p>
        )}
        {period.lockedAt && (
          <p>
            {t("console_dues.locked_at")}:{" "}
            {formatDate(period.lockedAt, timezone)}
          </p>
        )}
        <div className="filters operation-field">
          <span>
            {t("console_dues.deferred")}:{" "}
            <DuesMoney totals={detail.totals} field="deferred" />
          </span>
          <span>
            {t("console_dues.pending_amounts")}:{" "}
            <DuesMoney totals={detail.totals} field="pendingAdjustments" />
          </span>
        </div>
        <p className="detail-note operation-field">
          {t("console_dues.payment_note")}
        </p>
      </section>
    </dialog>
  );
}
