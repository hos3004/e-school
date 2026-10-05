import { useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import { useI18n } from "@/lib/i18n";
import type {
  DuesAdjustment,
  DuesCanDecide,
  DuesDetail,
  DuesLesson,
  DuesPeriod,
  DuesSessionDecision,
} from "./TeacherDuesTypes";

function Errors({ errors }: { errors: Record<string, string> }) {
  return Object.keys(errors).length ? (
    <div className="console-feedback is-error" role="alert">
      <ul>
        {Object.entries(errors).map(([key, message]) => (
          <li key={key}>{message}</li>
        ))}
      </ul>
    </div>
  ) : null;
}
export function ProposeDues({
  detail,
  periods,
  currency,
  types,
  requiresSecondApprover,
}: {
  detail: DuesDetail;
  periods: DuesPeriod[];
  currency: string;
  types: string[];
  requiresSecondApprover: boolean;
}) {
  const t = useI18n();
  const form = useForm({
    staff_profile_id: detail.id,
    type: types[0] ?? "",
    amount: "",
    reason_category: "",
    note: "",
    references_period_id: "",
  });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(detail.proposeUrl, {
      preserveScroll: true,
      onSuccess: () =>
        form.reset("amount", "reason_category", "note", "references_period_id"),
    });
  }
  return (
    <form onSubmit={submit} className="detail-block print:hidden">
      <h3>{t("console_dues.propose_title")}</h3>
      <p>
        {t(
          requiresSecondApprover
            ? "console_dues.propose_help"
            : "console_dues.propose_help_direct",
        )}
      </p>
      <Errors errors={form.errors} />
      <div className="field-grid operation-field">
        <div className="field">
          <label htmlFor="dues-type">{t("console_dues.adjustment_type")}</label>
          <select
            id="dues-type"
            required
            value={form.data.type}
            onChange={(e) => form.setData("type", e.target.value)}
          >
            {types.map((type) => (
              <option key={type} value={type}>
                {t(`console_dues.types.${type}`)}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="dues-amount">
            {t("console_dues.amount")} · <bdi>{currency}</bdi>
          </label>
          <input
            id="dues-amount"
            dir="ltr"
            required
            inputMode="decimal"
            pattern="[0-9]+([.][0-9]{1,2})?"
            value={form.data.amount}
            aria-invalid={!!form.errors.amount}
            onChange={(e) => form.setData("amount", e.target.value)}
          />
          <small>{t("console_dues.amount_help")}</small>
        </div>
        <div className="field">
          <label htmlFor="dues-reason">
            {t("console_dues.reason_category")}
          </label>
          <select
            id="dues-reason"
            required
            value={form.data.reason_category}
            onChange={(e) => form.setData("reason_category", e.target.value)}
          >
            <option value="">{t("console_dues.choose")}</option>
            {[
              "extra_work",
              "materials",
              "periodic",
              "previous_error",
              "advance",
              "reimbursement",
              "other",
            ].map((key) => (
              <option key={key} value={key}>
                {t(`console_dues.reasons.${key}`)}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="dues-reference">
            {t("console_dues.reference_period")}
          </label>
          <select
            id="dues-reference"
            value={form.data.references_period_id}
            onChange={(e) =>
              form.setData("references_period_id", e.target.value)
            }
          >
            <option value="">{t("console_dues.no_reference")}</option>
            {periods.map((period) => (
              <option key={period.id} value={period.id}>
                {`${period.year}/${String(period.month).padStart(2, "0")}`} ·{" "}
                {t(`console_dues.period_status.${period.status}`)}
              </option>
            ))}
          </select>
        </div>
        <div className="field span2">
          <label htmlFor="dues-note">
            {t(
              form.data.reason_category === "other"
                ? "console_dues.explanation"
                : "console_dues.note",
            )}
          </label>
          <textarea
            id="dues-note"
            required={form.data.reason_category === "other"}
            maxLength={1500}
            value={form.data.note}
            onChange={(e) => form.setData("note", e.target.value)}
          />
        </div>
      </div>
      <button
        className="console-button primary operation-field"
        disabled={form.processing}
        type="submit"
      >
        {t(
          form.processing
            ? "console_dues.saving"
            : requiresSecondApprover
              ? "console_dues.submit_proposal"
              : "console_dues.confirm_decision",
        )}
      </button>
    </form>
  );
}
export function DuesDecision({ adjustment }: { adjustment: DuesAdjustment }) {
  const t = useI18n();
  const [decision, setDecision] = useState<"approve" | "reject" | null>(null);
  const form = useForm({ reason_category: "reviewed", note: "" });
  if (!adjustment.approveUrl && !adjustment.rejectUrl) return null;
  function submit(event: FormEvent) {
    event.preventDefault();
    const url =
      decision === "approve" ? adjustment.approveUrl : adjustment.rejectUrl;
    if (url)
      form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
          setDecision(null);
          form.reset();
        },
      });
  }
  return (
    <div className="print:hidden">
      <div className="actions operation-field">
        {adjustment.approveUrl && (
          <button
            className="console-button"
            onClick={() => {
              setDecision("approve");
              form.setData("reason_category", "reviewed");
            }}
          >
            {t("console_dues.approve_action")}
          </button>
        )}
        {adjustment.rejectUrl && (
          <button
            className="console-button"
            onClick={() => {
              setDecision("reject");
              form.setData("reason_category", "incomplete");
            }}
          >
            {t("console_dues.reject_action")}
          </button>
        )}
      </div>
      {decision && (
        <form onSubmit={submit} className="detail-note operation-field">
          <strong>
            {t(
              decision === "approve"
                ? "console_dues.approve_review"
                : "console_dues.reject_review",
            )}{" "}
            ·{" "}
            <bdi>
              {adjustment.amount} {adjustment.currency}
            </bdi>
          </strong>
          <Errors errors={form.errors} />
          <div className="field operation-field">
            <label htmlFor={`decision-${adjustment.id}`}>
              {t("console_dues.decision_category")}
            </label>
            <select
              id={`decision-${adjustment.id}`}
              required
              value={form.data.reason_category}
              onChange={(e) => form.setData("reason_category", e.target.value)}
            >
              {(decision === "approve"
                ? ["reviewed", "other"]
                : ["incomplete", "not_due", "other"]
              ).map((key) => (
                <option value={key} key={key}>
                  {t(`console_dues.reasons.${key}`)}
                </option>
              ))}
            </select>
          </div>
          <div className="field operation-field">
            <label htmlFor={`note-${adjustment.id}`}>
              {t(
                form.data.reason_category === "other"
                  ? "console_dues.explanation"
                  : "console_dues.note",
              )}
            </label>
            <textarea
              id={`note-${adjustment.id}`}
              required={form.data.reason_category === "other"}
              value={form.data.note}
              maxLength={1500}
              onChange={(e) => form.setData("note", e.target.value)}
            />
          </div>
          <div className="actions operation-field">
            <button
              className="console-button primary"
              type="submit"
              disabled={form.processing}
            >
              {t(
                form.processing
                  ? "console_dues.saving"
                  : "console_dues.confirm_decision",
              )}
            </button>
            <button
              className="console-button"
              type="button"
              disabled={form.processing}
              onClick={() => {
                setDecision(null);
                form.clearErrors();
              }}
            >
              {t("console_dues.cancel")}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
const DECISION_REASONS: Record<DuesSessionDecision, string[]> = {
  complete: ["off_platform", "on_platform", "other"],
  no_show: ["student_absent", "other"],
  excused: ["student_absent", "other"],
  cancelled_by_school: ["not_held", "other"],
};
export function LessonDecision({
  lesson,
  canDecide,
  currency,
}: {
  lesson: DuesLesson;
  canDecide: DuesCanDecide;
  currency: string;
}) {
  const t = useI18n();
  const [decision, setDecision] = useState<DuesSessionDecision | null>(null);
  const form = useForm({
    decision: "",
    expected_status: lesson.status,
    reason_category: "",
    note: "",
    amount: "",
  });
  const allowed = (
    Object.keys(DECISION_REASONS) as DuesSessionDecision[]
  ).filter((key) => canDecide[key]);
  if (!lesson.decisionUrl || !allowed.length) return null;
  function start(key: DuesSessionDecision) {
    setDecision(key);
    form.clearErrors();
    form.setData({
      decision: key,
      expected_status: lesson.status,
      reason_category: DECISION_REASONS[key][0],
      note: "",
      amount:
        key === "complete" && !lesson.pricingNote
          ? (lesson.suggestedAmount ?? "")
          : "",
    });
  }
  function submit(event: FormEvent) {
    event.preventDefault();
    if (!lesson.decisionUrl) return;
    form.post(lesson.decisionUrl, {
      preserveScroll: true,
      onSuccess: () => {
        setDecision(null);
        form.reset();
      },
    });
  }
  return (
    <div className="print:hidden">
      <div className="actions operation-field">
        {allowed.map((key) => (
          <button
            type="button"
            key={key}
            className="console-button"
            onClick={() => start(key)}
          >
            {t(`console_dues.session_decisions.${key}`)}
          </button>
        ))}
      </div>
      {decision && (
        <form onSubmit={submit} className="detail-note operation-field">
          <strong>{t("console_dues.decide_title")}</strong>
          <p className="cell-sub">
            {t(`console_dues.session_decisions.${decision}`)}
          </p>
          <Errors errors={form.errors} />
          {decision === "complete" && lesson.pricingNote && (
            <p className="cell-sub">
              {t(`console_dues.${lesson.pricingNote}_note`)}
            </p>
          )}
          {decision === "complete" &&
            !lesson.pricingNote &&
            lesson.rateKnown === false && (
              <p className="console-feedback is-error" role="alert">
                {t("console_dues.no_rate_warning")}
              </p>
            )}
          <div className="field operation-field">
            <label htmlFor={`session-reason-${lesson.id}`}>
              {t("console_dues.decision_category")}
            </label>
            <select
              id={`session-reason-${lesson.id}`}
              required
              value={form.data.reason_category}
              onChange={(e) => form.setData("reason_category", e.target.value)}
            >
              {DECISION_REASONS[decision].map((key) => (
                <option value={key} key={key}>
                  {t(`console_dues.session_reasons.${key}`)}
                </option>
              ))}
            </select>
          </div>
          {decision === "complete" && !lesson.pricingNote && (
            <div className="field operation-field">
              <label htmlFor={`session-amount-${lesson.id}`}>
                {t("console_dues.session_amount")} · <bdi>{currency}</bdi>
              </label>
              <input
                id={`session-amount-${lesson.id}`}
                dir="ltr"
                inputMode="decimal"
                pattern="[0-9]+([.][0-9]{1,2})?"
                value={form.data.amount}
                aria-invalid={!!form.errors.amount}
                onChange={(e) => form.setData("amount", e.target.value)}
              />
              <small>{t("console_dues.session_amount_help")}</small>
              {lesson.suggestedAmount && (
                <small>
                  {t("console_dues.suggested_amount")}:{" "}
                  <bdi>
                    {lesson.suggestedAmount} {currency}
                  </bdi>
                </small>
              )}
            </div>
          )}
          <div className="field operation-field">
            <label htmlFor={`session-note-${lesson.id}`}>
              {t(
                form.data.reason_category === "other"
                  ? "console_dues.explanation"
                  : "console_dues.note",
              )}
            </label>
            <textarea
              id={`session-note-${lesson.id}`}
              required={form.data.reason_category === "other"}
              maxLength={1500}
              value={form.data.note}
              onChange={(e) => form.setData("note", e.target.value)}
            />
          </div>
          <p className="cell-sub">{t("console_dues.session_confirm")}</p>
          <div className="actions operation-field">
            <button
              className="console-button primary"
              type="submit"
              disabled={form.processing}
            >
              {t(
                form.processing
                  ? "console_dues.saving"
                  : "console_dues.confirm_decision",
              )}
            </button>
            <button
              className="console-button"
              type="button"
              disabled={form.processing}
              onClick={() => {
                setDecision(null);
                form.clearErrors();
              }}
            >
              {t("console_dues.cancel")}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
