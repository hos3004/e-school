import { Link, useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import LearningLayout, { type LearningKind } from "@/Layouts/LearningLayout";
import ProfileView, {
  type ProfileHub,
  type ProfileWorkspace,
} from "@/Components/Console/ProfileView";
import { useI18n } from "@/lib/i18n";
import AccountEditor from "./AccountEditor";
import { Errors } from "./shared";
interface Account {
  name: string;
  email: string;
  username: string | null;
  phone: string | null;
  phoneCountry: string | null;
  timezone: string;
  status: string;
}
interface ProfileData {
  id: string;
  name: string;
  code: string;
  status?: string;
  statusTone?: string;
  email?: string;
  phone?: string;
  city?: string;
  country?: string;
  dateOfBirth?: string;
  bio?: Record<string, string>;
  specializations?: string[];
  timezone?: string;
  joinedAt?: string | null;
  avatarUrl?: string | null;
}
interface Props {
  kind: LearningKind;
  timezone: string;
  profile: ProfileData;
  own: boolean;
  returnUrl?: string;
  account: Account | null;
  timezones: string[];
  updateUrl: string | null;
  passwordUrl: string | null;
  availabilityUrl?: string | null;
  hub: ProfileHub;
  profileWorkspace: ProfileWorkspace;
}
export default function Profile({
  kind,
  timezone,
  profile,
  own,
  returnUrl,
  account,
  timezones,
  updateUrl,
  passwordUrl,
  availabilityUrl,
  hub,
  profileWorkspace,
}: Props) {
  const t = useI18n();
  const [editing, setEditing] = useState(false);
  const [changingPassword, setChangingPassword] = useState(false);
  const form = useForm({
    name: account?.name ?? "",
    phone: account?.phone ?? "",
    phone_country: account?.phoneCountry ?? "",
    timezone: account?.timezone ?? timezone,
  });
  const password = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  function save(event: FormEvent) {
    event.preventDefault();
    if (updateUrl)
      form.patch(updateUrl, {
        preserveScroll: true,
        onSuccess: () => {
          form.setDefaults();
          setEditing(false);
        },
      });
  }
  function savePassword(event: FormEvent) {
    event.preventDefault();
    if (passwordUrl)
      password.put(passwordUrl, {
        preserveScroll: true,
        onSuccess: () => {
          password.reset();
          setChangingPassword(false);
        },
      });
  }
  return (
    <LearningLayout kind={kind} title={profile.name}>
      {own && (
        <Link href="/profile/complete" className="console-button mb-4">
          {t("profile_completion.title")}
        </Link>
      )}
      <ProfileView
        kind={own ? kind : "student"}
        audience={own ? "self" : "teacher"}
        person={{
          name: profile.name,
          code: profile.code,
          status: profile.status || t("console_profiles.assigned"),
          statusTone: profile.statusTone,
          email: profile.email,
          phone: profile.phone,
          username: account?.username,
          timezone: account?.timezone || profile.timezone,
          location: [profile.country, profile.city].filter(Boolean).join(" · "),
          bio: profile.bio?.ar,
          specializations: profile.specializations,
          birthDate: profile.dateOfBirth,
          joinedAt: profile.joinedAt,
          avatarUrl: profile.avatarUrl,
        }}
        hub={hub}
        workspace={profileWorkspace}
        timezone={timezone}
        availabilityUrl={own && kind === "teacher" ? availabilityUrl : null}
        backUrl={returnUrl ?? "/learn/" + kind}
        onEdit={own && updateUrl ? () => setEditing(true) : undefined}
        onPassword={
          own && passwordUrl ? () => setChangingPassword(true) : undefined
        }
      />
      {own && account && (
        <>
          <AccountEditor
            open={editing}
            title={t("learning.edit_account")}
            subtitle={t("learning.account_help")}
            busy={form.processing}
            onDismiss={() => setEditing(false)}
          >
            <form onSubmit={save} className="learning-form learning-no-print">
              <Errors errors={form.errors} />
              <label>
                {t("learning.name")}
                <input
                  required
                  autoComplete="name"
                  data-editor-focus
                  aria-invalid={!!form.errors.name}
                  aria-describedby={
                    form.errors.name ? "account-name-error" : undefined
                  }
                  value={form.data.name}
                  onChange={(event) => form.setData("name", event.target.value)}
                />
                {form.errors.name && (
                  <span
                    className="learning-field-error"
                    id="account-name-error"
                  >
                    {form.errors.name}
                  </span>
                )}
              </label>
              <label>
                {t("learning.phone")}
                <input
                  type="tel"
                  aria-invalid={!!form.errors.phone}
                  aria-describedby={
                    form.errors.phone ? "account-phone-error" : undefined
                  }
                  autoComplete="tel"
                  value={form.data.phone}
                  onChange={(event) =>
                    form.setData("phone", event.target.value)
                  }
                />
                {form.errors.phone && (
                  <span
                    className="learning-field-error"
                    id="account-phone-error"
                  >
                    {form.errors.phone}
                  </span>
                )}
              </label>
              <label>
                {t("learning.timezone")}
                <select
                  dir="ltr"
                  aria-invalid={!!form.errors.timezone}
                  aria-describedby="account-timezone-help account-timezone-error"
                  value={form.data.timezone}
                  onChange={(event) =>
                    form.setData("timezone", event.target.value)
                  }
                >
                  {timezones.map((zone) => (
                    <option key={zone} value={zone}>
                      {zone}
                    </option>
                  ))}
                </select>
              </label>
              <button
                type="button"
                className="learning-text"
                onClick={() =>
                  form.setData(
                    "timezone",
                    Intl.DateTimeFormat().resolvedOptions().timeZone,
                  )
                }
              >
                {t("learning.detect_timezone")}
              </button>
              <p className="learning-help" id="account-timezone-help">
                {t("learning.timezone_help")}
              </p>
              <span
                className="learning-field-error"
                id="account-timezone-error"
              >
                {form.errors.timezone}
              </span>
              <div className="learning-actions learning-editor-footer">
                <button
                  disabled={form.processing}
                  type="submit"
                  className="learning-button"
                >
                  {t(form.processing ? "learning.saving" : "learning.save")}
                </button>
                <button
                  type="button"
                  disabled={form.processing}
                  className="learning-button secondary"
                  onClick={() => {
                    setEditing(false);
                  }}
                >
                  {t("learning.cancel")}
                </button>
              </div>
            </form>
          </AccountEditor>
          <AccountEditor
            open={changingPassword}
            title={t("learning.change_password")}
            busy={password.processing}
            onDismiss={() => {
              password.reset();
              password.clearErrors();
              setChangingPassword(false);
            }}
          >
            <form
              className="learning-form learning-no-print"
              onSubmit={savePassword}
            >
              <Errors errors={password.errors} />
              {(
                [
                  "current_password",
                  "password",
                  "password_confirmation",
                ] as const
              ).map((field) => (
                <label key={field}>
                  {t(`learning.${field}`)}
                  <input
                    required
                    type="password"
                    data-editor-focus={
                      field === "current_password" || undefined
                    }
                    aria-invalid={!!password.errors[field]}
                    aria-describedby={
                      password.errors[field] ? `${field}-error` : undefined
                    }
                    autoComplete={
                      field === "current_password"
                        ? "current-password"
                        : "new-password"
                    }
                    value={password.data[field]}
                    onChange={(event) =>
                      password.setData(field, event.target.value)
                    }
                  />
                  {password.errors[field] && (
                    <span
                      className="learning-field-error"
                      id={`${field}-error`}
                    >
                      {password.errors[field]}
                    </span>
                  )}
                </label>
              ))}
              <div className="learning-actions learning-editor-footer">
                <button
                  className="learning-button"
                  disabled={password.processing}
                >
                  {t("learning.save")}
                </button>
                <button
                  className="learning-button secondary"
                  type="button"
                  disabled={password.processing}
                  onClick={() => {
                    password.reset();
                    password.clearErrors();
                    setChangingPassword(false);
                  }}
                >
                  {t("learning.cancel")}
                </button>
              </div>
            </form>
          </AccountEditor>
        </>
      )}
    </LearningLayout>
  );
}
