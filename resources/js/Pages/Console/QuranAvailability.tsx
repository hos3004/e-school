import { Head, Link, useForm } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import "../../../css/console-quran.css";
interface Slot {
  id: string;
  weekday: number;
  start_time: string;
  end_time: string;
  timezone: string;
  effective_from: string;
  effective_to: string | null;
  approval_status: string;
  decision_reason: string | null;
  can_decide: boolean;
  can_remove: boolean;
}
interface Props {
  teacher: { id: string; name: string; active: boolean; slots: Slot[] };
  canCreate: boolean;
  defaults: { timezone: string; effective_from: string };
}
export default function QuranAvailability({
  teacher,
  canCreate,
  defaults,
}: Props) {
  const t = useI18n();
  const form = useForm({
    weekday: 0,
    start_time: "",
    end_time: "",
    timezone: defaults.timezone,
    effective_from: defaults.effective_from,
    effective_to: "",
  });
  const action = useForm({ decision: "approved" });
  const href = "/manage/teachers/" + teacher.id + "/availability";
  const decide = (slot: Slot, decision: string) => {
    action.transform(() => ({ decision }));
    action.post(href + "/" + slot.id + "/decision", { preserveScroll: true });
  };
  return (
    <ConsoleLayout
      title={t("console_quran.availability_title") + " · " + teacher.name}
      description={t("console_quran.availability_page_description")}
      section="before"
      actions={
        <Link
          className="console-button"
          href={"/manage/teachers/" + teacher.id}
        >
          {t("console_quran.full_profile")}
        </Link>
      }
    >
      <Head title={t("console_quran.availability_title")} />
      <div className="quran-workspace quran-availability-page">
        <div className="quran-context">
          <span>{t("console_quran.availability_help")}</span>
          <Link className="inline-link" href="/manage/quran?tab=teachers">
            {t("console_quran.title")}
          </Link>
        </div>
        {canCreate && teacher.active && (
          <section className="panel quran-availability-form">
            <div className="panel-head">
              <div>
                <h2>{t("console_quran.availability_add")}</h2>
                <p className="cell-sub">
                  {t("console_quran.availability_pending_help")}
                </p>
              </div>
            </div>
            <form
              onSubmit={(event) => {
                event.preventDefault();
                form.post(href, {
                  preserveScroll: true,
                  onSuccess: () => form.reset("start_time", "end_time"),
                });
              }}
            >
              <fieldset disabled={form.processing} className="field-grid">
                <div className="field">
                  <label htmlFor="availability-day">
                    {t("console_quran.weekday")}
                  </label>
                  <select
                    id="availability-day"
                    className="console-control"
                    value={form.data.weekday}
                    onChange={(event) =>
                      form.setData("weekday", Number(event.target.value))
                    }
                  >
                    {Array.from({ length: 7 }, (_, day) => (
                      <option key={day} value={day}>
                        {t("console_quran.days." + day)}
                      </option>
                    ))}
                  </select>
                </div>
                {(
                  [
                    "start_time",
                    "end_time",
                    "effective_from",
                    "effective_to",
                    "timezone",
                  ] as const
                ).map((key) => (
                  <div key={key} className="field">
                    <label htmlFor={"availability-" + key}>
                      {t(
                        "console_quran." +
                          {
                            effective_from: "starts_on",
                            effective_to: "ends_on",
                            start_time: "start_time",
                            end_time: "end_time",
                            timezone: "timezone",
                          }[key],
                      )}
                      {key === "effective_to" && (
                        <small> · {t("console_quran.optional")}</small>
                      )}
                    </label>
                    <input
                      id={"availability-" + key}
                      className="console-control"
                      type={
                        key.includes("time") && key !== "timezone"
                          ? "time"
                          : key.startsWith("effective")
                            ? "date"
                            : "text"
                      }
                      dir="ltr"
                      value={form.data[key]}
                      required={key !== "effective_to"}
                      min={
                        key === "effective_to"
                          ? form.data.effective_from
                          : undefined
                      }
                      aria-invalid={!!form.errors[key]}
                      aria-describedby={
                        form.errors[key] ? "error-" + key : undefined
                      }
                      onChange={(event) =>
                        form.setData(key, event.target.value)
                      }
                    />
                    {form.errors[key] && (
                      <small id={"error-" + key} className="quran-error">
                        {form.errors[key]}
                      </small>
                    )}
                  </div>
                ))}
              </fieldset>
              {Object.entries(form.errors)
                .filter(
                  ([key]) =>
                    ![
                      "start_time",
                      "end_time",
                      "effective_from",
                      "effective_to",
                      "timezone",
                    ].includes(key),
                )
                .map(([key, error]) => (
                  <p key={key} className="quran-error" role="alert">
                    {error}
                  </p>
                ))}
              <div className="actions">
                <button
                  className="console-button primary"
                  type="submit"
                  disabled={form.processing}
                >
                  {t(
                    form.processing
                      ? "console_quran.saving"
                      : "console_quran.availability_add",
                  )}
                </button>
              </div>
            </form>
          </section>
        )}
        {!teacher.active && (
          <p className="detail-note">
            {t("console_quran.availability_inactive_teacher")}
          </p>
        )}
        <section className="panel">
          <div className="panel-head">
            <div>
              <h2>{t("console_quran.declared_availability")}</h2>
              <p className="cell-sub">
                {t("console_quran.availability_approved_protection")}
              </p>
            </div>
          </div>
          {Object.values(action.errors).map((error) => (
            <p className="quran-error" role="alert" key={error}>
              {error}
            </p>
          ))}
          <div className="console-table-scroll">
            <table className="table console-table quran-availability-table">
              <thead>
                <tr>
                  {[
                    "weekday",
                    "slots",
                    "timezone",
                    "placement_period",
                    "availability_decision",
                    "action",
                  ].map((key) => (
                    <th key={key} scope="col">
                      {t("console_quran." + key)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {teacher.slots.map((slot) => (
                  <tr key={slot.id}>
                    <td>{t("console_quran.days." + slot.weekday)}</td>
                    <td>
                      <bdi>
                        {slot.start_time}–{slot.end_time}
                      </bdi>
                    </td>
                    <td>
                      <bdi>{slot.timezone}</bdi>
                    </td>
                    <td>
                      <bdi>{slot.effective_from}</bdi>
                      <small className="cell-sub">
                        <bdi>
                          {slot.effective_to ?? t("console_quran.open_end")}
                        </bdi>
                      </small>
                    </td>
                    <td>
                      <span
                        className={
                          "status " +
                          (slot.approval_status === "approved"
                            ? "green"
                            : slot.approval_status === "pending"
                              ? "amber"
                              : "slate")
                        }
                      >
                        {t("console_quran.approval." + slot.approval_status)}
                      </span>
                      {slot.decision_reason && (
                        <small className="cell-sub">
                          {slot.decision_reason}
                        </small>
                      )}
                    </td>
                    <td>
                      <div className="actions">
                        {slot.can_decide && (
                          <>
                            <button
                              className="console-button"
                              type="button"
                              disabled={action.processing}
                              onClick={() => decide(slot, "approved")}
                            >
                              {t("console_quran.availability_approve")}
                            </button>
                            <button
                              className="console-button"
                              type="button"
                              disabled={action.processing}
                              onClick={() => decide(slot, "rejected")}
                            >
                              {t("console_quran.availability_reject")}
                            </button>
                          </>
                        )}
                        {slot.can_remove && (
                          <button
                            type="button"
                            className="inline-link"
                            disabled={action.processing}
                            onClick={() =>
                              action.delete(href + "/" + slot.id, {
                                preserveScroll: true,
                              })
                            }
                          >
                            {t("console_quran.availability_withdraw")}
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
                {teacher.slots.length === 0 && (
                  <tr>
                    <td className="quran-empty" colSpan={6}>
                      {t("console_quran.no_declared_availability")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </ConsoleLayout>
  );
}
