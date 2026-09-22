import { useCallback, useEffect, useRef, useState } from "react";

import { useI18n } from "@/lib/i18n";

/**
 * لوحة محادثة البوت.
 *
 * تُحمَّل كسولًا عند أول فتح، فلا تدخل حزمة الصفحة الأولى ولا تثقّل الموقع على
 * من لا يفتحها أصلًا.
 */

export interface BotMessage {
  role: "user" | "bot";
  body: string;
  at?: string;
}

const REQUEST_TIMEOUT = 40_000;

const csrfToken = (): string =>
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
  "";

interface Props {
  onClose: () => void;
  initialMessages: BotMessage[];
}

export default function SupportBotPanel({
  onClose,
  initialMessages,
}: Props) {
  const t = useI18n();
  const [messages, setMessages] = useState<BotMessage[]>(initialMessages);
  const [draft, setDraft] = useState("");
  const [sending, setSending] = useState(false);
  const [expired, setExpired] = useState(false);
  const listRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLTextAreaElement | null>(null);

  useEffect(() => {
    listRef.current?.scrollTo({ top: listRef.current.scrollHeight });
  }, [messages, sending]);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const send = useCallback(async (): Promise<void> => {
    const message = draft.trim();

    if (message === "" || sending) {
      return;
    }

    setDraft("");
    setMessages((current) => [...current, { role: "user", body: message }]);
    setSending(true);

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT);

    try {
      const response = await fetch("/bot/ask", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ message }),
        signal: controller.signal,
      });

      if (
        response.status === 401 ||
        response.status === 403 ||
        response.status === 419
      ) {
        setExpired(true);
        return;
      }

      /*
       * الخادم يرد 200 ومعه نص مهذّب حتى حين يتعثّر المزوّد. ما يصل هنا من
       * أخطاء هو الشبكة أو المهلة أو الحدّ، ولكلٍّ منها نص واضح بدل صمت.
       */
      if (!response.ok) {
        setMessages((current) => [
          ...current,
          {
            role: "bot",
            body:
              response.status === 429
                ? t("support_bot.too_many")
                : t("support_bot.failed"),
          },
        ]);
        return;
      }

      const payload = (await response.json()) as { reply?: unknown };
      const reply =
        typeof payload.reply === "string" && payload.reply.trim() !== ""
          ? payload.reply
          : t("support_bot.failed");

      setMessages((current) => [...current, { role: "bot", body: reply }]);
    } catch {
      setMessages((current) => [
        ...current,
        { role: "bot", body: t("support_bot.failed") },
      ]);
    } finally {
      clearTimeout(timer);
      setSending(false);
    }
  }, [draft, sending, t]);

  return (
    <section className="support-bot-panel" aria-label={t("support_bot.title")}>
      <header className="support-bot-panel__head">
        <span className="support-bot-panel__title">{t("support_bot.title")}</span>
        <button
          type="button"
          className="support-bot-panel__close"
          onClick={onClose}
          aria-label={t("support_bot.collapse")}
        >
          ×
        </button>
      </header>

      <div className="support-bot-panel__body" ref={listRef}>
        {messages.length === 0 && (
          <p className="support-bot-panel__empty">{t("support_bot.greeting")}</p>
        )}

        {messages.map((message, index) => (
          <div
            key={`${index}-${message.role}`}
            className={
              message.role === "bot"
                ? "support-bot-bubble support-bot-bubble--bot"
                : "support-bot-bubble support-bot-bubble--user"
            }
          >
            {message.body}
          </div>
        ))}

        {sending && (
          <div
            className="support-bot-bubble support-bot-bubble--bot support-bot-bubble--typing"
            role="status"
          >
            {t("support_bot.thinking")}
          </div>
        )}

        {expired && (
          <p className="support-bot-panel__expired" role="alert">
            {t("support_bot.session_expired")}
          </p>
        )}
      </div>

      <form
        className="support-bot-panel__foot"
        onSubmit={(event) => {
          event.preventDefault();
          void send();
        }}
      >
        <textarea
          ref={inputRef}
          className="support-bot-panel__input"
          value={draft}
          rows={2}
          disabled={expired}
          placeholder={t("support_bot.placeholder")}
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => {
            // Enter يرسل، وShift+Enter سطر جديد — المتوقَّع في صندوق محادثة.
            if (event.key === "Enter" && !event.shiftKey) {
              event.preventDefault();
              void send();
            }
          }}
        />
        <button
          type="submit"
          className="support-bot-panel__send"
          disabled={sending || expired || draft.trim() === ""}
        >
          {t("support_bot.send")}
        </button>
      </form>
    </section>
  );
}
