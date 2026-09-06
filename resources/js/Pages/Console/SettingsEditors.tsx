import { router, useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import { useI18n } from "@/lib/i18n";

type Accounts = {
  username_prefix: string;
  effective_prefix: string;
  version: string;
};
type Calendar = {
  id: string;
  name: string;
  starts_on: string;
  ends_on: string;
  is_active: boolean;
  version: string;
  can_activate: boolean;
  can_close: boolean;
};
type Holiday = {
  id: string;
  name: string;
  starts_on: string;
  ends_on: string;
  academic_calendar_id: string | null;
  blocks_scheduling: boolean;
  version: string;
  can_remove: boolean;
};
type Category = {
  category: string;
  label: string;
  channels: string[];
  is_critical: boolean;
  respects_quiet_hours: boolean;
  version: string;
};
type Channel = { value: string; label: string; enabled: boolean };
export type SettingsEditorsProps = {
  accounts: Accounts | null;
  calendars: Calendar[];
  holidays: Holiday[];
  notificationCategories: Category[];
  notificationChannels: Channel[];
  settingsPermissions: {
    accounts: boolean;
    notifications: boolean;
    calendars: boolean;
    holidays: boolean;
    create_calendar: boolean;
    create_holiday: boolean;
  };
};

function Feedback({ errors }: { errors: Record<string, string> }) {
  const t = useI18n();
  return Object.keys(errors).length ? (
    <div className="console-feedback is-error" role="alert">
      {Object.values(errors).join(" · ")}
      {errors.version && (
        <button
          type="button"
          className="console-button"
          onClick={() => router.reload()}
        >
          {t("console_settings.reload")}
        </button>
      )}
    </div>
  ) : null;
}

export function AccountSettings({ accounts }: { accounts: Accounts }) {
  const t = useI18n();
  const form = useForm({
    username_prefix: accounts.username_prefix,
    version: accounts.version,
  });
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post("/manage/settings/accounts", { preserveScroll: true });
  }
  return (
    <form onSubmit={submit} className="console-settings-editor">
      <div className="form-section">
        <div className="field-grid">
          <div className="field">
            <label htmlFor="username-prefix">
              {t("console_settings.username_prefix")}
            </label>
            <input
              className="console-control"
              id="username-prefix"
              dir="ltr"
              autoCapitalize="none"
              autoComplete="off"
              spellCheck={false}
              value={form.data.username_prefix}
              onChange={(event) =>
                form.setData("username_prefix", event.target.value)
              }
            />
            <small>{t("console_settings.prefix_help")}</small>
            <small>{t("console_settings.prefix_default")}</small>
          </div>
          <div className="field">
            <span>{t("console_settings.prefix_current")}</span>
            <strong dir="ltr">{accounts.effective_prefix}</strong>
            <small>{t("console_settings.accounts_note")}</small>
          </div>
        </div>
      </div>
      <Feedback errors={form.errors} />
      <div className="savebar">
        <button
          className="console-button primary"
          disabled={form.processing || !form.isDirty}
        >
          {t(form.processing ? "console.saving" : "console_settings.save")}
        </button>
      </div>
    </form>
  );
}

function CalendarCreate() {
  const t = useI18n();
  const form = useForm({ name: "", starts_on: "", ends_on: "" });
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        form.post("/manage/settings/calendar-create", {
          preserveScroll: true,
          onSuccess: () => form.reset(),
        });
      }}
    >
      <div className="form-section">
        <h3>{t("console_settings.calendar_add")}</h3>
        <p>{t("console_settings.calendar_draft_help")}</p>
        <div className="field-grid">
          <div className="field span2">
            <label htmlFor="calendar-name">
              {t("console_settings.calendar_name")}
            </label>
            <input
              id="calendar-name"
              className="console-control"
              value={form.data.name}
              required
              onChange={(event) => form.setData("name", event.target.value)}
            />
          </div>
          <div className="field">
            <label htmlFor="calendar-start">
              {t("console_settings.starts_on")}
            </label>
            <input
              id="calendar-start"
              className="console-control"
              type="date"
              required
              value={form.data.starts_on}
              onChange={(event) =>
                form.setData("starts_on", event.target.value)
              }
            />
          </div>
          <div className="field">
            <label htmlFor="calendar-end">
              {t("console_settings.ends_on")}
            </label>
            <input
              id="calendar-end"
              className="console-control"
              type="date"
              required
              min={form.data.starts_on}
              value={form.data.ends_on}
              onChange={(event) => form.setData("ends_on", event.target.value)}
            />
          </div>
        </div>
      </div>
      <Feedback errors={form.errors} />
      <div className="savebar">
        <button className="console-button primary" disabled={form.processing}>
          {t("console_settings.calendar_create")}
        </button>
      </div>
    </form>
  );
}

