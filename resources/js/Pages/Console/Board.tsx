import { Link } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import { formatDate, formatNumber } from "@/lib/console-format";
import "../../../css/console-board.css";

type Teacher = { id: string; name: string; total: number };
type Props = {
  today: { count: number };
  whatsappEnabled: boolean;
  backlog: {
    total: number;
    teachers: Teacher[];
    started: number;
    neverStarted: number;
    olderThanAWeek: number;
    oldest: string | null;
  };
  awaiting: { total: number; missingReport: number; oldest: string | null };
  ledger: {
    entries: number;
    teachers: {
      key: string;
      name: string;
      amount: string;
      currency: string;
    }[];
    sessionsWithoutEntry: number;
    period: { label: string; status: string; neverCalculated: boolean };
  } | null;
  canReview: boolean;
  reviewUrl: string;
  duesUrl: string;
  whatsappUrl: string;
  liveUrl: string;
  timezone: string;
};

export default function Board({
  today,
  whatsappEnabled,
  backlog,
  awaiting,
  ledger,
  canReview,
  reviewUrl,
  duesUrl,
  whatsappUrl,
  liveUrl,
  timezone,
}: Props) {
  const t = useI18n();
  const quiet = backlog.total === 0 && awaiting.total === 0 && whatsappEnabled;

  return (
    <ConsoleLayout
      title={t("console_board.title")}
      description={t("console_board.description")}
      section="during"
    >
      <p className="board-hero">
        {t("console_board.today_prefix")}{" "}
        <bdi>{formatNumber(today.count)}</bdi>{" "}
        {t("console_board.today_suffix")}{" "}
        <Link className="inline-link" href={liveUrl}>
          {t("console_board.open_live")}
        </Link>
        {quiet && <span className="board-clear"> · {t("console_board.all_clear")}</span>}
      </p>

      {!whatsappEnabled && (
        <article className="board-card is-alert">
          <h2>{t("console_board.whatsapp_off_title")}</h2>
          <p>{t("console_board.whatsapp_off_body")}</p>
          <Link className="console-button" href={whatsappUrl}>
            {t("console_board.whatsapp_open")}
          </Link>
        </article>
      )}

      {backlog.total > 0 && (
        <article className="board-card is-alert">
          <h2>
            <bdi>{formatNumber(backlog.total)}</bdi>{" "}
            {t("console_board.backlog_title")}
          </h2>
          <p className="board-rule">{t("console_board.backlog_rule")}</p>

          <div className="board-split">
            <span className="board-chip is-work">
              <bdi>{formatNumber(backlog.started)}</bdi>{" "}
              {t("console_board.backlog_started")}
            </span>
            <span className="board-chip is-ask">
              <bdi>{formatNumber(backlog.neverStarted)}</bdi>{" "}
              {t("console_board.backlog_never")}
            </span>
          </div>

          <ul className="board-people">
            {backlog.teachers.map((teacher) => (
              <li key={teacher.id}>
                <b>{teacher.name}</b>
                <bdi>{formatNumber(teacher.total)}</bdi>
              </li>
            ))}
          </ul>

          <p className="board-meta">
            {backlog.oldest && (
              <>
                {t("console_board.backlog_oldest")}{" "}
                {formatDate(backlog.oldest, timezone)}
              </>
            )}
            {backlog.olderThanAWeek > 0 && (
              <>
                {" · "}
                <bdi>{formatNumber(backlog.olderThanAWeek)}</bdi>{" "}
                {t("console_board.backlog_old_count")}
              </>
            )}
          </p>
          {!whatsappEnabled && (
            <p className="board-meta is-warn">
              {t("console_board.backlog_channel_off")}
            </p>
          )}
          {canReview && (
            <Link className="console-button" href={reviewUrl}>
              {t("console_board.backlog_action")}
            </Link>
          )}
        </article>
      )}

      {awaiting.total > 0 && (
        <article className="board-card is-warn">
          <h2>
            <bdi>{formatNumber(awaiting.total)}</bdi>{" "}
            {t("console_board.awaiting_title")}
          </h2>
          <p>
            <bdi>{formatNumber(awaiting.missingReport)}</bdi>{" "}
            {t("console_board.awaiting_missing")}
          </p>
          <p className="board-meta">{t("console_board.awaiting_note")}</p>
          {canReview && (
            <Link className="console-button" href={reviewUrl}>
              {t("console_board.awaiting_action")}
            </Link>
          )}
        </article>
      )}

      {ledger && (
        <article
          className={`board-card ${ledger.sessionsWithoutEntry > 0 ? "is-alert" : ""}`}
        >
          <h2>{t("console_board.ledger_title")}</h2>
          <p className="board-figure">
            {t("console_board.ledger_recorded")}{" "}
            <bdi>{formatNumber(ledger.entries)}</bdi>
          </p>
          <p className="board-figure is-gap">
            {t("console_board.ledger_gap_prefix")}{" "}
            <bdi>{formatNumber(ledger.sessionsWithoutEntry)}</bdi>{" "}
            {t("console_board.ledger_gap_suffix")}
          </p>
          <p className="board-rule">{t("console_board.ledger_rule")}</p>

          <ul className="board-people">
            {ledger.teachers.map((row) => (
              <li key={row.key}>
                <b>{row.name}</b>
                <bdi>
                  {row.amount} {row.currency}
                </bdi>
              </li>
            ))}
          </ul>

          <p className="board-meta">
            {t("console_board.ledger_period")}{" "}
            <bdi>{ledger.period.label}</bdi>
            {ledger.period.neverCalculated && (
              <> · {t("console_board.ledger_never_paid")}</>
            )}
          </p>
          <Link className="console-button" href={duesUrl}>
            {t("console_board.ledger_action")}
          </Link>
        </article>
      )}

      <p className="board-footnote">{t("console_board.no_revenue")}</p>
    </ConsoleLayout>
  );
}
