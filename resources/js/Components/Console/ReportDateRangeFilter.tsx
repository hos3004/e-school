import type { FormEvent } from "react";
import { useI18n } from "@/lib/i18n";

export type ReportDateRangeValue = {
  view: "today" | "month" | "custom";
  from: string;
  to: string;
  month: string;
};

type Props = {
  value: ReportDateRangeValue;
  onChange: (value: ReportDateRangeValue) => void;
  onSubmit: () => void;
  processing?: boolean;
  extra?: React.ReactNode;
};

/**
 * منتقي مدى التاريخ المشترك بين لوحة تقارير اليوم/الشهر وملف تعريف
 * البرنامج — اليوم والشهر اختصاران فوق نفس حقلي from/to، لا مسارين
 * منفصلين، كي يبقى مدى التاريخ المخصَّص متاحًا دائمًا.
 */
export default function ReportDateRangeFilter({
  value,
  onChange,
  onSubmit,
  processing,
  extra,
}: Props) {
  const t = useI18n();

  function submit(event: FormEvent) {
    event.preventDefault();
    onSubmit();
  }

  function selectToday() {
    const today = new Date().toISOString().slice(0, 10);
    onChange({ view: "today", from: today, to: today, month: value.month });
  }

  function selectMonth() {
    onChange({ ...value, view: "month" });
  }

  return (
    <form
      onSubmit={submit}
      className="toolbar console-filter-bar"
      aria-label={t("console_reports.title")}
    >
      <div className="filters console-filter-pills">
        <button
          type="button"
          className={
            "console-pill" + (value.view === "today" ? " is-active" : "")
          }
          onClick={selectToday}
        >
          {t("console_reports.views.today")}
        </button>
        <button
          type="button"
          className={
            "console-pill" + (value.view === "month" ? " is-active" : "")
          }
          onClick={selectMonth}
        >
          {t("console_reports.views.month")}
        </button>
        <button
          type="button"
          className={
            "console-pill" + (value.view === "custom" ? " is-active" : "")
          }
          onClick={() => onChange({ ...value, view: "custom" })}
        >
          {t("console_reports.views.custom")}
        </button>
      </div>

      {value.view === "month" ? (
        <div className="field">
          <label htmlFor="report-month">{t("console_reports.filters.month")}</label>
          <input
            id="report-month"
            type="month"
            className="console-control"
            dir="ltr"
            value={value.month}
            onChange={(event) =>
              onChange({ ...value, month: event.target.value })
            }
          />
        </div>
      ) : value.view === "custom" ? (
        <>
          <div className="field">
            <label htmlFor="report-from">{t("console_reports.filters.from")}</label>
            <input
              id="report-from"
              type="date"
              className="console-control"
              dir="ltr"
              required
              value={value.from}
              onChange={(event) =>
                onChange({ ...value, from: event.target.value })
              }
            />
          </div>
          <div className="field">
            <label htmlFor="report-to">{t("console_reports.filters.to")}</label>
            <input
              id="report-to"
              type="date"
              className="console-control"
              dir="ltr"
              required
              value={value.to}
              onChange={(event) =>
                onChange({ ...value, to: event.target.value })
              }
            />
          </div>
        </>
      ) : null}

      {extra}

      <button
        type="submit"
        className="console-button primary"
        disabled={processing}
      >
        {t("console_reports.filters.apply")}
      </button>
    </form>
  );
}