function HolidayCreate({ calendars }: { calendars: Calendar[] }) {
  const t = useI18n();
  const form = useForm({
    name: "",
    starts_on: "",
    ends_on: "",
    academic_calendar_id: "",
    blocks_scheduling: true,
  });
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        form.post("/manage/settings/holiday-create", {
          preserveScroll: true,
          onSuccess: () => form.reset(),
        });
      }}
    >
      <div className="form-section">
        <h3>{t("console_settings.holiday_add")}</h3>
        <div className="field-grid">
          <div className="field">
            <label htmlFor="holiday-name">
              {t("console_settings.holiday_name")}
            </label>
            <input
              id="holiday-name"
              className="console-control"
              value={form.data.name}
              required
              onChange={(event) => form.setData("name", event.target.value)}
            />
          </div>
          <div className="field">
            <label htmlFor="holiday-scope">
              {t("console_settings.holiday_scope")}
            </label>
            <select
              id="holiday-scope"
              className="console-control"
              value={form.data.academic_calendar_id}
              onChange={(event) =>
                form.setData("academic_calendar_id", event.target.value)
              }
            >
              <option value="">{t("console_settings.all_calendars")}</option>
              {calendars.map((calendar) => (
                <option key={calendar.id} value={calendar.id}>
                  {calendar.name}
                </option>
              ))}
            </select>
          </div>
          <div className="field">
            <label htmlFor="holiday-start">
              {t("console_settings.starts_on")}
            </label>
            <input
              id="holiday-start"
              className="console-control"
              type="date"
              required
              value={form.data.starts_on}
              onChange={(event) =>
                form.setData("starts_on", event.target.value)
              }
            />
          </div>
          <div className="field">
            <label htmlFor="holiday-end">{t("console_settings.ends_on")}</label>
            <input
              id="holiday-end"
              className="console-control"
              type="date"
              required
              min={form.data.starts_on}
              value={form.data.ends_on}
              onChange={(event) => form.setData("ends_on", event.target.value)}
            />
          </div>
          <label className="console-settings-check span2">
            <input
              type="checkbox"
              checked={form.data.blocks_scheduling}
              onChange={(event) =>
                form.setData("blocks_scheduling", event.target.checked)
              }
            />
            <span>{t("console_settings.holiday_block")}</span>
          </label>
        </div>
      </div>
      <Feedback errors={form.errors} />
      <div className="savebar">
        <button className="console-button primary" disabled={form.processing}>
          {t("console_settings.holiday_create")}
        </button>
      </div>
    </form>
  );
}

function SettingAction({
  record,
  operation,
}: {
  record: { id: string; version: string };
  operation: "calendar-activate" | "calendar-close" | "holiday-remove";
}) {
  const t = useI18n();
  const [confirming, setConfirming] = useState(false);
  const form = useForm({ id: record.id, version: record.version });
  const key = operation.replace("-", "_");
  return (
    <div className="console-settings-action">
      {confirming ? (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            form.post("/manage/settings/" + operation, {
              preserveScroll: true,
              onSuccess: () => setConfirming(false),
            });
          }}
        >
          <p>{t("console_settings." + key + "_note")}</p>
          <div className="actions">
            <button
              className="console-button primary"
              disabled={form.processing}
            >
              {t("console_settings.confirm")}
            </button>
            <button
              type="button"
              className="console-button"
              disabled={form.processing}
              onClick={() => setConfirming(false)}
            >
              {t("console_settings.cancel")}
            </button>
          </div>
          <Feedback errors={form.errors} />
        </form>
      ) : (
        <button
          type="button"
          className="console-button"
          onClick={() => setConfirming(true)}
        >
          {t("console_settings." + key)}
        </button>
      )}
    </div>
  );
}

