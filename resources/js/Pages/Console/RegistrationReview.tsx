import { Head, Link, useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";
import type {
  RegistrationApplication,
  RegistrationCourse,
  Option,
} from "./RegistrationTypes";

interface Account {
  id: string;
  name: string;
  username: string;
  phone: string | null;
  email: string | null;
}
export default function RegistrationReview({
  application,
  catalog,
  countries,
  accounts: initialAccounts,
  usernameSuggestions,
  timezone,
  passwordLength,
  placementUrl,
}: {
  application: RegistrationApplication;
  catalog: RegistrationCourse[];
  countries: Option[];
  accounts: Account[];
  usernameSuggestions: string[];
  timezone: string;
  passwordLength: number;
  canPlace: boolean;
  placementUrl: string | null;
}) {
  const t = useI18n();
  const [showPassword, setShowPassword] = useState(false);
  const [search, setSearch] = useState("");
  const [accounts, setAccounts] = useState<Option[]>(
    initialAccounts.map((account) => ({
      id: account.id,
      name: [account.name, account.username, account.phone, account.email]
        .filter(Boolean)
        .join(" · "),
    })),
  );
  const [lookupError, setLookupError] = useState("");
  const [looking, setLooking] = useState(false);
  const form = useForm({
    decision: "accept",
    account_mode: application.user_id ? "existing" : "new",
    existing_user_id: application.user_id ?? "",
    username: usernameSuggestions[0] ?? "",
    password: "",
    password_confirmation: "",
    timezone,
    identity_confirmed: false,
    rejection_category: "",
    note: "",
  });
  const generate = () => {
    const bytes = crypto.getRandomValues(
      new Uint8Array(Math.ceil(passwordLength / 2)),
    );
    const generated =
      "Aa1!" +
      Array.from(bytes, (value) => value.toString(16).padStart(2, "0"))
        .join("")
        .slice(0, Math.max(0, passwordLength - 4));
    form.setData((data) => ({
      ...data,
      password: generated,
      password_confirmation: generated,
    }));
    setShowPassword(true);
  };
  const findAccounts = async () => {
    setLooking(true);
    setLookupError("");
    try {
      const response = await fetch(
        "/manage/registration/accounts?" + new URLSearchParams({ search }),
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok) throw new Error();
      const result = (await response.json()) as {
        accounts: Record<string, string>;
      };
      setAccounts(
        Object.entries(result.accounts).map(([id, name]) => ({ id, name })),
      );
    } catch {
      setLookupError(t("console_registration.lookup_error"));
    } finally {
      setLooking(false);
    }
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    form.post(
      "/manage/registration/applications/" + application.id + "/decision",
      {
        preserveScroll: true,
        onSuccess: () => form.reset("password", "password_confirmation"),
      },
    );
  };
  const error = (key: string) => form.errors[key as keyof typeof form.errors];
  const feedback = (key: string) =>
    error(key) ? <small role="alert">{error(key)}</small> : null;
  const label = (key: string) => t("console_registration.fields." + key);
  const course = catalog.find(
    (item) => item.id === application.preferred_course_id,
  );
  return (
    <ConsoleLayout
      title={t("console_registration.review_request")}
      section="before"
      description={application.full_name}
      actions={
        <Link
          className="console-button"
          href={
            "/manage/registration?" +
            new URLSearchParams({
              stage: application.can_place ? "accepted" : "requests",
              ...(application.preferred_course_id
                ? { course: application.preferred_course_id }
                : {}),
            }).toString()
          }
          onBefore={() =>
            !form.isDirty || window.confirm(t("console_registration.discard"))
          }
        >
          {t("console_registration.back_center")}
        </Link>
      }
    >
      <Head
        title={
          t("console_registration.review_request") +
          " · " +
          application.full_name
        }
      />
      <div className="setup-layout">
        <div className="panel">
          <section className="form-section">
            <h2>{t("console_registration.application")}</h2>
            <dl className="field-grid">
              {[
                [label("full_name"), application.full_name],
                [label("date_of_birth"), application.date_of_birth],
                [
                  label("gender"),
                  t("console_registration.gender." + application.gender),
                ],
                [
                  label("country"),
                  countries.find((item) => item.id === application.country_id)
                    ?.name ?? t("console_registration.unspecified"),
                ],
                [label("phone"), application.phone],
                [label("email"), application.email],
                [t("console_registration.source"), application.source],
                [
                  label("course"),
                  course?.name ?? t("console_registration.general_form"),
                ],
                [label("notes"), application.notes],
              ].map(([key, value]) => (
                <div className="definition" key={key}>
                  <dt>{key}</dt>
                  <dd>
                    <bdi>{value || t("console_registration.unspecified")}</bdi>
                  </dd>
                </div>
              ))}
            </dl>
          </section>
          <section className="form-section">
            <h2>{t("console_registration.answers")}</h2>
            {application.answers.length === 0 ? (
              <p className="cell-sub">{t("console_registration.no_answers")}</p>
            ) : (
              <dl>
                {application.answers.map((answer) => (
                  <div className="definition" key={answer.question_id}>
                    <dt>{answer.question}</dt>
                    <dd>
                      {Array.isArray(answer.answer)
                        ? answer.answer.join("، ")
                        : answer.answer}
                    </dd>
                  </div>
                ))}
              </dl>
            )}
          </section>
          {application.can_decide ? (
            <form onSubmit={submit}>
              <section className="form-section">
                <h2>{t("console_registration.decision")}</h2>
                {Object.keys(form.errors).length > 0 && (
                  <div className="console-flash error" role="alert">
                    {Object.entries(form.errors).map(([key, message]) => (
                      <p key={key}>{message}</p>
                    ))}
                  </div>
                )}
                {application.duplicate_of_application_id && (
                  <div className="summary-note">
                    <p>{t("console_registration.duplicate_help")}</p>
                    <Link
                      className="inline-link"
                      href={
                        "/manage/registration/applications/" +
                        application.duplicate_of_application_id
                      }
                      target="_blank"
                    >
                      {t("console_registration.open_duplicate")}
                    </Link>
                  </div>
                )}
                <div className="field-grid">
                  <div className="field span2">
                    <label htmlFor="registration-decision">
                      {label("decision")}
                    </label>
                    <select
                      id="registration-decision"
                      className="console-control"
                      value={form.data.decision}
                      onChange={(event) =>
                        form.setData("decision", event.target.value)
                      }
                    >
                      <option value="accept">
                        {t("console_registration.accept")}
                      </option>
                      {application.can_review && (
                        <option value="review">
                          {t("console_registration.keep_review")}
                        </option>
                      )}
                      <option value="reject">
                        {t("console_registration.reject")}
                      </option>
                    </select>
                  </div>
                  {form.data.decision === "accept" && (
                    <>
                      <div className="field span2">
                        <label htmlFor="registration-account-mode">
                          {label("account_mode")}
                        </label>
                        <select
                          id="registration-account-mode"
                          className="console-control"
                          disabled={!!application.user_id}
                          value={form.data.account_mode}
                          onChange={(event) =>
                            form.setData("account_mode", event.target.value)
                          }
                        >
                          <option value="new">
                            {t("console_registration.new_account")}
                          </option>
                          <option value="existing">
                            {t("console_registration.existing_account")}
                          </option>
                        </select>
                      </div>
                      {form.data.account_mode === "existing" ? (
                        <div className="field span2">
                          <label htmlFor="registration-account-search">
                            {t("console_registration.find_account")}
                          </label>
                          <div className="input-action">
                            <input
                              id="registration-account-search"
                              className="console-control"
                              value={search}
                              onChange={(event) =>
                                setSearch(event.target.value)
                              }
                            />
                            <button
                              type="button"
                              className="console-button"
                              disabled={looking || !search.trim()}
                              onClick={findAccounts}
                            >
                              {t(
                                looking
                                  ? "console_registration.searching"
                                  : "console_registration.search",
                              )}
                            </button>
                          </div>
                          {lookupError && (
                            <small role="alert">{lookupError}</small>
                          )}
                          <label htmlFor="registration-account">
                            {label("account")}
                          </label>
                          <select
                            id="registration-account"
                            className="console-control"
                            required
                            disabled={!!application.user_id}
                            value={form.data.existing_user_id}
                            onChange={(event) =>
                              form.setData(
                                "existing_user_id",
                                event.target.value,
                              )
                            }
                          >
                            <option value="">
                              {t("console_registration.choose")}
                            </option>
                            {accounts.map((account) => (
                              <option value={account.id} key={account.id}>
                                {account.name}
                              </option>
                            ))}
                          </select>
                          <small>
                            {t("console_registration.account_help")}
                          </small>
                          {feedback("existing_user_id")}
                        </div>
                      ) : (
                        <>
                          <div className="field span2">
                            <label htmlFor="registration-username">
                              {label("username")}
                            </label>
                            <input
                              id="registration-username"
                              className="console-control"
                              dir="ltr"
                              autoComplete="off"
                              required
                              value={form.data.username}
                              onChange={(event) =>
                                form.setData("username", event.target.value)
                              }
                            />
                            {feedback("username")}
                            <div className="filters">
                              {usernameSuggestions.map((name) => (
                                <button
                                  type="button"
                                  className="pill"
                                  key={name}
                                  onClick={() => form.setData("username", name)}
                                >
                                  <bdi>{name}</bdi>
                                </button>
                              ))}
                            </div>
                          </div>
                          <div className="field span2">
                            <label htmlFor="registration-password">
                              {label("password")}
                            </label>
                            <div className="input-action">
                              <input
                                id="registration-password"
                                className="console-control"
                                dir="ltr"
                                autoComplete="new-password"
                                type={showPassword ? "text" : "password"}
                                required
                                value={form.data.password}
                                onChange={(event) =>
                                  form.setData((data) => ({
                                    ...data,
                                    password: event.target.value,
                                    password_confirmation: event.target.value,
                                  }))
                                }
                              />
                              <button
                                type="button"
                                className="console-button"
                                onClick={generate}
                              >
                                {t("console_registration.generate_password")}
                              </button>
                              <button
                                type="button"
                                className="console-button"
                                onClick={() => setShowPassword(!showPassword)}
                              >
                                {t(
                                  showPassword
                                    ? "console_registration.hide_password"
                                    : "console_registration.show_password",
                                )}
                              </button>
                            </div>
                            {feedback("password")}
                            <small>
                              {t("console_registration.password_help")}
                            </small>
                          </div>
                        </>
                      )}
                      <div className="field span2">
                        <label htmlFor="registration-timezone">
                          {label("timezone")}
                        </label>
                        <input
                          id="registration-timezone"
                          className="console-control"
                          dir="ltr"
                          required
                          value={form.data.timezone}
                          onChange={(event) =>
                            form.setData("timezone", event.target.value)
                          }
                        />
                        {feedback("timezone")}
                      </div>
                      {application.duplicate_of_application_id && (
                        <div className="field span2">
                          <label className="question-check">
                            <input
                              type="checkbox"
                              checked={form.data.identity_confirmed}
                              onChange={(event) =>
                                form.setData(
                                  "identity_confirmed",
                                  event.target.checked,
                                )
                              }
                            />
                            {t("console_registration.identity_confirmed")}
                          </label>
                          {feedback("identity_confirmed")}
                        </div>
                      )}
                    </>
                  )}
                  {form.data.decision === "reject" && (
                    <>
                      <div className="field">
                        <label htmlFor="registration-rejection">
                          {label("rejection_category")}
                        </label>
                        <select
                          id="registration-rejection"
                          className="console-control"
                          required
                          value={form.data.rejection_category}
                          onChange={(event) =>
                            form.setData(
                              "rejection_category",
                              event.target.value,
                            )
                          }
                        >
                          <option value="">
                            {t("console_registration.choose")}
                          </option>
                          {[
                            "eligibility",
                            "schedule",
                            "duplicate",
                            "other",
                          ].map((key) => (
                            <option value={key} key={key}>
                              {t("console_registration.rejections." + key)}
                            </option>
                          ))}
                        </select>
                        {feedback("rejection_category")}
                      </div>
                      <div className="field span2">
                        <label htmlFor="registration-note">
                          {label("note")}{" "}
                          {form.data.rejection_category !== "other" &&
                            "(" + t("console_registration.optional") + ")"}
                        </label>
                        <textarea
                          id="registration-note"
                          className="console-control"
                          required={form.data.rejection_category === "other"}
                          maxLength={1500}
                          value={form.data.note}
                          onChange={(event) =>
                            form.setData("note", event.target.value)
                          }
                        />
                        {feedback("note")}
                      </div>
                    </>
                  )}
                </div>
              </section>
              <footer className="savebar">
                <p>{t("console_registration.decision_help")}</p>
                <button
                  type="submit"
                  className="console-button primary"
                  disabled={form.processing}
                >
                  {t(
                    form.processing
                      ? "console_registration.saving"
                      : "console_registration.save_decision",
                  )}
                </button>
              </footer>
            </form>
          ) : (
            <section className="form-section">
              <h2>{t("console_registration.status")}</h2>
              <span
                className={
                  "console-status " + (application.can_place ? "success" : "")
                }
              >
                {t("console_registration.statuses." + application.status)}
              </span>
              {application.decision_reason && (
                <p className="cell-sub">{application.decision_reason}</p>
              )}
              {application.can_place && placementUrl && (
                <Link className="inline-link" href={placementUrl}>
                  {t("console_registration.placement_link")}
                </Link>
              )}
            </section>
          )}
        </div>
        <aside className="panel summary">
          <div className="panel-head">
            <h2>{t("console_registration.summary")}</h2>
          </div>
          <div className="summary-content">
            <dl>
              {[
                [
                  t("console_registration.status"),
                  t("console_registration.statuses." + application.status),
                ],
                [t("console_registration.source"), application.source],
                [
                  label("course"),
                  course?.name ?? t("console_registration.general_form"),
                ],
                [
                  t("console_registration.submitted"),
                  application.submitted_at ??
                    t("console_registration.unspecified"),
                ],
              ].map(([key, value]) => (
                <div className="definition" key={key}>
                  <dt>{key}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            <p className="summary-note">
              {t("console_registration.acceptance_help")}
            </p>
          </div>
        </aside>
      </div>
    </ConsoleLayout>
  );
}
