import { useForm, usePage } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import SessionPaySettings, { type SessionPayData } from "./SessionPaySettings";
import { useI18n } from "@/lib/i18n";
import { formatNumber } from "@/lib/console-format";
import type { AppPageProps } from "@/types";
import {
  AccountSettings,
  GreenApiSettings,
  CalendarSettings,
  NotificationSettings,
  WhatsappTemplateSettings,
  type SettingsEditorsProps,
} from "./SettingsEditors";

type School = {
  name: Record<string, string>;
  default_timezone: string;
  week_starts_on: string;
  version: string;
};
type Policy = {
  key: string;
  fields: {
    key: string;
    label: string;
    value: string | number | boolean | null;
  }[];
};
export default function Settings({
  school,
  timezones,
  canUpdate,
  policies,
  sessionPay,
  ...editors
}: {
  school: School;
  timezones: string[];
  canUpdate: boolean;
  policies: Policy[];
  sessionPay: SessionPayData | null;
} & SettingsEditorsProps) {
  const t = useI18n();
  const { locale = "ar" } = usePage<AppPageProps>().props;
  const [search, setSearch] = useState("");
  const [activeSection, setActiveSection] = useState("school");
  const initial = () => ({
    name: {
      ar: school.name.ar ?? "",
    },
    default_timezone: school.default_timezone,
    week_starts_on: school.week_starts_on,
    version: school.version,
  });
  const form = useForm(initial());
  function submit(event: FormEvent) {
    event.preventDefault();
    form.put("/manage/settings", {
      preserveScroll: true,
      onSuccess: (page) => {
        const saved = page.props.school as School;
        form.setData("version", saved.version);
        form.clearErrors();
      },
    });
  }
  function adoptLatest() {
    form.setData(initial());
    form.clearErrors();
  }
  const display = (value: Policy["fields"][0]["value"]) =>
    typeof value === "boolean"
      ? t(value ? "console.enabled" : "console.disabled")
      : typeof value === "number"
        ? formatNumber(value, locale)
        : (value ?? t("console.not_set"));
  const keys = [
    "school",
    ...(editors.settingsPermissions.calendars ||
    editors.settingsPermissions.holidays
      ? ["calendar"]
      : []),
    ...policies.map((item) => item.key),
  ];
  const matches = (labels: string[]) =>
    !search ||
    labels.join(" ").toLowerCase().includes(search.trim().toLowerCase());
  const schoolMatches = matches([
    t("console.settings.sections.school"),
    t("console.settings.name"),
    t("console.timezone"),
  ]);
  const calendarMatches = matches([
    t("console_settings.calendars"),
    t("console_settings.holiday_name"),
    ...editors.calendars.map((calendar) => calendar.name),
    ...editors.holidays.map((holiday) => holiday.name),
  ]);
  const matchingKeys = policies
    .filter((section) =>
      matches([
        t("console.settings.sections." + section.key),
        ...section.fields.map((field) => field.label),
        ...(section.key === "accounts"
          ? [
              t("console_settings.username_prefix"),
              t("console_settings.prefix_help"),
            ]
          : []),
        ...(section.key === "integrations" && editors.greenApi
          ? [
              t("console_settings.green_api.title"),
              t("console_settings.green_api.api_url"),
              t("console_settings.green_api.instance_id"),
            ]
          : []),
        ...(section.key === "notifications"
          ? [
              ...editors.notificationCategories.map((category) => category.label),
              t("console_settings.whatsapp_templates.title"),
              ...editors.whatsappTemplates.map((template) => template.label),
            ]
          : []),
      ]),
    )
    .map((section) => section.key);
  return (
    <ConsoleLayout
      section="settings"
      title={t("console.settings.title")}
      description={t("console.settings.description")}
    >
      <div className="console-settings-intro">
        <div>
          <strong>{t("console.settings.one_reference")}</strong>
          <p>{t("console.settings.intro_help")}</p>
        </div>
        <div className="console-field">
          <label htmlFor="settings-search">
            {t("console.settings.search")}
          </label>
          <input
            id="settings-search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            type="search"
          />
        </div>
      </div>
      <div className="console-settings-grid">
        <nav
          className="console-settings-index"
          aria-label={t("console.settings.index")}
        >
          {keys.map((key, index) => (
            <a
              key={key}
              href={"#settings-" + key}
              className={activeSection === key ? "is-active" : ""}
              aria-current={activeSection === key ? "location" : undefined}
              onClick={() => {
                setSearch("");
                setActiveSection(key);
              }}
            >
              <bdi>{String(index + 1).padStart(2, "0")}</bdi> ·{" "}
              {key === "calendar"
                ? t("console_settings.calendars")
                : t("console.settings.sections." + key)}
            </a>
          ))}
        </nav>
        <div>
          <section
            id="settings-school"
            hidden={!schoolMatches}
            className="console-panel console-settings-section"
          >
            <div className="console-panel-header">
              <div>
                <h2>{t("console.settings.sections.school")}</h2>
                <p>{t("console.settings.timezone_note")}</p>
              </div>
            </div>
            <form onSubmit={submit}>
              <div className="console-panel-body">
                <div className="console-fields">
                  <div className="console-field">
                    <label htmlFor="school-name-ar">
                      {t("console.settings.name")}
                    </label>
                    <input
                      id="school-name-ar"
                      value={form.data.name.ar}
                      required
                      disabled={!canUpdate}
                      onChange={(event) =>
                        form.setData("name", { ar: event.target.value })
                      }
                    />
                  </div>
                  <div className="console-field">
                    <label htmlFor="school-timezone">
                      {t("console.timezone")}
                    </label>
                    <select
                      id="school-timezone"
                      value={form.data.default_timezone}
                      disabled={!canUpdate}
                      onChange={(event) =>
                        form.setData("default_timezone", event.target.value)
                      }
                    >
                      {timezones.map((zone) => (
                        <option key={zone}>{zone}</option>
                      ))}
                    </select>
                    <small>{t("console.settings.timezone_help")}</small>
                  </div>
                  <div className="console-field">
                    <label htmlFor="school-week-start">
                      {t("console.settings.calendar_week_start")}
                    </label>
                    <select
                      id="school-week-start"
                      disabled={!canUpdate}
                      value={form.data.week_starts_on}
                      onChange={(event) =>
                        form.setData("week_starts_on", event.target.value)
                      }
                    >
                      {["saturday", "sunday", "monday"].map((day) => (
                        <option key={day} value={day}>
                          {t("console.days." + day)}
                        </option>
                      ))}
                    </select>
                    <small>{t("console.settings.calendar_read_only")}</small>
                  </div>
                </div>
              </div>
              {Object.keys(form.errors).length > 0 && (
                <div className="console-feedback is-error" role="alert">
                  {Object.values(form.errors).join(" · ")}
                  {form.errors.version && (
                    <div className="mt-3 space-y-3">
                      <p>
                        {t("console.settings.latest_values")}{" "}
                        {school.name[locale] || school.name.ar} ·{" "}
                        <bdi>{school.default_timezone}</bdi>
                      </p>
                      <button
                        type="button"
                        className="console-button"
                        onClick={adoptLatest}
                      >
                        {t("console.settings.use_latest")}
                      </button>
                    </div>
                  )}
                </div>
              )}
              <div className="console-settings-note">
                <p>{t("console.settings.policy_scope")}</p>
                {canUpdate && (
                  <button
                    className="console-button primary"
                    style={{ marginTop: 14 }}
                    disabled={form.processing}
                  >
                    {t(
                      form.processing
                        ? "console.saving"
                        : "console.settings.save",
                    )}
                  </button>
                )}
              </div>
            </form>
          </section>
          {(editors.settingsPermissions.calendars ||
            editors.settingsPermissions.holidays) && (
            <div hidden={!calendarMatches}>
              <CalendarSettings {...editors} />
            </div>
          )}
          {policies.map((section) => (
            <section
              id={"settings-" + section.key}
              key={section.key}
              hidden={!matchingKeys.includes(section.key)}
              className="console-panel console-settings-section"
            >
              <div className="console-panel-header">
                <h2>{t("console.settings.sections." + section.key)}</h2>
                <span className="console-status">
                  {t("console.settings.read_policy")}
                </span>
              </div>
              {section.key === "dues" && sessionPay && (
                <SessionPaySettings
                  key={sessionPay.version}
                  data={sessionPay}
                />
              )}
              {section.key === "accounts" && editors.accounts && (
                <AccountSettings
                  key={editors.accounts.version}
                  accounts={editors.accounts}
                />
              )}
              {section.key === "notifications" &&
                editors.settingsPermissions.notifications && (
                  <NotificationSettings
                    categories={editors.notificationCategories}
                    channels={editors.notificationChannels}
                  />
                )}
              {section.key === "notifications" &&
                editors.settingsPermissions.notifications && (
                  <WhatsappTemplateSettings
                    templates={editors.whatsappTemplates}
                  />
                )}
              {section.key === "integrations" && editors.greenApi && (
                <GreenApiSettings
                  key={editors.greenApi.version}
                  data={editors.greenApi}
                />
              )}
              <div className="console-panel-body">
                <dl>
                  {section.fields.map((field) => (
                    <div key={field.key}>
                      <dt>{field.label}</dt>
                      <dd>
                        <bdi>{display(field.value)}</bdi>
                      </dd>
                    </div>
                  ))}
                </dl>
              </div>
              <p className="console-settings-note">
                {t("console.settings.policy_note")}
              </p>
            </section>
          ))}
          {search &&
            !matchingKeys.length &&
            !schoolMatches &&
            !(
              calendarMatches &&
              (editors.settingsPermissions.calendars ||
                editors.settingsPermissions.holidays)
            ) && (
              <div className="console-empty" role="status">
                {t("console.no_results")}
              </div>
            )}
        </div>
      </div>
    </ConsoleLayout>
  );
}
