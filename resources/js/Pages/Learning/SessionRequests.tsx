import { useForm, usePage } from "@inertiajs/react";
import { useState } from "react";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";
import { date, Errors } from "./shared";
export interface SessionRequestProps {
  canRequestPostponement?: boolean;
  canSubmitApology?: boolean;
  postponementRequestUrl?: string;
  studentApologyUrl?: string;
  postponementRequest?: {
    id: string;
    status: string;
    reason: string;
    proposedStart: string;
    teacherProposedStart?: string | null;
    acceptAlternativeUrl?: string | null;
  } | null;
  studentApology?: { submittedAt: string; reason: string } | null;
}
export function RequestFields({
  data,
  setData,
  timezone,
  withDate = true,
}: {
  data: { category: string; note: string; proposed_local: string };
  setData: (key: "category" | "note" | "proposed_local", value: string) => void;
  timezone: string;
  withDate?: boolean;
}) {
  const t = useI18n();
  return (
    <>
      {withDate && (
        <label className="lp-field">
          {t("learning.requests.proposed")}
          <input
            dir="ltr"
            type="datetime-local"
            required
            value={data.proposed_local}
            onChange={(e) => setData("proposed_local", e.target.value)}
          />
          <small>
            {t("learning.times_in")} <b dir="ltr">{timezone}</b>
          </small>
        </label>
      )}
      <label className="lp-field">
        {t("learning.requests.category")}
        <select
          value={data.category}
          onChange={(e) => setData("category", e.target.value)}
        >
          {["schedule", "health", "technical", "personal"].map((key) => (
            <option key={key} value={key}>
              {t(`learning.requests.categories.${key}`)}
            </option>
          ))}
        </select>
      </label>
      <label className="lp-field">
        {t("learning.requests.note")}
        <textarea
          rows={3}
          maxLength={1500}
          value={data.note}
          onChange={(e) => setData("note", e.target.value)}
        />
      </label>
    </>
  );
}
export default function SessionRequests({
  kind,
  timezone,
  sessionId,
  ...props
}: SessionRequestProps & {
  kind: "student" | "teacher";
  timezone: string;
  sessionId: string;
}) {
  const t = useI18n();
  const user = usePage<AppPageProps>().props.auth.user?.id ?? "";
  const [mode, setMode] = useState<"postpone" | "apologize">("postpone");
  const form = useForm(`learning-request-${user}-${sessionId}`, {
    category: "schedule",
    note: "",
    proposed_local: "",
  });
  const accept = useForm({});
  const current = props.postponementRequest;
  const canForm = props.canRequestPostponement || props.canSubmitApology;
  if (!current && !props.studentApology && !canForm) return null;
  return (
    <section className="lp-section" id="requests">
      <div className="lp-section-head">
        <h2>{t("learning.requests.title")}</h2>
      </div>
      <div className="lp-request-columns">
        <div className="lp-report-card">
          {current && (
            <div className="lp-report-card">
              <h3>{t("learning.requests.current_request")}</h3>
              <span className="lp-tag">
                {t(`learning.requests.statuses.${current.status}`)}
              </span>
              <p>{current.reason}</p>
              <p>
                {date(current.proposedStart, "ar", timezone)} ·{" "}
                {date(current.proposedStart, "ar", timezone, true)}
              </p>
              {current.teacherProposedStart && (
                <p>
                  {t("learning.requests.teacher_proposed")}:{" "}
                  {date(current.teacherProposedStart, "ar", timezone)} ·{" "}
                  {date(current.teacherProposedStart, "ar", timezone, true)}
                </p>
              )}
              {current.acceptAlternativeUrl && (
                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    accept.post(current.acceptAlternativeUrl!, {
                      preserveScroll: true,
                    });
                  }}
                >
                  <Errors errors={accept.errors} />
                  <button className="lp-btn" disabled={accept.processing}>
                    {t("learning.requests.accept")}
                  </button>
                </form>
              )}
            </div>
          )}
          {props.studentApology && (
            <div className="lp-report-card">
              <h3>{t("learning.requests.recorded_apology")}</h3>
              <p>{props.studentApology.reason}</p>
              <small>
                {date(props.studentApology.submittedAt, "ar", timezone)}
              </small>
            </div>
          )}
          <p className="lp-muted">
            {t(
              kind === "teacher"
                ? "learning.requests.teacher_hint"
                : "learning.requests.student_hint",
            )}
          </p>
        </div>
        {canForm && (
          <form
            className="lp-report-card lp-form lp-auth-form"
            onSubmit={(e) => {
              e.preventDefault();
              const apology =
                mode === "apologize" || !props.canRequestPostponement;
              const url = apology
                ? props.studentApologyUrl
                : props.postponementRequestUrl;
              if (url)
                form.post(url, {
                  preserveScroll: true,
                  onSuccess: () => form.reset(),
                });
            }}
          >
            <h3>{t("learning.requests.new")}</h3>
            {props.canRequestPostponement && props.canSubmitApology && (
              <div className="lp-segmented">
                <button
                  type="button"
                  className={mode === "postpone" ? "active" : ""}
                  onClick={() => setMode("postpone")}
                >
                  {t("learning.requests.postpone")}
                </button>
                <button
                  type="button"
                  className={mode === "apologize" ? "active" : ""}
                  onClick={() => setMode("apologize")}
                >
                  {t("learning.requests.apologize")}
                </button>
              </div>
            )}
            <Errors errors={form.errors} />
            <RequestFields
              data={form.data}
              setData={form.setData}
              timezone={timezone}
              withDate={
                Boolean(props.canRequestPostponement) && mode === "postpone"
              }
            />
            <button className="lp-btn" disabled={form.processing}>
              {t(
                kind === "teacher"
                  ? "learning.requests.move"
                  : "learning.requests.send",
              )}
            </button>
          </form>
        )}
      </div>
    </section>
  );
}
