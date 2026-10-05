import { Link, useForm, usePage } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import LearningEntryLayout from "@/Layouts/LearningEntryLayout";
import Icon from "@/Pages/Learning/Icon";
import { Errors } from "@/Pages/Learning/shared";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";

export default function LearningLogin({
  action = "/login",
  portal = "student",
  portals,
  status,
}: {
  action?: string;
  portal?: string;
  portals?: Record<string, string>;
  status?: string;
}) {
  const t = useI18n();
  const { flash } = usePage<AppPageProps>().props;
  const [visible, setVisible] = useState(false);
  const form = useForm({ login: "", password: "", remember: false, portal });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(action, {
      preserveScroll: "errors",
      onFinish: () => form.reset("password"),
    });
  }
  return (
    <LearningEntryLayout title={t("auth.login.title")}>
      <h2 id="entry-title">{t("learning.entry.welcome")}</h2>
      <p>
        {t(
          portals
            ? "learning.entry.choose_authenticated"
            : "learning.entry.choose",
        )}
      </p>
      {status && (
        <p className="lp-feedback" role="status">
          {status}
        </p>
      )}
      {flash?.success && (
        <p className="lp-feedback" role="status">
          {flash.success}
        </p>
      )}
      {flash?.error && (
        <p className="lp-feedback lp-error" role="alert">
          {flash.error}
        </p>
      )}
      {portals ? (
        <>
          <div className="lp-role-picker">
            {Object.entries(portals).map(([kind, url]) => (
              <Link key={kind} href={url} className="lp-portal-choice">
                <Icon
                  name={
                    kind === "teacher"
                      ? "teacher"
                      : kind === "admin"
                        ? "calendar"
                        : "user"
                  }
                />
                <b>
                  {t(
                    kind === "admin"
                      ? "learning.entry.admin"
                      : `learning.${kind}_portal`,
                  )}
                </b>
                <small>{t("learning.entry.continue")}</small>
              </Link>
            ))}
          </div>
          {Object.keys(portals).length === 0 && (
            <p className="lp-feedback">{t("learning.entry.no_portal")}</p>
          )}
          <Link
            href="/logout"
            method="post"
            as="button"
            className="lp-recovery"
          >
            {t("learning.logout")}
          </Link>
        </>
      ) : (
        <form className="lp-login-form" onSubmit={submit}>
          <div
            className="lp-role-picker"
            role="group"
            aria-label={t("learning.entry.portal_type")}
          >
            {["student", "teacher"].map((kind) => (
              <button
                key={kind}
                type="button"
                aria-pressed={form.data.portal === kind}
                onClick={() => form.setData("portal", kind)}
              >
                <Icon name={kind === "teacher" ? "teacher" : "user"} />
                <b>{t(`learning.${kind}_portal`)}</b>
                <small>{t(`learning.entry.${kind}_hint`)}</small>
              </button>
            ))}
          </div>
          <Errors errors={form.errors} />
          <label className="lp-field" htmlFor="login">
            <span>{t("auth.login.identifier")}</span>
            <input
              autoComplete="username"
              autoFocus
              id="login"
              name="login"
              required
              dir="ltr"
              value={form.data.login}
              onChange={(e) => form.setData("login", e.target.value)}
              aria-invalid={Boolean(form.errors.login)}
              aria-describedby="login-hint"
            />
            <small id="login-hint">{t("learning.entry.identifier_hint")}</small>
          </label>
          <label className="lp-field" htmlFor="password">
            <span>{t("auth.login.password")}</span>
            <span className="lp-password-wrap">
              <input
                autoComplete="current-password"
                id="password"
                name="password"
                required
                type={visible ? "text" : "password"}
                dir="ltr"
                value={form.data.password}
                onChange={(e) => form.setData("password", e.target.value)}
                aria-invalid={Boolean(form.errors.password)}
              />
              <button
                type="button"
                onClick={() => setVisible(!visible)}
                aria-pressed={visible}
                aria-label={t(
                  visible
                    ? "learning.entry.hide_password"
                    : "learning.entry.show_password",
                )}
              >
                <Icon name="eye" />
              </button>
            </span>
          </label>
          <label className="lp-remember">
            <input
              type="checkbox"
              checked={form.data.remember}
              onChange={(e) => form.setData("remember", e.target.checked)}
            />
            {t("auth.login.remember")}
          </label>
          <button className="lp-btn" disabled={form.processing} type="submit">
            {t(form.processing ? "actions.processing" : "auth.login.submit")}
            <Icon name="arrow" size={17} />
          </button>
          <Link className="lp-recovery" href="/forgot-password">
            {t("learning.entry.recover")}
          </Link>
        </form>
      )}
    </LearningEntryLayout>
  );
}
