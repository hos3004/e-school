import { Link } from "@inertiajs/react";
import { useState } from "react";
import LearningLayout from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import { date, Empty } from "./shared";
type Entry = {
  id: string;
  sessionId: string | null;
  sessionUrl: string | null;
  entryType: string;
  outcomeKey: string;
  amountMinorUnits: number;
  status: string;
  recordedAt: string | null;
};
type Adjustment = {
  id: string;
  type: string;
  amountMinorUnits: number;
  reason: string;
  approvedAt: string | null;
};
type Period = {
  id: string;
  year: number;
  month: number;
  status: string;
  currency: string;
  earningsMinorUnits: number;
  deductionsMinorUnits: number;
  adjustmentsMinorUnits: number;
  netMinorUnits: number;
  sessionsCount: number;
  entries: Entry[];
  adjustments: Adjustment[];
};
function money(value: number) {
  return `${value < 0 ? "-" : ""}${Math.floor(Math.abs(value) / 100).toLocaleString("en-US")}.${String(Math.abs(value) % 100).padStart(2, "0")}`;
}
export default function Earnings({
  periods,
  timezone,
}: {
  periods: Period[];
  timezone: string;
}) {
  const t = useI18n();
  const [selected, setSelected] = useState(periods[0]?.id ?? "");
  const period = periods.find((item) => item.id === selected);
  return (
    <LearningLayout kind="teacher" title={t("learning.earnings")}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t("learning.workspace.professional_kicker")}
          </div>
          <h1>{t("learning.earnings")}</h1>
          <p>{t("learning.services.earnings_hint")}</p>
        </div>
        <Link className="lp-text-action" href="/learn/teacher#progress">
          {t("learning.back_portal")}
        </Link>
      </div>
      {period ? (
        <>
          <label className="lp-field lp-compact-filter console-filter-single">
            <span>{t("learning.services.period")}</span>
            <select
              value={selected}
              onChange={(e) => setSelected(e.target.value)}
            >
              {periods.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.year}-{String(item.month).padStart(2, "0")} ·{" "}
                  {item.currency}
                </option>
              ))}
            </select>
          </label>
          <div className="lp-earnings-summary">
            {[
              ["earned", period.earningsMinorUnits],
              ["deducted", period.deductionsMinorUnits],
              ["adjustments", period.adjustmentsMinorUnits],
              ["net", period.netMinorUnits],
            ].map(([key, value]) => (
              <div key={String(key)}>
                <small>{t(`teacher.earnings.${key}`)}</small>
                <b dir="ltr">{money(Number(value))}</b>
                <span>{period.currency}</span>
              </div>
            ))}
          </div>
          <section className="lp-section">
            <div className="lp-section-head">
              <h2>{t("learning.services.lesson_entries")}</h2>
              <span className="lp-tag">
                {period.sessionsCount} {t("learning.workspace.ledger_sessions")}
              </span>
            </div>
            <div className="lp-notices">
              {period.entries.map((entry) => (
                <article className="lp-resource-row" key={entry.id}>
                  <span>
                    <b>{t(`payroll_outcomes.${entry.outcomeKey}`)}</b>
                    <small>
                      {entry.recordedAt
                        ? date(entry.recordedAt, "ar", timezone)
                        : ""}{" "}
                      · {t(`statuses.${entry.status}`)}
                    </small>
                    {entry.sessionUrl && (
                      <Link className="lp-text-action" href={entry.sessionUrl}>
                        {t("learning.lesson_details")}
                      </Link>
                    )}
                    <small>
                      {t("learning.services.reference")} <bdi>{entry.id}</bdi>
                    </small>
                  </span>
                  <b dir="ltr">
                    {money(entry.amountMinorUnits)} {period.currency}
                  </b>
                </article>
              ))}
            </div>
          </section>
          <section className="lp-section">
            <h2>{t("learning.services.adjustments")}</h2>
            {period.adjustments.length ? (
              <div className="lp-notices">
                {period.adjustments.map((item) => (
                  <article className="lp-resource-row" key={item.id}>
                    <span>
                      <b>{t(`payroll_adjustment_types.${item.type}`)}</b>
                      <small>{item.reason}</small>
                      {item.approvedAt && (
                        <small>{date(item.approvedAt, "ar", timezone)}</small>
                      )}
                    </span>
                    <b dir="ltr">
                      {money(item.amountMinorUnits)} {period.currency}
                    </b>
                  </article>
                ))}
              </div>
            ) : (
              <Empty>{t("learning.services.no_adjustments")}</Empty>
            )}
          </section>
        </>
      ) : (
        <Empty>{t("learning.workspace.no_earnings")}</Empty>
      )}
    </LearningLayout>
  );
}
