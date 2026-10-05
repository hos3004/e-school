import { useForm } from "@inertiajs/react";
import { useState } from "react";

import ConsoleLayout from "@/Layouts/ConsoleLayout";
import { useI18n } from "@/lib/i18n";

/**
 * قسم «The Bot» — التشغيل والنصوص والحدود والوصول والأرشيف في شاشة واحدة.
 *
 * كل كتابة تطلب سببًا: الحقل جزء من النموذج لا نافذة تأكيد، فالسبب يُكتب قبل
 * الضغط لا بعده.
 */

interface Option {
  value: string;
  label: string;
}

interface TopicOption extends Option {
  money: boolean;
}

interface Entry {
  id: string;
  scope: "global" | "organization";
  kind: string;
  key: string;
  locale: string;
  title: string | null;
  body: string;
  topic: string | null;
  audiences: string[];
  isActive: boolean;
}

interface Rule {
  id: string;
  scope: "global" | "organization";
  topic: string;
  audience: string;
  mode: string;
  replyKey: string | null;
  isActive: boolean;
}

interface BlockedAccount {
  id: string;
  userId: string;
  name: string;
  enabled: boolean;
  reason: string;
}

interface Conversation {
  id: string;
  name: string;
  audience: string;
  messages: number;
  blocked: number;
  startedAt: string;
  lastMessageAt: string | null;
  closed: boolean;
}

interface TranscriptMessage {
  role: string;
  body: string;
  topic: string | null;
  mode: string | null;
  generated: boolean;
  failure: string | null;
  at: string;
}

interface Props {
  connection: {
    configured: boolean;
    enabled: boolean;
    status: string;
    state: string;
    base_url: string;
  };
  audiences: { enabled: string[]; isDefault: boolean };
  entries: Entry[];
  rules: Rule[];
  blockedAccounts: BlockedAccount[];
  usage: {
    spentTodayMicroUsd: number;
    dailyCapMicroUsd: number;
    messagesPerDay: number;
    messagesPerMinute: number;
  };
  vocabulary: {
    topics: TopicOption[];
    audiences: Option[];
    modes: Option[];
    kinds: Option[];
  };
  archive: Conversation[] | null;
  abilities: { manage: boolean; readArchive: boolean };
  urls: Record<string, string>;
}

type Tab = "status" | "entries" | "rules" | "access" | "archive";

const money = (micro: number): string => `$${(micro / 1_000_000).toFixed(3)}`;

export default function SupportBotPage(props: Props) {
  const t = useI18n();
  const [tab, setTab] = useState<Tab>("status");

  const tabs: Tab[] = ["status", "entries", "rules", "access"];

  if (props.abilities.readArchive) {
    tabs.push("archive");
  }

  return (
    <ConsoleLayout
      title={t("console_bot.title")}
      description={t("console_bot.description")}
      section="settings"
    >
      <nav className="console-tabs" aria-label={t("console_bot.title")}>
        {tabs.map((name) => (
          <button
            key={name}
            type="button"
            className={tab === name ? "is-active" : undefined}
            onClick={() => setTab(name)}
          >
            {t(`console_bot.tab.${name}`)}
          </button>
        ))}
      </nav>

      {tab === "status" && <StatusTab {...props} />}
      {tab === "entries" && <EntriesTab {...props} />}
      {tab === "rules" && <RulesTab {...props} />}
      {tab === "access" && <AccessTab {...props} />}
      {tab === "archive" && props.archive !== null && (
        <ArchiveTab conversations={props.archive} url={props.urls.conversation ?? ""} />
      )}
    </ConsoleLayout>
  );
}

