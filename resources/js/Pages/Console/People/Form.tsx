import { Head, Link, useForm } from "@inertiajs/react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import CountryInput from "@/Components/CountryInput";
import {
  Choice,
  Field,
  Section,
  fieldClass,
  primaryClass,
  secondaryClass,
  type PersonKind,
} from "@/Components/Console/PeopleFields";
import { useI18n } from "@/lib/i18n";
import "../../../../css/console-people.css";

type Data = {
  account_mode: string;
  existing_user_id: string;
  full_name: string;
  email: string;
  phone: string;
  username: string;
  password: string;
  password_confirmation: string;
  locale: string;
  timezone: string;
  date_of_birth: string;
  gender: string;
  country_id: string;
  region_id: string;
  nationality: string;
  city: string;
  preferred_language: string;
  notes: string;
  preferred_program_id: string;
  preferred_course_id: string;
  staff_code: string;
  employment_type: string;
  hired_at: string;
  bio: string;
  specializations: string[];
  contract_basis: string;
  contract_effective_from: string;
  contract_effective_to: string;
  currency: string;
  base_amount_major: string;
  default_rate_major: string;
  monthly_target_sessions: string;
  target_admin_tasks: string;
  target_training_sessions: string;
  course_ids: string[];
  qualification_notes: string;
  teaching_staff_profile_id: string;
  teaching_duration_minutes: string;
  teaching_weekday: string;
  teaching_start_time: string;
  teaching_starts_on: string;
};
type Props = {
  kind: PersonKind;
  mode: "create" | "edit";
  canUpdateAccount: boolean;
  personKinds?: { kind: PersonKind; label: string; url: string }[];
  person: Partial<Data>;
  countries: Choice[];
  programs: Choice[];
  courses: Choice[];
  timezones: string[];
  employmentTypes: Choice[];
  contractBases: Choice[];
  currencies: string[];
  optionsUrl: string;
  usernameUrl: string;
  submitUrl: string;
  backUrl: string;
  teaching?: {
    durations: number[];
    timezone: string;
    startsOn: string;
  } | null;
};
type Options = {
  regions: Choice[];
  courses: Choice[];
  accounts: Choice[];
  teachers: Choice[];
};
export default function PeopleForm(props: Props) {
  const {
    kind,
    mode,
    person,
    countries,
    programs,
    timezones,
    employmentTypes,
    contractBases,
    currencies,
    optionsUrl,
    usernameUrl,
    submitUrl,
    backUrl,
  } = props;
  const t = useI18n();
  const isStudent = kind === "students";
  const creating = mode === "create";
  const accountReadOnly = !creating && !props.canUpdateAccount;
  const initial: Data = {
    account_mode: "new",
    existing_user_id: "",
    full_name: "",
    email: "",
    phone: "",
    username: "",
    password: "",
    password_confirmation: "",
    locale: "",
    timezone: "",
    date_of_birth: "",
    gender: "",
    country_id: "",
    region_id: "",
    nationality: "",
    city: "",
    preferred_language: "",
    notes: "",
    preferred_program_id: "",
    preferred_course_id: "",
    staff_code: "",
    employment_type: "",
    hired_at: "",
    bio: "",
    specializations: [],
    contract_basis: "",
    contract_effective_from: "",
    contract_effective_to: "",
    currency: "",
    base_amount_major: "",
    default_rate_major: "",
    monthly_target_sessions: "",
    target_admin_tasks: "",
    target_training_sessions: "",
    course_ids: [],
    qualification_notes: "",
    teaching_staff_profile_id: "",
    teaching_duration_minutes: String(props.teaching?.durations[0] ?? ""),
    teaching_weekday: "",
    teaching_start_time: "",
    teaching_starts_on: props.teaching?.startsOn ?? "",
    ...Object.fromEntries(
      Object.entries(person).map(([key, value]) => [key, value ?? ""]),
    ),
  };
  const {
    data,
    setData,
    post,
    put,
    processing,
    errors,
    setError,
    clearErrors,
    reset,
  } = useForm<Data>(initial);
  const fieldErrors = errors as Partial<Record<string, string>>;
  const [regions, setRegions] = useState<Choice[]>([]);
  const [courses, setCourses] = useState<Choice[]>(props.courses);
  const [teachers, setTeachers] = useState<Choice[]>([]);
  const [teachersLoading, setTeachersLoading] = useState(false);
  const [accounts, setAccounts] = useState<Choice[]>([]);
  const [accountSearch, setAccountSearch] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [suggesting, setSuggesting] = useState(false);
  const [optionsLoading, setOptionsLoading] = useState(false);
  const [lookupError, setLookupError] = useState("");
  const [copyFeedback, setCopyFeedback] = useState("");
  const summaryRef = useRef<HTMLDivElement>(null);
  const usernameEdited = useRef(Boolean(person.username));

  useEffect(() => {
    const controller = new AbortController();
    const query = new URLSearchParams();
    if (data.country_id) query.set("country_id", data.country_id);
    if (data.preferred_program_id)
      query.set("program_id", data.preferred_program_id);
    setOptionsLoading(true);
    setLookupError("");
    fetch(optionsUrl + "?" + query, {
      signal: controller.signal,
      headers: { Accept: "application/json" },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error();
        return (await response.json()) as Options;
      })
      .then((result) => {
        setRegions(result.regions);
        if (
          result.regions.length === 1 &&
          result.regions[0]?.code === "UNSPECIFIED"
        ) {
          setData("region_id", result.regions[0].value);
        }
        if (isStudent) setCourses(result.courses);
      })
      .catch((error) => {
        if (error.name !== "AbortError")
          setLookupError(t("console_people.lookup_error"));
      })
      .finally(() => {
        if (!controller.signal.aborted) setOptionsLoading(false);
      });
    return () => controller.abort();
  }, [
    data.country_id,
    data.preferred_program_id,
    optionsUrl,
    isStudent,
    t,
    setData,
  ]);

  /*
   * معلمو الكورس المختار. الطلب يُلغى عند تبديل الكورس، والقائمة تُفرَّغ فورًا
   * حتى لا يبقى اختيار معلم لا يُدرّس الكورس الجديد.
   */
  const teachingEnabled = Boolean(creating && isStudent && props.teaching);
  useEffect(() => {
    if (!teachingEnabled) return;
    setData("teaching_staff_profile_id", "");
    setTeachers([]);
    if (!data.preferred_course_id) return;
    const controller = new AbortController();
    setTeachersLoading(true);
    fetch(
      optionsUrl +
        "?" +
        new URLSearchParams({ course_id: data.preferred_course_id }),
      { signal: controller.signal, headers: { Accept: "application/json" } },
    )
      .then(async (response) => {
        if (!response.ok) throw new Error();
        return (await response.json()) as Options;
      })
      .then((result) => setTeachers(result.teachers ?? []))
      .catch((error) => {
        if (error.name !== "AbortError")
          setLookupError(t("console_people.lookup_error"));
      })
      .finally(() => {
        if (!controller.signal.aborted) setTeachersLoading(false);
      });
    return () => controller.abort();
  }, [data.preferred_course_id, optionsUrl, teachingEnabled, t, setData]);

  async function suggestUsername(force = false) {
    if (
      !creating ||
      !data.full_name.trim() ||
      (usernameEdited.current && !force)
    )
      return;
    setSuggesting(true);
    clearErrors("username");
    try {
      const response = await fetch(
        usernameUrl + "?" + new URLSearchParams({ name: data.full_name }),
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok) throw new Error();
      const result = (await response.json()) as { suggestions: string[] };
      if ((force || !usernameEdited.current) && result.suggestions[0])
        setData("username", result.suggestions[0]);
    } catch {
      setError("username", t("console_people.username_error"));
    } finally {
      setSuggesting(false);
    }
  }
  async function findAccount() {
    setLookupError("");
    try {
      const response = await fetch(
        optionsUrl + "?" + new URLSearchParams({ search: accountSearch }),
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok) throw new Error();
      const result = (await response.json()) as Options;
      setAccounts(result.accounts);
    } catch {
      setLookupError(t("console_people.lookup_error"));
    }
  }
  function generatePassword() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    const generated =
      "Aa1!" +
      Array.from(bytes, (value) => value.toString(16).padStart(2, "0")).join(
        "",
      );
    setData((values) => ({
      ...values,
      password: generated,
      password_confirmation: generated,
    }));
    setShowPassword(true);
  }
  function submit(event: FormEvent) {
    event.preventDefault();
    const options = {
      preserveScroll: true,
      onError: () => {
        requestAnimationFrame(() => summaryRef.current?.focus());
      },
      onSuccess: () => reset("password", "password_confirmation"),
    };
    if (creating) post(submitUrl, options);
    else put(submitUrl, options);
  }
  function input(
    name: keyof Data,
    type = "text",
    optional = false,
    hint?: string,
  ) {
    return (
      <Field
        name={name}
        label={t("console_people.fields." + name)}
        error={fieldErrors[name]}
        optional={optional}
        hint={hint}
      >
        <input
          id={name}
          readOnly={accountReadOnly && name === "phone"}
          type={type}
          step={
            type === "number"
              ? name.endsWith("_major")
                ? "0.01"
                : "1"
              : undefined
          }
          value={String(data[name])}
          className={fieldClass}
          onChange={(event) => setData(name, event.target.value)}
          aria-invalid={Boolean(fieldErrors[name])}
          aria-describedby={
            fieldErrors[name]
              ? name + "-error"
              : hint
                ? name + "-hint"
                : undefined
          }
          autoComplete={
            name === "full_name"
              ? "name"
              : name === "phone"
                ? "tel"
                : name === "email"
                  ? "email"
                  : undefined
          }
        />
      </Field>
    );
  }
  function select(
    name: keyof Data,
    values: Choice[],
    optional = false,
    onChange?: (value: string) => void,
  ) {
    return (
      <Field
        name={name}
        label={t("console_people.fields." + name)}
        error={fieldErrors[name]}
        optional={optional}
      >
        <select
          id={name}
          value={String(data[name])}
          className={fieldClass}
          onChange={(event) => {
            setData(name, event.target.value);
            onChange?.(event.target.value);
          }}
          aria-invalid={Boolean(fieldErrors[name])}
          aria-describedby={fieldErrors[name] ? name + "-error" : undefined}
        >
          <option value="">{t("console_people.choose")}</option>
          {values.map((value) => (
            <option key={value.value} value={value.value}>
              {value.label}
            </option>
          ))}
        </select>
      </Field>
    );
  }
  function textarea(name: keyof Data, max: number) {
    return (
      <Field
        name={name}
        label={t("console_people.fields." + name)}
        error={fieldErrors[name]}
        optional
      >
        <textarea
          id={name}
          value={String(data[name])}
          onChange={(event) => setData(name, event.target.value)}
          rows={3}
          maxLength={max}
          className={fieldClass}
          aria-invalid={Boolean(fieldErrors[name])}
          aria-describedby={fieldErrors[name] ? name + "-error" : undefined}
        />
        <p className="text-end text-xs tabular-nums text-slate-400">
          {String(data[name]).length} / {max}
        </p>
      </Field>
    );
  }
  const title = t("console_people." + (creating ? "add_" : "edit_") + kind);
  return (
    <ConsoleLayout
      title={title}
      section="records"
      description={t("console_people.form_description")}
      actions={
        <Link href={backUrl} className={secondaryClass}>
          {t("console_people.back")}
        </Link>
      }
    >
      <Head title={title} />
      <form onSubmit={submit} className="console-people-form">
        <div className="setup-layout">
          <div className="panel">
            <div className="registration-mode">
              <div
                className="filters"
                aria-label={t("console_people.person_kind")}
              >
                {creating ? (
                  props.personKinds?.map((choice) => (
                    <Link
                      key={choice.kind}
                      href={choice.url}
                      className={
                        "pill" + (kind === choice.kind ? " current" : "")
                      }
                      aria-current={kind === choice.kind ? "page" : undefined}
                    >
                      {choice.label}
                    </Link>
                  ))
                ) : (
                  <span className="pill current">
                    {t("console_people." + (isStudent ? "student" : "teacher"))}
                  </span>
                )}
              </div>
              <span className="cell-sub">
                {t(
                  "console_people." +
                    (creating ? "new_registration" : "edit_profile"),
                )}
              </span>
            </div>
            {Object.keys(fieldErrors).length > 0 && (
              <div
                ref={summaryRef}
                tabIndex={-1}
                role="alert"
                className="rounded-xl border border-red-200 bg-red-50 p-5 outline-none"
              >
                <h2 className="font-semibold text-red-900">
                  {t("console_people.check_fields")}
                </h2>
                <ul className="mt-2 space-y-1 text-sm text-red-800">
                  {Object.entries(fieldErrors).map(([key, error]) => (
                    <li key={key}>
                      {key === "form" ? (
                        error
                      ) : (
                        <a href={"#" + key} className="underline">
                          {error}
                        </a>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            )}
            {lookupError && (
              <p
                role="alert"
                className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900"
              >
                {lookupError}
              </p>
            )}
            <Section
              id="account"
              number="01"
              title={t("console_people.account_section")}
              description={t(
                "console_people." +
                  (creating
                    ? "account_intro"
                    : accountReadOnly
                      ? "account_read_only"
                      : "account_edit_intro"),
              )}
            >
              {creating && (
                <fieldset className="span2">
                  <legend className="mb-3 text-sm font-semibold text-slate-700">
                    {t("console_people.fields.account_mode")}
                  </legend>
                  <div className="flex flex-wrap gap-5">
                    {["new", "existing"].map((value) => (
                      <label
                        key={value}
                        className="flex items-center gap-2 text-sm"
                      >
                        <input
                          type="radio"
                          name="account_mode"
                          value={value}
                          checked={data.account_mode === value}
                          onChange={() => setData("account_mode", value)}
                          className="text-teal-800 focus:ring-teal-700"
                        />
                        {t("console_people.account_" + value)}
                      </label>
                    ))}
                  </div>
                </fieldset>
              )}
              {creating && data.account_mode === "existing" ? (
                <div className="span2 space-y-4 rounded-lg bg-slate-50 p-4">
                  <p className="text-sm leading-7 text-slate-600">
                    {t("console_people.existing_hint")}
                  </p>
                  <div>
                    <label
                      htmlFor="account-search"
                      className="mb-2 block text-sm font-medium"
                    >
                      {t("console_people.search_account")}
                    </label>
                    <div className="flex gap-2">
                      <input
                        id="account-search"
                        value={accountSearch}
                        onChange={(event) =>
                          setAccountSearch(event.target.value)
                        }
                        className={fieldClass}
                      />
                      <button
                        type="button"
                        onClick={() => void findAccount()}
                        className={secondaryClass}
                      >
                        {t("console_people.search")}
                      </button>
                    </div>
                  </div>
                  {select("existing_user_id", accounts)}
                </div>
              ) : (
                <>
                  <Field
                    name="full_name"
                    label={t("console_people.fields.full_name")}
                    error={errors.full_name}
                  >
                    <input
                      id="full_name"
                      readOnly={accountReadOnly}
                      value={data.full_name}
                      onChange={(event) =>
                        setData("full_name", event.target.value)
                      }
                      onBlur={() => void suggestUsername()}
                      autoComplete="name"
                      className={fieldClass}
                      aria-invalid={Boolean(errors.full_name)}
                      aria-describedby={
                        errors.full_name ? "full_name-error" : undefined
                      }
                    />
                  </Field>
                  {creating ? (
                    input(
                      "email",
                      "email",
                      true,
                      t("console_people.contact_hint"),
                    )
                  ) : (
                    <div className="rounded-xl bg-slate-50 p-4 text-sm">
                      <p className="text-slate-500">
                        {t("console_people.fields.email")}
                      </p>
                      <bdi className="mt-1 block">{person.email || "—"}</bdi>
                    </div>
                  )}
                  {input(
                    "phone",
                    "tel",
                    true,
                    creating ? t("console_people.contact_hint") : undefined,
                  )}
                  {creating ? (
                    <Field
                      name="username"
                      label={t("console_people.fields.username")}
                      error={errors.username}
                      hint={t("console_people.username_hint")}
                    >
                      <div className="flex gap-2">
                        <input
                          id="username"
                          dir="ltr"
                          value={data.username}
                          onChange={(event) => {
                            usernameEdited.current = true;
                            setData("username", event.target.value);
                          }}
                          className={fieldClass}
                          autoComplete="off"
                          aria-invalid={Boolean(errors.username)}
                          aria-describedby={
                            errors.username ? "username-error" : "username-hint"
                          }
                        />
                        <button
                          type="button"
                          disabled={suggesting || !data.full_name.trim()}
                          onClick={() => void suggestUsername(true)}
                          className={secondaryClass}
                        >
                          {t(
                            "console_people." +
                              (suggesting ? "loading" : "suggest"),
                          )}
                        </button>
                      </div>
                    </Field>
                  ) : (
                    <div className="rounded-xl bg-slate-50 p-4 text-sm">
                      <p className="text-slate-500">
                        {t("console_people.fields.username")}
                      </p>
                      <bdi className="mt-1 block">{person.username || "—"}</bdi>
                    </div>
                  )}
                  {creating && (
                    <div className="span2 space-y-4 rounded-lg border border-slate-200 p-4">
                      <Field
                        name="password"
                        label={t("console_people.fields.password")}
                        error={errors.password}
                        hint={t("console_people.password_hint")}
                      >
                        <input
                          id="password"
                          type={showPassword ? "text" : "password"}
                          value={data.password}
                          onChange={(event) =>
                            setData("password", event.target.value)
                          }
                          autoComplete="new-password"
                          dir="ltr"
                          className={fieldClass}
                          aria-invalid={Boolean(errors.password)}
                          aria-describedby={
                            errors.password ? "password-error" : "password-hint"
                          }
                        />
                      </Field>
                      <div className="flex flex-wrap gap-3">
                        <button
                          type="button"
                          onClick={generatePassword}
                          className={secondaryClass}
                        >
                          {t("console_people.generate_password")}
                        </button>
                        <button
                          type="button"
                          disabled={!data.password}
                          className={secondaryClass}
                          onClick={async () => {
                            try {
                              await navigator.clipboard.writeText(
                                data.password,
                              );
                              setCopyFeedback(
                                t("console_people.password_copied"),
                              );
                            } catch {
                              setCopyFeedback(
                                t("console_people.password_copy_failed"),
                              );
                            }
                          }}
                        >
                          {t("console_people.copy_password")}
                        </button>
                        <span role="status" className="cell-sub">
                          {copyFeedback}
                        </span>
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          className="text-sm text-teal-800"
                        >
                          {t(
                            "console_people." +
                              (showPassword
                                ? "hide_password"
                                : "show_password"),
                          )}
                        </button>
                      </div>
                      <Field
                        name="password_confirmation"
                        label={t("console_people.fields.password_confirmation")}
                        error={errors.password_confirmation}
                      >
                        <input
                          id="password_confirmation"
                          type={showPassword ? "text" : "password"}
                          value={data.password_confirmation}
                          onChange={(event) =>
                            setData("password_confirmation", event.target.value)
                          }
                          autoComplete="new-password"
                          dir="ltr"
                          className={fieldClass}
                          aria-describedby={
                            errors.password_confirmation
                              ? "password_confirmation-error"
                              : undefined
                          }
                        />
                      </Field>
                    </div>
                  )}
                </>
              )}
            </Section>
            <Section
              id="profile"
              number="02"
              title={t("console_people.profile_section")}
              description={t("console_people.profile_intro")}
            >
              {select(
                "gender",
                [
                  { value: "male", label: t("console_people.male") },
                  { value: "female", label: t("console_people.female") },
                ],
                !creating,
              )}
              {input("date_of_birth", "date", !isStudent || !creating)}
              <Field
                name="country_id"
                label={t("console_people.fields.country_id")}
                error={errors.country_id}
                hint={t("console_people.country_hint")}
              >
                <CountryInput
                  id="country_id"
                  className={fieldClass}
                  options={countries}
                  value={data.country_id}
                  required
                  aria-invalid={Boolean(errors.country_id)}
                  aria-describedby={
                    errors.country_id ? "country_id-error" : "country_id-hint"
                  }
                  onChange={(value) => {
                    setData("country_id", value);
                    setData("region_id", "");
                    if (creating) {
                      const zones =
                        countries.find((country) => country.value === value)
                          ?.timezones ?? [];
                      setData(
                        "timezone",
                        zones.length === 1 ? (zones[0] ?? "") : "",
                      );
                    }
                  }}
                />
              </Field>
              {select("region_id", regions)}
              {optionsLoading && (
                <p role="status" className="text-xs text-slate-500">
                  {t("console_people.loading")}
                </p>
              )}
              <Field
                name="timezone"
                label={t("console_people.fields.timezone")}
                error={errors.timezone}
                hint={t("console_people.timezone_hint")}
              >
                <input
                  id="timezone"
                  readOnly={accountReadOnly}
                  value={data.timezone}
                  onChange={(event) => setData("timezone", event.target.value)}
                  list="people-timezones"
                  dir="ltr"
                  className={fieldClass}
                  aria-invalid={Boolean(errors.timezone)}
                  aria-describedby={
                    errors.timezone ? "timezone-error" : "timezone-hint"
                  }
                />
                <datalist id="people-timezones">
                  {timezones.map((zone) => (
                    <option key={zone} value={zone} />
                  ))}
                </datalist>
              </Field>
              {isStudent ? (
                <>
                  {input("city", "text", true)}
                  {select(
                    "nationality",
                    countries.map((country) => ({
                      value: country.iso2 ?? "",
                      label: country.label,
                    })),
                    true,
                  )}
                  {textarea("notes", 5000)}
                </>
              ) : (
                <>
                  {input("staff_code")}
                  {select("employment_type", employmentTypes)}
                  {input("hired_at", "date", !creating)}
                  <Field
                    name="specializations"
                    label={t("console_people.fields.specializations")}
                    optional
                    error={errors.specializations}
                  >
                    <input
                      id="specializations"
                      value={data.specializations.join(", ")}
                      onChange={(event) =>
                        setData(
                          "specializations",
                          event.target.value
                            .split(/[,،]/)
                            .map((value) => value.trimStart()),
                        )
                      }
                      className={fieldClass}
                    />
                    <p className="text-xs text-slate-500">
                      {t("console_people.specializations_hint")}
                    </p>
                  </Field>
                  {textarea("bio", 5000)}
                </>
              )}
            </Section>
            {creating &&
              (isStudent ? (
                <Section
                  id="study"
                  number="03"
                  title={t("console_people.study_section")}
                  description={t("console_people.study_intro")}
                >
                  {select("preferred_program_id", programs, false, () =>
                    setData("preferred_course_id", ""),
                  )}
                  {select("preferred_course_id", courses)}
                  {programs.length === 0 && (
                    <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                      {t("console_people.no_programs")}
                    </p>
                  )}
                </Section>
              ) : null)}
            {creating && isStudent && props.teaching && (
              <Section
                id="teaching"
                number="04"
                title={t("console_people.teaching_section")}
                description={t("console_people.teaching_intro")}
              >
                <Field
                  name="teaching_staff_profile_id"
                  label={t("console_people.fields.teaching_staff_profile_id")}
                  optional
                  error={fieldErrors.teaching_staff_profile_id}
                  hint={t("console_people.teaching_slot_hint")}
                >
                  <select
                    id="teaching_staff_profile_id"
                    value={data.teaching_staff_profile_id}
                    className={fieldClass}
                    disabled={!data.preferred_course_id || teachersLoading}
                    onChange={(event) =>
                      setData("teaching_staff_profile_id", event.target.value)
                    }
                    aria-invalid={Boolean(fieldErrors.teaching_staff_profile_id)}
                    aria-describedby={
                      fieldErrors.teaching_staff_profile_id
                        ? "teaching_staff_profile_id-error"
                        : "teaching_staff_profile_id-hint"
                    }
                  >
                    <option value="">
                      {teachersLoading
                        ? t("console_people.loading")
                        : t("console_people.choose")}
                    </option>
                    {teachers.map((teacher) => (
                      <option key={teacher.value} value={teacher.value}>
                        {teacher.label}
                      </option>
                    ))}
                  </select>
                </Field>
                {data.preferred_course_id &&
                  !teachersLoading &&
                  teachers.length === 0 && (
                    <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                      {t("console_people.teaching_no_teachers")}
                    </p>
                  )}
                {data.teaching_staff_profile_id !== "" && (
                  <>
                    <Field
                      name="teaching_duration_minutes"
                      label={t(
                        "console_people.fields.teaching_duration_minutes",
                      )}
                      error={fieldErrors.teaching_duration_minutes}
                    >
                      <select
                        id="teaching_duration_minutes"
                        value={data.teaching_duration_minutes}
                        className={fieldClass}
                        onChange={(event) =>
                          setData(
                            "teaching_duration_minutes",
                            event.target.value,
                          )
                        }
                        aria-invalid={Boolean(
                          fieldErrors.teaching_duration_minutes,
                        )}
                      >
                        {props.teaching.durations.map((duration) => (
                          <option key={duration} value={String(duration)}>
                            {duration}
                          </option>
                        ))}
                      </select>
                    </Field>
                    <Field
                      name="teaching_weekday"
                      label={t("console_people.fields.teaching_weekday")}
                      optional
                      error={fieldErrors.teaching_weekday}
                      hint={t("console_people.teaching_timezone_hint").replace(
                        ":timezone",
                        props.teaching.timezone,
                      )}
                    >
                      <select
                        id="teaching_weekday"
                        value={data.teaching_weekday}
                        className={fieldClass}
                        onChange={(event) =>
                          setData("teaching_weekday", event.target.value)
                        }
                        aria-invalid={Boolean(fieldErrors.teaching_weekday)}
                        aria-describedby={
                          fieldErrors.teaching_weekday
                            ? "teaching_weekday-error"
                            : "teaching_weekday-hint"
                        }
                      >
                        <option value="">{t("console_people.choose")}</option>
                        {[0, 1, 2, 3, 4, 5, 6].map((weekday) => (
                          <option key={weekday} value={String(weekday)}>
                            {t("console_people.teaching.weekday_" + weekday)}
                          </option>
                        ))}
                      </select>
                    </Field>
                    {input("teaching_start_time", "time", true)}
                    {input("teaching_starts_on", "date", true)}
                  </>
                )}
              </Section>
            )}
            {creating &&
              (isStudent ? null : (
                <>
                  <Section
                    id="study"
                    number="03"
                    title={t("console_people.contract_section")}
                    description={t("console_people.contract_intro")}
                  >
                    {select("contract_basis", contractBases, false, (value) => {
                      if (value === "salary") setData("default_rate_major", "");
                      if (value === "per_session")
                        setData("base_amount_major", "");
                    })}
                    {select(
                      "currency",
                      currencies.map((value) => ({ value, label: value })),
                    )}
                    {input("contract_effective_from", "date")}
                    {input("contract_effective_to", "date", true)}
                    {data.contract_basis !== "per_session" &&
                      input("base_amount_major", "number")}
                    {data.contract_basis !== "salary" &&
                      input("default_rate_major", "number")}
                    {input("monthly_target_sessions", "number", true)}
                    {input("target_admin_tasks", "number", true)}
                    {input("target_training_sessions", "number", true)}
                  </Section>
                  <Section
                    id="qualifications"
                    number="04"
                    title={t("console_people.qualifications_section")}
                    description={t("console_people.qualifications_intro")}
                  >
                    <fieldset className="span2">
                      <legend className="mb-3 text-sm font-semibold text-slate-700">
                        {t("console_people.fields.course_ids")}
                      </legend>
                      {courses.length === 0 ? (
                        <p className="text-sm text-slate-500">
                          {t("console_people.no_courses")}
                        </p>
                      ) : (
                        <div className="max-h-72 space-y-3 overflow-y-auto rounded-xl border border-slate-200 p-4">
                          {courses.map((course) => (
                            <label
                              key={course.value}
                              className="flex items-start gap-3 text-sm leading-6"
                            >
                              <input
                                type="checkbox"
                                checked={data.course_ids.includes(course.value)}
                                onChange={(event) =>
                                  setData(
                                    "course_ids",
                                    event.target.checked
                                      ? [...data.course_ids, course.value]
                                      : data.course_ids.filter(
                                          (value) => value !== course.value,
                                        ),
                                  )
                                }
                                className="mt-1 rounded text-teal-800 focus:ring-teal-700"
                              />
                              {course.label}
                            </label>
                          ))}
                        </div>
                      )}
                      {errors.course_ids && (
                        <p role="alert" className="mt-2 text-sm text-red-700">
                          {errors.course_ids}
                        </p>
                      )}
                    </fieldset>
                    {textarea("qualification_notes", 2000)}
                  </Section>
                </>
              ))}
            <div className="savebar">
              <p className="max-w-md text-xs leading-6 text-slate-500">
                {t("console_people.audit_hint")}
              </p>
              <button
                type="submit"
                disabled={processing}
                className={primaryClass}
              >
                {t(
                  "console_people." +
                    (processing
                      ? "saving"
                      : creating
                        ? "save_create"
                        : "save_changes"),
                )}
              </button>
            </div>
          </div>
          <aside className="panel summary">
            <div className="panel-head">
              <h2>{title}</h2>
            </div>
            <div className="summary-content">
              <dl aria-live="polite">
                {[
                  [
                    t("console_people.fields.full_name"),
                    data.account_mode === "existing"
                      ? accounts.find(
                          (account) => account.value === data.existing_user_id,
                        )?.label
                      : data.full_name,
                  ],
                  [
                    t("console_people.fields.username"),
                    data.account_mode === "existing"
                      ? t("console_people.account_existing")
                      : data.username,
                  ],
                  [t("console_people.fields.phone"), data.phone],
                  [
                    t("console_people.fields.country_id"),
                    countries.find(
                      (country) => country.value === data.country_id,
                    )?.label,
                  ],
                  [t("console_people.fields.timezone"), data.timezone],
                  ...(creating && isStudent
                    ? [
                        [
                          t("console_people.fields.preferred_course_id"),
                          courses.find(
                            (course) =>
                              course.value === data.preferred_course_id,
                          )?.label,
                        ],
                        ...(props.teaching
                          ? [
                              [
                                t(
                                  "console_people.fields.teaching_staff_profile_id",
                                ),
                                teachers.find(
                                  (teacher) =>
                                    teacher.value ===
                                    data.teaching_staff_profile_id,
                                )?.label,
                              ],
                            ]
                          : []),
                      ]
                    : []),
                  ...(creating && !isStudent
                    ? [
                        [
                          t("console_people.fields.contract_basis"),
                          contractBases.find(
                            (basis) => basis.value === data.contract_basis,
                          )?.label,
                        ],
                        [
                          t("console_people.fields.course_ids"),
                          String(data.course_ids.length),
                        ],
                      ]
                    : []),
                ].map(([label, value]) => (
                  <div className="definition" key={label}>
                    <dt>{label}</dt>
                    <dd>
                      <bdi>{value || t("console_people.not_entered")}</bdi>
                    </dd>
                  </div>
                ))}
              </dl>
              <h3 className="people-summary-heading">
                {t("console_people.form_sections")}
              </h3>
              <nav
                aria-label={t("console_people.form_sections")}
                className="people-section-links"
              >
                <a href="#account" className="text-teal-800">
                  01 · {t("console_people.account_section")}
                </a>
                <a href="#profile" className="text-teal-800">
                  02 · {t("console_people.profile_section")}
                </a>
                {creating && (
                  <a href="#study" className="text-teal-800">
                    03 ·{" "}
                    {t(
                      "console_people." +
                        (isStudent ? "study_section" : "contract_section"),
                    )}
                  </a>
                )}
                {creating && !isStudent && (
                  <a href="#qualifications" className="text-teal-800">
                    04 · {t("console_people.qualifications_section")}
                  </a>
                )}
                {creating && isStudent && props.teaching && (
                  <a href="#teaching" className="text-teal-800">
                    04 · {t("console_people.teaching_section")}
                  </a>
                )}
              </nav>
              <p className="summary-note">
                {t(
                  "console_people." +
                    (creating ? "create_explainer" : "edit_explainer"),
                )}
              </p>
            </div>
          </aside>
        </div>
      </form>
    </ConsoleLayout>
  );
}
