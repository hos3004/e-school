import { useForm } from "@inertiajs/react";
import { type FormEvent } from "react";
import { useI18n } from "@/lib/i18n";
export type SessionPayData = {
  version: string;
  currency: string;
  rates: {
    name: string;
    session_type: string;
    duration_minutes: number;
    price: string;
  }[];
};
export default function SessionPaySettings({ data }: { data: SessionPayData }) {
  const t = useI18n();
  const form = useForm({
    version: data.version,
    rates: data.rates,
    reason: "",
  });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/manage/settings/session-pay", { preserveScroll: true });
  }
  const change = (index: number, key: string, value: string | number) =>
    form.setData(
      "rates",
      form.data.rates.map((row, i) =>
        i === index ? { ...row, [key]: value } : row,
      ),
    );
  return (
    <form onSubmit={submit} className="console-panel-body">
      <h3>{t("session_pay.title")}</h3>
      <p className="mb-5 leading-7">{t("session_pay.help")}</p>
      <div className="space-y-4">
        {form.data.rates.map((row, index) => (
          <fieldset key={index} className="rounded-lg border p-4">
            <legend className="px-2">{row.name || t("session_pay.new")}</legend>
            <div className="console-fields">
              <div className="console-field">
                <label htmlFor={"pay-name-" + index}>
                  {t("session_pay.name")}
                </label>
                <input
                  id={"pay-name-" + index}
                  required
                  maxLength={100}
                  value={row.name}
                  onChange={(e) => change(index, "name", e.target.value)}
                />
              </div>
              <div className="console-field">
                <label htmlFor={"pay-type-" + index}>
                  {t("session_pay.type")}
                </label>
                <select
                  id={"pay-type-" + index}
                  value={row.session_type}
                  onChange={(e) =>
                    change(index, "session_type", e.target.value)
                  }
                >
                  <option value="individual">
                    {t("session_pay.individual")}
                  </option>
                  <option value="group">{t("session_pay.group")}</option>
                </select>
              </div>
              <div className="console-field">
                <label htmlFor={"pay-duration-" + index}>
                  {t("session_pay.duration")}
                </label>
                <input
                  id={"pay-duration-" + index}
                  type="number"
                  required
                  min={1}
                  step={1}
                  value={row.duration_minutes}
                  onChange={(e) =>
                    change(index, "duration_minutes", Number(e.target.value))
                  }
                />
              </div>
              <div className="console-field">
                <label htmlFor={"pay-price-" + index}>
                  {t("session_pay.price")} · {data.currency}
                </label>
                <input
                  id={"pay-price-" + index}
                  dir="ltr"
                  type="number"
                  required
                  min={0}
                  step="0.01"
                  value={row.price}
                  onChange={(e) => change(index, "price", e.target.value)}
                />
              </div>
            </div>
            <button
              type="button"
              className="console-button mt-3"
              disabled={form.data.rates.length === 1}
              onClick={() =>
                form.setData(
                  "rates",
                  form.data.rates.filter((_, i) => i !== index),
                )
              }
            >
              {t("session_pay.remove")}
            </button>
          </fieldset>
        ))}
      </div>
      <button
        type="button"
        className="console-button my-4"
        onClick={() =>
          form.setData("rates", [
            ...form.data.rates,
            {
              name: "",
              session_type: "individual",
              duration_minutes: data.rates[0]?.duration_minutes ?? 0,
              price: "",
            },
          ])
        }
      >
        {t("session_pay.add")}
      </button>
      <div className="console-field">
        <label htmlFor="pay-reason">{t("session_pay.reason")}</label>
        <textarea
          id="pay-reason"
          required
          maxLength={1000}
          value={form.data.reason}
          onChange={(e) => form.setData("reason", e.target.value)}
        />
      </div>
      {Object.keys(form.errors).length > 0 && (
        <div className="console-feedback is-error" role="alert">
          {Object.values(form.errors).join(" · ")}
        </div>
      )}
      <button
        className="console-button primary mt-4"
        disabled={form.processing}
      >
        {t(form.processing ? "console.saving" : "session_pay.save")}
      </button>
    </form>
  );
}