function StatusTab({ connection, audiences, usage, vocabulary, urls }: Props) {
  const t = useI18n();
  const toggle = useForm({ active: !connection.enabled, reason: "" });
  const audienceForm = useForm<{ audiences: string[]; reason: string }>({
    audiences: audiences.enabled,
    reason: "",
  });

  return (
    <div className="console-stack">
      <section className="console-card">
        <h2>{t("console_bot.status.heading")}</h2>
        <p>
          {connection.enabled
            ? t("console_bot.status.on")
            : t("console_bot.status.off")}
        </p>
        {!connection.configured && (
          <p className="console-feedback is-error" role="alert">
            {t("console_bot.status.no_key")}
          </p>
        )}

        <form
          onSubmit={(event) => {
            event.preventDefault();
            toggle.post(urls.toggle ?? "", { preserveScroll: true });
          }}
        >
          <label>
            {t("console_bot.reason")}
            <input
              type="text"
              value={toggle.data.reason}
              onChange={(event) => toggle.setData("reason", event.target.value)}
              required
              minLength={3}
            />
          </label>
          {toggle.errors.reason && (
            <p className="console-feedback is-error">{toggle.errors.reason}</p>
          )}
          <button type="submit" disabled={toggle.processing}>
            {connection.enabled
              ? t("console_bot.status.turn_off")
              : t("console_bot.status.turn_on")}
          </button>
        </form>
      </section>

      <section className="console-card">
        <h2>{t("console_bot.audiences.heading")}</h2>
        <p>{t("console_bot.audiences.hint")}</p>
        <form
          onSubmit={(event) => {
            event.preventDefault();
            audienceForm.post(urls.audiences ?? "", { preserveScroll: true });
          }}
        >
          {vocabulary.audiences.map((audience) => (
            <label key={audience.value} className="console-checkbox">
              <input
                type="checkbox"
                checked={audienceForm.data.audiences.includes(audience.value)}
                onChange={(event) =>
                  audienceForm.setData(
                    "audiences",
                    event.target.checked
                      ? [...audienceForm.data.audiences, audience.value]
                      : audienceForm.data.audiences.filter((v) => v !== audience.value),
                  )
                }
              />
              {audience.label}
            </label>
          ))}
          <label>
            {t("console_bot.reason")}
            <input
              type="text"
              value={audienceForm.data.reason}
              onChange={(event) => audienceForm.setData("reason", event.target.value)}
              required
              minLength={3}
            />
          </label>
          <button type="submit" disabled={audienceForm.processing}>
            {t("console_bot.save")}
          </button>
        </form>
      </section>

      <section className="console-card">
        <h2>{t("console_bot.usage.heading")}</h2>
        <ul>
          <li>
            {t("console_bot.usage.today")}: {money(usage.spentTodayMicroUsd)} /{" "}
            {money(usage.dailyCapMicroUsd)}
          </li>
          <li>
            {t("console_bot.usage.per_day")}: {usage.messagesPerDay}
          </li>
          <li>
            {t("console_bot.usage.per_minute")}: {usage.messagesPerMinute}
          </li>
        </ul>
      </section>
    </div>
  );
}

