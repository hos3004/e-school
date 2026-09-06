import { Link, useForm, usePage } from "@inertiajs/react";
import LearningLayout from "@/Layouts/LearningLayout";
import { useI18n } from "@/lib/i18n";
import { Empty, Errors } from "./shared";
type Slot = {
  id: string;
  weekday: number;
  start_time: string;
  end_time: string;
  timezone: string;
  effective_from: string;
  effective_to: string | null;
  approval_status: string;
  decision_reason: string | null;
  can_remove: boolean;
};
export default function Availability({
  teacher,
  canCreate,
  defaults,
  timezones,
}: {
  teacher: { name: string; active: boolean; slots: Slot[] };
  canCreate: boolean;
  defaults: { timezone: string; effective_from: string };
  timezones: string[];
}) {
  const t = useI18n();
  const accountId = usePage<{ auth: { user: { id: string } } }>().props.auth
    .user.id;
  const form = useForm(`learning-my-availability-${accountId}`, {
    weekday: 0,
    start_time: "",
    end_time: "",
    timezone: defaults.timezone,
    effective_from: defaults.effective_from,
    effective_to: "",
  });
  const removal = useForm({});
  return (
    <LearningLayout kind="teacher" title={t("learning.availability")}>
      <div className="lp-page-heading">
        <div>
          <div className="lp-kicker">
            {t("learning.workspace.professional_kicker")}
          </div>
          <h1>{t("learning.availability")}</h1>
          <p>{t("console_quran.availability_pending_help")}</p>
        </div>
        <Link className="lp-text-action" href="/learn/teacher#progress">
          {t("learning.back_portal")}
        </Link>
      </div>
      <div className="lp-availability-grid">
        <section className="lp-report-card">
          <h2>{t("console_quran.availability_title")}</h2>
          <Errors errors={removal.errors} />
          {teacher.slots.length ? (
            teacher.slots.map((slot) => (
              <article className="lp-resource-row" key={slot.id}>
                <span>
                  <b>
                    {t(`learning.workspace.weekdays.${slot.weekday}`)} ·{" "}
                    <bdi>
                      {slot.start_time}–{slot.end_time}
                    </bdi>
                  </b>
                  <small>
                    {slot.timezone} · <bdi>{slot.effective_from}</bdi>
                    {slot.effective_to && (
                      <>
                        {" "}
                        — <bdi>{slot.effective_to}</bdi>
                      </>
                    )}
                  </small>
                  <span className="lp-tag">
                    {t(`statuses.${slot.approval_status}`)}
                  </span>
                  {slot.decision_reason && (
                    <small>{slot.decision_reason}</small>
                  )}
                  {slot.can_remove && (
                    <button
                      className="lp-text-action"
                      disabled={removal.processing}
                      onClick={() =>
                        removal.delete(
                          `/learn/teacher/availability/${slot.id}`,
                          { preserveScroll: true },
                        )
                      }
                    >
                      {t("learning.services.withdraw_availability")}
                    </button>
                  )}
                </span>
              </article>
            ))
          ) : (
            <Empty>{t("learning.workspace.no_availability")}</Empty>
          )}
        </section>
        {canCreate && teacher.active && (
          <section className="lp-report-card">
            <h2>{t("console_quran.availability_add")}</h2>
            <form
              className="lp-form lp-auth-form"
              onSubmit={(event) => {
                event.preventDefault();
                form.post("/learn/teacher/availability", {
                  preserveScroll: true,
                  onSuccess: () => form.reset("start_time", "end_time"),
                });
              }}
            >
              <Errors errors={form.errors} />
              <label className="lp-field">
                <span>{t("console_quran.weekday")}</span>
                <select
                  value={form.data.weekday}
                  onChange={(e) =>
                    form.setData("weekday", Number(e.target.value))
                  }
                >
                  {Array.from({ length: 7 }, (_, day) => (
                    <option key={day} value={day}>
                      {t(`learning.workspace.weekdays.${day}`)}
                    </option>
                  ))}
                </select>
              </label>
              <div className="lp-two-fields">
                {(["start_time", "end_time"] as const).map((key) => (
                  <label className="lp-field" key={key}>
                    <span>{t(`console_quran.${key}`)}</span>
                    <input
                      type="time"
                      required
                      dir="ltr"
                      value={form.data[key]}
                      onChange={(e) => form.setData(key, e.target.value)}
                    />
                  </label>
                ))}
              </div>
              <label className="lp-field">
                <span>{t("learning.timezone")}</span>
                <select
                  value={form.data.timezone}
                  onChange={(e) => form.setData("timezone", e.target.value)}
                >
                  {timezones.map((zone) => (
                    <option key={zone}>{zone}</option>
                  ))}
                </select>
              </label>
              <div className="lp-two-fields">
                {(["effective_from", "effective_to"] as const).map((key) => (
                  <label className="lp-field" key={key}>
                    <span>
                      {t(
                        key === "effective_from"
                          ? "console_quran.starts_on"
                          : "console_quran.ends_on",
                      )}
                    </span>
                    <input
                      type="date"
                      required={key === "effective_from"}
                      value={form.data[key]}
                      min={
                        key === "effective_to"
                          ? form.data.effective_from
                          : undefined
                      }
                      onChange={(e) => form.setData(key, e.target.value)}
                    />
                  </label>
                ))}
              </div>
              <button
                className="lp-btn"
                type="submit"
                disabled={form.processing}
              >
                {t(
                  form.processing
                    ? "learning.saving"
                    : "learning.services.request_availability",
                )}
              </button>
            </form>
          </section>
        )}
      </div>
    </LearningLayout>
  );
}