export function CalendarSettings({
  calendars,
  holidays,
  settingsPermissions: permissions,
}: SettingsEditorsProps) {
  const t = useI18n();
  return (
    <section
      id="settings-calendar"
      className="console-panel console-settings-section"
    >
      <div className="console-panel-header">
        <div>
          <h2>{t("console_settings.calendars")}</h2>
          <p>{t("console_settings.calendar_note")}</p>
        </div>
      </div>
      {permissions.calendars && (
        <>
          <div className="console-settings-records">
            {calendars.length ? (
              calendars.map((calendar) => (
                <article
                  key={calendar.id + calendar.version}
                  className="console-settings-record"
                >
                  <div>
                    <h3>{calendar.name}</h3>
                    <p>
                      <bdi>{calendar.starts_on}</bdi> —{" "}
                      <bdi>{calendar.ends_on}</bdi>
                    </p>
                    <span
                      className={
                        "console-status" +
                        (calendar.is_active ? " is-success" : "")
                      }
                    >
                      {t(
                        "console_settings." +
                          (calendar.is_active
                            ? "calendar_active"
                            : "calendar_draft"),
                      )}
                    </span>
                  </div>
                  {calendar.can_activate && (
                    <SettingAction
                      record={calendar}
                      operation="calendar-activate"
                    />
                  )}
                  {calendar.can_close && (
                    <SettingAction
                      record={calendar}
                      operation="calendar-close"
                    />
                  )}
                </article>
              ))
            ) : (
              <p className="console-empty">
                {t("console_settings.calendar_empty")}
              </p>
            )}
          </div>
          {permissions.create_calendar && <CalendarCreate />}
        </>
      )}
      {permissions.holidays && (
        <>
          <div className="form-section">
            <h3>{t("console_settings.holidays")}</h3>
          </div>
          <div className="console-settings-records">
            {holidays.length ? (
              holidays.map((holiday) => (
                <article
                  key={holiday.id + holiday.version}
                  className="console-settings-record"
                >
                  <div>
                    <h3>{holiday.name}</h3>
                    <p>
                      <bdi>{holiday.starts_on}</bdi> —{" "}
                      <bdi>{holiday.ends_on}</bdi>
                    </p>
                    <small>
                      {holiday.academic_calendar_id
                        ? calendars.find(
                            (calendar) =>
                              calendar.id === holiday.academic_calendar_id,
                          )?.name || t("console_settings.calendar")
                        : t("console_settings.all_calendars")}{" "}
                      ·{" "}
                      {t(
                        "console_settings." +
                          (holiday.blocks_scheduling
                            ? "holiday_blocked"
                            : "holiday_informational"),
                      )}
                    </small>
                  </div>
                  {holiday.can_remove && (
                    <SettingAction
                      record={holiday}
                      operation="holiday-remove"
                    />
                  )}
                </article>
              ))
            ) : (
              <p className="console-empty">
                {t("console_settings.holiday_empty")}
              </p>
            )}
          </div>
          {permissions.create_holiday && (
            <HolidayCreate calendars={calendars} />
          )}
        </>
      )}
    </section>
  );
}

function CategoryEditor({
  category,
  channels,
}: {
  category: Category;
  channels: Channel[];
}) {
  const t = useI18n();
  const form = useForm({
    category: category.category,
    channels: category.channels,
    is_critical: category.is_critical,
    respects_quiet_hours: category.respects_quiet_hours,
    version: category.version,
  });
  return (
    <form
      className="console-notification-setting"
      onSubmit={(event) => {
        event.preventDefault();
        form.post("/manage/settings/notifications", { preserveScroll: true });
      }}
    >
      <fieldset>
        <legend>{category.label}</legend>
        <div className="console-settings-channels">
          {channels.map((channel) => (
            <label key={channel.value} className="console-settings-check">
              <input
                type="checkbox"
                checked={form.data.channels.includes(channel.value)}
                onChange={(event) =>
                  form.setData(
                    "channels",
                    event.target.checked
                      ? [...form.data.channels, channel.value]
                      : form.data.channels.filter(
                          (value) => value !== channel.value,
                        ),
                  )
                }
              />
              <span>
                {channel.label}
                {!channel.enabled && (
                  <small>{t("console_settings.channel_unavailable")}</small>
                )}
              </span>
            </label>
          ))}
        </div>
        <div className="console-settings-rules">
          <label className="console-settings-check">
            <input
              type="checkbox"
              checked={form.data.is_critical}
              onChange={(event) =>
                form.setData("is_critical", event.target.checked)
              }
            />
            <span>{t("console_settings.critical")}</span>
          </label>
          <label className="console-settings-check">
            <input
              type="checkbox"
              disabled={form.data.is_critical}
              checked={form.data.respects_quiet_hours}
              onChange={(event) =>
                form.setData("respects_quiet_hours", event.target.checked)
              }
            />
            <span>{t("console_settings.quiet_hours")}</span>
          </label>
          {form.data.is_critical && (
            <small>{t("console_settings.critical_note")}</small>
          )}
        </div>
      </fieldset>
      <Feedback errors={form.errors} />
      <button
        className="console-button"
        disabled={form.processing || !form.isDirty}
      >
        {t("console_settings.save")}
      </button>
    </form>
  );
}

export function NotificationSettings({
  categories,
  channels,
}: {
  categories: Category[];
  channels: Channel[];
}) {
  const t = useI18n();
  return (
    <div className="console-settings-editor">
      <p className="console-settings-note">
        {t("console_settings.notification_note")}
      </p>
      {categories.map((category) => (
        <CategoryEditor
          key={category.category + category.version}
          category={category}
          channels={channels}
        />
      ))}
    </div>
  );
}