function EntriesTab({ entries, vocabulary, urls }: Props) {
  const t = useI18n();
  const [editing, setEditing] = useState<Entry | null>(null);

  return (
    <div className="console-stack">
      {editing !== null && (
        <EntryForm
          entry={editing}
          vocabulary={vocabulary}
          url={urls.entry ?? ""}
          onDone={() => setEditing(null)}
        />
      )}

      <table className="console-table">
        <thead>
          <tr>
            <th>{t("console_bot.entries.kind")}</th>
            <th>{t("console_bot.entries.key")}</th>
            <th>{t("console_bot.entries.scope")}</th>
            <th>{t("console_bot.entries.body")}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {entries.map((entry) => (
            <tr key={entry.id}>
              <td>
                {vocabulary.kinds.find((k) => k.value === entry.kind)?.label ?? entry.kind}
              </td>
              <td>{entry.key}</td>
              <td>
                {entry.scope === "global"
                  ? t("console_bot.entries.global")
                  : t("console_bot.entries.organization")}
              </td>
              <td className="console-truncate">{entry.body.slice(0, 120)}</td>
              <td>
                <button type="button" onClick={() => setEditing(entry)}>
                  {t("console_bot.edit")}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function EntryForm({
  entry,
  vocabulary,
  url,
  onDone,
}: {
  entry: Entry;
  vocabulary: Props["vocabulary"];
  url: string;
  onDone: () => void;
}) {
  const t = useI18n();
  const form = useForm({
    kind: entry.kind,
    key: entry.key,
    locale: entry.locale,
    title: entry.title ?? "",
    body: entry.body,
    topic: entry.topic ?? "",
    audiences: entry.audiences,
    is_active: entry.isActive,
    reason: "",
  });

  return (
    <section className="console-card">
      <h2>
        {entry.key} · {entry.locale}
      </h2>
      {entry.scope === "global" && (
        <p>{t("console_bot.entries.global_hint")}</p>
      )}
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post(url, { preserveScroll: true, onSuccess: onDone });
        }}
      >
        <label>
          {t("console_bot.entries.body")}
          <textarea
            rows={8}
            value={form.data.body}
            onChange={(event) => form.setData("body", event.target.value)}
            required
          />
        </label>

        <fieldset>
          <legend>{t("console_bot.entries.audiences")}</legend>
          {vocabulary.audiences.map((audience) => (
            <label key={audience.value} className="console-checkbox">
              <input
                type="checkbox"
                checked={form.data.audiences.includes(audience.value)}
                onChange={(event) =>
                  form.setData(
                    "audiences",
                    event.target.checked
                      ? [...form.data.audiences, audience.value]
                      : form.data.audiences.filter((v) => v !== audience.value),
                  )
                }
              />
              {audience.label}
            </label>
          ))}
        </fieldset>

        <label>
          {t("console_bot.reason")}
          <input
            type="text"
            value={form.data.reason}
            onChange={(event) => form.setData("reason", event.target.value)}
            required
            minLength={3}
          />
        </label>

        <button type="submit" disabled={form.processing}>
          {t("console_bot.save")}
        </button>
        <button type="button" onClick={onDone}>
          {t("console_bot.cancel")}
        </button>
      </form>
    </section>
  );
}

function RulesTab({ rules, vocabulary, urls }: Props) {
  const t = useI18n();
  const form = useForm({ topic: "", audience: "", mode: "guide", reply_key: "", reason: "" });

  const effective = (topic: string, audience: string): Rule | undefined =>
    rules.find(
      (rule) =>
        rule.topic === topic && rule.audience === audience && rule.scope === "organization",
    ) ??
    rules.find((rule) => rule.topic === topic && rule.audience === audience);

  return (
    <div className="console-stack">
      <p>{t("console_bot.rules.hint")}</p>

      <table className="console-table">
        <thead>
          <tr>
            <th>{t("console_bot.rules.topic")}</th>
            {vocabulary.audiences.map((audience) => (
              <th key={audience.value}>{audience.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {vocabulary.topics.map((topic) => (
            <tr key={topic.value}>
              <td>
                {topic.label}
                {topic.money && <span title={t("console_bot.rules.money_locked")}> 🔒</span>}
              </td>
              {vocabulary.audiences.map((audience) => {
                const rule = effective(topic.value, audience.value);

                return (
                  <td key={audience.value}>
                    <button
                      type="button"
                      onClick={() =>
                        form.setData({
                          topic: topic.value,
                          audience: audience.value,
                          mode: rule?.mode ?? "guide",
                          reply_key: rule?.replyKey ?? "",
                          reason: "",
                        })
                      }
                    >
                      {vocabulary.modes.find((m) => m.value === rule?.mode)?.label ?? "—"}
                    </button>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>

      {form.data.topic !== "" && (
        <section className="console-card">
          <h2>{t("console_bot.rules.editing")}</h2>
          <form
            onSubmit={(event) => {
              event.preventDefault();
              form.post(urls.rule ?? "", {
                preserveScroll: true,
                onSuccess: () => form.setData("topic", ""),
              });
            }}
          >
            <label>
              {t("console_bot.rules.mode")}
              <select
                value={form.data.mode}
                onChange={(event) => form.setData("mode", event.target.value)}
              >
                {vocabulary.modes.map((mode) => (
                  <option key={mode.value} value={mode.value}>
                    {mode.label}
                  </option>
                ))}
              </select>
            </label>

            <label>
              {t("console_bot.rules.reply_key")}
              <input
                type="text"
                value={form.data.reply_key}
                onChange={(event) => form.setData("reply_key", event.target.value)}
              />
            </label>

            <label>
              {t("console_bot.reason")}
              <input
                type="text"
                value={form.data.reason}
                onChange={(event) => form.setData("reason", event.target.value)}
                required
                minLength={3}
              />
            </label>

            <button type="submit" disabled={form.processing}>
              {t("console_bot.save")}
            </button>
          </form>
        </section>
      )}
    </div>
  );
}

function AccessTab({ blockedAccounts, urls }: Props) {
  const t = useI18n();
  const form = useForm({ user_id: "", enabled: false, reason: "" });

  return (
    <div className="console-stack">
      <section className="console-card">
        <h2>{t("console_bot.access.heading")}</h2>
        <p>{t("console_bot.access.hint")}</p>
        <form
          onSubmit={(event) => {
            event.preventDefault();
            form.post(urls.access ?? "", { preserveScroll: true });
          }}
        >
          <label>
            {t("console_bot.access.user_id")}
            <input
              type="text"
              value={form.data.user_id}
              onChange={(event) => form.setData("user_id", event.target.value)}
              required
            />
          </label>
          <label className="console-checkbox">
            <input
              type="checkbox"
              checked={form.data.enabled}
              onChange={(event) => form.setData("enabled", event.target.checked)}
            />
            {t("console_bot.access.enabled")}
          </label>
          <label>
            {t("console_bot.reason")}
            <input
              type="text"
              value={form.data.reason}
              onChange={(event) => form.setData("reason", event.target.value)}
              required
              minLength={3}
            />
          </label>
          <button type="submit" disabled={form.processing}>
            {t("console_bot.save")}
          </button>
        </form>
      </section>

      <table className="console-table">
        <thead>
          <tr>
            <th>{t("console_bot.access.account")}</th>
            <th>{t("console_bot.access.state")}</th>
            <th>{t("console_bot.reason")}</th>
          </tr>
        </thead>
        <tbody>
          {blockedAccounts.map((account) => (
            <tr key={account.id}>
              <td>{account.name}</td>
              <td>
                {account.enabled
                  ? t("console_bot.access.open")
                  : t("console_bot.access.closed")}
              </td>
              <td>{account.reason}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ArchiveTab({
  conversations,
  url,
}: {
  conversations: Conversation[];
  url: string;
}) {
  const t = useI18n();
  const [transcript, setTranscript] = useState<TranscriptMessage[] | null>(null);
  const [loading, setLoading] = useState(false);

  const open = async (id: string): Promise<void> => {
    setLoading(true);

    try {
      const response = await fetch(url.replace("__ID__", id), {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });

      if (!response.ok) {
        setTranscript([]);
        return;
      }

      const payload = (await response.json()) as { messages?: TranscriptMessage[] };
      setTranscript(payload.messages ?? []);
    } catch {
      setTranscript([]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="console-stack">
      <table className="console-table">
        <thead>
          <tr>
            <th>{t("console_bot.archive.person")}</th>
            <th>{t("console_bot.archive.messages")}</th>
            <th>{t("console_bot.archive.blocked")}</th>
            <th>{t("console_bot.archive.last")}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {conversations.length === 0 && (
            <tr>
              <td colSpan={5}>{t("console_bot.archive.empty")}</td>
            </tr>
          )}
          {conversations.map((conversation) => (
            <tr key={conversation.id}>
              <td>{conversation.name}</td>
              <td>{conversation.messages}</td>
              <td>{conversation.blocked}</td>
              <td>{conversation.lastMessageAt ?? "—"}</td>
              <td>
                <button type="button" onClick={() => void open(conversation.id)}>
                  {t("console_bot.archive.view")}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {loading && <p>{t("console_bot.archive.loading")}</p>}

      {transcript !== null && !loading && (
        <section className="console-card">
          <h2>{t("console_bot.archive.transcript")}</h2>
          {transcript.map((message, index) => (
            <p key={`${index}-${message.role}`}>
              <strong>{message.role}</strong>
              {message.mode !== null && <em> [{message.mode}]</em>}: {message.body}
            </p>
          ))}
          <button type="button" onClick={() => setTranscript(null)}>
            {t("console_bot.cancel")}
          </button>
        </section>
      )}
    </div>
  );
}
