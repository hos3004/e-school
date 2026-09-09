import { Head, Link, useForm } from "@inertiajs/react";
import { useEffect, useState, type FormEvent } from "react";
import { useI18n } from "@/lib/i18n";

type Option = { id: string; name: string };
type Profile = {
  name: string;
  email: string;
  phone: string;
  timezone: string;
  country_id: string;
  region_id: string;
  region_name: string;
  city: string;
  date_of_birth: string;
  gender: string;
};
export default function CompleteProfile({
  profile,
  required,
  countries,
  regions: initialRegions,
  timezones,
}: {
  profile: Profile;
  required: boolean;
  countries: Option[];
  regions: Option[];
  timezones: string[];
}) {
  const t = useI18n();
  const form = useForm({
    name: profile.name ?? "",
    email: profile.email?.endsWith(".invalid") ? "" : (profile.email ?? ""),
    phone: profile.phone ?? "",
    timezone: profile.timezone ?? "",
    country_id: profile.country_id ?? "",
    region_id: initialRegions.some((region) => region.id === profile.region_id)
      ? profile.region_id
      : "",
    region_name: profile.region_name ?? "",
    city: profile.city ?? "",
    date_of_birth: profile.date_of_birth ?? "",
    gender: profile.gender ?? "",
    confirmed: false,
  });
  const [regions, setRegions] = useState(initialRegions);
  const [loading, setLoading] = useState(false);
  const [regionError, setRegionError] = useState(false);
  useEffect(() => {
    if (!form.data.country_id) {
      setRegions([]);
      return;
    }
    const controller = new AbortController();
    setLoading(true);
    setRegionError(false);
    fetch(
      "/profile/complete/regions?country_id=" +
        encodeURIComponent(form.data.country_id),
      {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        signal: controller.signal,
      },
    )
      .then((response) => {
        if (!response.ok) throw new Error();
        return response.json() as Promise<Option[]>;
      })
      .then(setRegions)
      .catch(() => {
        if (!controller.signal.aborted) {
          setRegions([]);
          setRegionError(true);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [form.data.country_id]);
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/profile/complete", { preserveScroll: true });
  }
  const textFields = [
    "name",
    "email",
    "phone",
    "city",
    "date_of_birth",
  ] as const;
  return (
    <main className="min-h-screen bg-slate-50 px-4 py-10 text-slate-900">
      <Head title={t("profile_completion.title")} />
      <section className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <h1 className="mb-4 text-2xl font-bold">
          {t("profile_completion.title")}
        </h1>
        <p className="mb-6 rounded-xl bg-amber-50 p-4 leading-8" role="note">
          {t(
            required
              ? "profile_completion.notice"
              : "profile_completion.edit_notice",
          )}
        </p>
        <form onSubmit={submit}>
          <div className="grid gap-5 sm:grid-cols-2">
            {textFields.map((key) => (
              <div key={key}>
                <label className="mb-2 block font-medium" htmlFor={key}>
                  {t("profile_completion." + key)}
                </label>
                <input
                  id={key}
                  className="w-full rounded-lg border border-slate-300 p-3"
                  required
                  maxLength={key === "phone" ? 24 : 191}
                  type={
                    key === "email"
                      ? "email"
                      : key === "date_of_birth"
                        ? "date"
                        : key === "phone"
                          ? "tel"
                          : "text"
                  }
                  dir={
                    ["phone", "email", "date_of_birth"].includes(key)
                      ? "ltr"
                      : undefined
                  }
                  value={form.data[key]}
                  onChange={(e) => form.setData(key, e.target.value)}
                  aria-invalid={!!form.errors[key]}
                  aria-describedby={key + "-help"}
                />
                <small
                  id={key + "-help"}
                  className={
                    "mt-1 block " +
                    (form.errors[key] ? "text-red-700" : "text-slate-600")
                  }
                >
                  {form.errors[key] ||
                    (key === "phone" ? t("profile_completion.phone_help") : "")}
                </small>
              </div>
            ))}
            {(["country_id", "region_id", "gender", "timezone"] as const).map(
              (key) => {
                const options =
                  key === "country_id"
                    ? countries
                    : key === "region_id"
                      ? regions
                      : key === "gender"
                        ? ["male", "female"].map((id) => ({
                            id,
                            name: t("profile_completion." + id),
                          }))
                        : timezones.map((id) => ({ id, name: id }));
                return (
                  <div key={key}>
                    <label className="mb-2 block font-medium" htmlFor={key}>
                      {t("profile_completion." + key)}
                    </label>
                    <select
                      id={key}
                      className="w-full rounded-lg border border-slate-300 p-3"
                      required={key !== "region_id"}
                      disabled={key === "region_id" && loading}
                      value={form.data[key]}
                      aria-invalid={!!form.errors[key]}
                      onChange={(e) => {
                        form.setData(key, e.target.value);
                        if (key === "country_id") {
                          form.setData("region_id", "");
                          form.setData("region_name", "");
                        }
                      }}
                    >
                      <option value="">{t("profile_completion.select")}</option>
                      {options.map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.name}
                        </option>
                      ))}
                    </select>
                    {form.errors[key] && (
                      <small className="text-red-700">{form.errors[key]}</small>
                    )}
                    {key === "region_id" && regionError && (
                      <p role="alert" className="text-red-700">
                        {t("profile_completion.load_error")}
                      </p>
                    )}
                  </div>
                );
              },
            )}
          </div>
          {!form.data.region_id && (
            <div className="mt-5">
              <label className="mb-2 block font-medium" htmlFor="region_name">
                {t("profile_completion.region_name")}
              </label>
              <input
                id="region_name"
                className="w-full rounded-lg border border-slate-300 p-3"
                required
                maxLength={191}
                value={form.data.region_name}
                onChange={(e) => form.setData("region_name", e.target.value)}
              />
              {form.errors.region_name && (
                <small className="text-red-700">
                  {form.errors.region_name}
                </small>
              )}
            </div>
          )}
          <label className="my-6 flex items-start gap-3 leading-7">
            <input
              className="mt-2"
              type="checkbox"
              required
              checked={form.data.confirmed}
              onChange={(e) => form.setData("confirmed", e.target.checked)}
            />
            {t("profile_completion.confirmed")}
          </label>
          {form.errors.confirmed && (
            <p role="alert" className="text-red-700">
              {form.errors.confirmed}
            </p>
          )}
          <div className="flex flex-wrap items-center gap-5">
            <button
              className="rounded-lg bg-teal-800 px-6 py-3 font-bold text-white disabled:opacity-50"
              disabled={form.processing || loading}
            >
              {t(
                form.processing
                  ? "profile_completion.saving"
                  : "profile_completion.save",
              )}
            </button>
            <Link
              href="/logout"
              method="post"
              as="button"
              className="underline"
            >
              {t("profile_completion.logout")}
            </Link>
          </div>
        </form>
      </section>
    </main>
  );
}
