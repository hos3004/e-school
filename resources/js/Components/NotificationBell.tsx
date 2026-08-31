import { Link } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";

import { useI18n } from "@/lib/i18n";

interface NotificationItem {
  id: string;
  subject: string;
  body: string;
  read_at: string | null;
  target_url: string | null;
}

interface NotificationListResponse {
  data?: NotificationItem[];
}

interface UnreadCountResponse {
  data?: { unread_count?: number };
}

interface Props {
  notificationsUrl?: string;
}

const csrfToken = () =>
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
  "";

export default function NotificationBell({
  notificationsUrl = "/notifications",
}: Props) {
  const t = useI18n();
  const containerRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLElement>(null);
  const [isOpen, setIsOpen] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [hasError, setHasError] = useState(false);

  const loadCount = useCallback(async () => {
    try {
      const response = await fetch("/api/notifications/unread-count", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      if (!response.ok) return;

      const payload = (await response.json()) as UnreadCountResponse;
      setUnreadCount(Math.max(0, Number(payload.data?.unread_count ?? 0)));
    } catch {
      // Polling is a fallback; a temporary network failure must not disrupt navigation.
    }
  }, []);

  const loadNotifications = useCallback(async () => {
    setIsLoading(true);
    setHasError(false);

    try {
      const response = await fetch("/api/notifications", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      if (!response.ok) throw new Error("notification-list-failed");

      const payload = (await response.json()) as NotificationListResponse;
      setItems(Array.isArray(payload.data) ? payload.data.slice(0, 10) : []);
    } catch {
      setHasError(true);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadCount();
    const interval = window.setInterval(() => void loadCount(), 30_000);

    return () => window.clearInterval(interval);
  }, [loadCount]);

  useEffect(() => {
    const closeOnOutsideClick = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node))
        setIsOpen(false);
    };
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setIsOpen(false);
    };

    document.addEventListener("mousedown", closeOnOutsideClick);
    document.addEventListener("keydown", closeOnEscape);

    return () => {
      document.removeEventListener("mousedown", closeOnOutsideClick);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, []);

  useEffect(() => {
    if (!isOpen) return;

    const trigger = triggerRef.current;
    const dialog = dialogRef.current;
    const focusableSelector =
      'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const focusableElements = dialog
      ? Array.from(dialog.querySelectorAll<HTMLElement>(focusableSelector))
      : [];

    focusableElements[0]?.focus();

    const keepFocusInside = (event: KeyboardEvent) => {
      if (event.key !== "Tab" || focusableElements.length === 0) return;

      const first = focusableElements[0];
      const last = focusableElements[focusableElements.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
      }
    };

    document.addEventListener("keydown", keepFocusInside);

    return () => {
      document.removeEventListener("keydown", keepFocusInside);
      trigger?.focus();
    };
  }, [isOpen]);

  const markAsRead = async (id: string) => {
    const item = items.find((notification) => notification.id === id);
    if (item?.read_at) return;

    const response = await fetch(`/api/notifications/${id}/mark-as-read`, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      },
    });
    if (!response.ok) return;

    const readAt = new Date().toISOString();
    setItems((current) =>
      current.map((notification) =>
        notification.id === id
          ? { ...notification, read_at: readAt }
          : notification,
      ),
    );
    setUnreadCount((current) => Math.max(0, current - 1));
  };

  return (
    <div className="relative" ref={containerRef}>
      <button
        aria-controls="notification-dialog"
        aria-expanded={isOpen}
        aria-haspopup="dialog"
        aria-label={t("notifications.bell.label")}
        className="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-md)] border border-transparent text-[var(--ink-muted)] transition-[color,background-color,border-color,transform] duration-150 ease-out hover:border-[var(--line)] hover:bg-[var(--surface-muted)] hover:text-[var(--ink)] active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus-ring)] motion-reduce:transform-none"
        onClick={() => {
          const nextState = !isOpen;
          setIsOpen(nextState);
          if (nextState) void loadNotifications();
        }}
        ref={triggerRef}
        type="button"
      >
        <svg
          aria-hidden="true"
          className="size-6"
          fill="none"
          viewBox="0 0 24 24"
        >
          <path
            d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="1.8"
          />
          <path
            d="M10.5 19a1.8 1.8 0 0 0 3 0"
            stroke="currentColor"
            strokeWidth="1.8"
          />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-[var(--danger)] px-1.5 py-0.5 text-center text-[10px] font-semibold text-white">
            {unreadCount > 99 ? "99+" : unreadCount}
          </span>
        )}
        <span className="sr-only" role="status">
          {t("notifications.bell.title")}: {unreadCount}
        </span>
      </button>

      {isOpen && (
        <section
          aria-label={t("notifications.bell.title")}
          aria-modal="true"
          className="absolute end-0 z-50 mt-2 w-[min(23rem,calc(100vw-2rem))] overflow-hidden rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface)] shadow-[var(--shadow-float)]"
          id="notification-dialog"
          ref={dialogRef}
          role="dialog"
        >
          <header className="flex items-center justify-between border-b border-[var(--line)] px-4 py-3">
            <h2 className="font-semibold">{t("notifications.bell.title")}</h2>
            <div className="flex items-center gap-2">
              <span className="rounded-full bg-[var(--surface-muted)] px-2 py-0.5 text-xs">
                {unreadCount}
              </span>
              <button
                aria-label={t("navigation.close")}
                className="inline-flex size-9 items-center justify-center rounded-[var(--radius-sm)] text-[var(--ink-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--ink)]"
                onClick={() => setIsOpen(false)}
                type="button"
              >
                <svg aria-hidden="true" className="size-5" fill="none" viewBox="0 0 24 24">
                  <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" strokeLinecap="round" strokeWidth="1.8" />
                </svg>
              </button>
            </div>
          </header>

          {isLoading ? (
            <p className="px-4 py-8 text-center text-sm text-[var(--ink-muted)]">
              {t("notifications.loading")}
            </p>
          ) : hasError ? (
            <div className="px-4 py-8 text-center text-sm text-[var(--danger)]">
              <p>{t("notifications.error")}</p>
              <button
                className="mt-3 font-semibold underline"
                onClick={() => void loadNotifications()}
                type="button"
              >
                {t("common.retry")}
              </button>
            </div>
          ) : items.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-[var(--ink-muted)]">
              {t("notifications.empty")}
            </p>
          ) : (
            <div className="max-h-96 divide-y divide-[var(--line)] overflow-y-auto">
              {items.map((notification) => (
                <article
                  className={
                    notification.read_at
                      ? "px-4 py-3"
                      : "bg-[var(--surface-muted)] px-4 py-3"
                  }
                  key={notification.id}
                >
                  <div className="flex items-start gap-3">
                    {!notification.read_at && (
                      <span
                        aria-hidden="true"
                        className="mt-2 size-2 shrink-0 rounded-full bg-[var(--brand)]"
                      />
                    )}
                    <div className="min-w-0 flex-1">
                      <p className="font-semibold">{notification.subject}</p>
                      {notification.body && (
                        <p className="mt-1 line-clamp-2 text-xs text-[var(--ink-muted)]">
                          {notification.body}
                        </p>
                      )}
                      <div className="mt-2 flex items-center gap-3 text-xs">
                        {!notification.read_at && (
                          <button
                            className="font-semibold text-[var(--brand)]"
                            onClick={() => void markAsRead(notification.id)}
                            type="button"
                          >
                            {t("notifications.mark_read")}
                          </button>
                        )}
                        {notification.target_url && (
                          <Link
                            className="font-semibold text-[var(--brand)]"
                            href={notification.target_url}
                            onClick={() => void markAsRead(notification.id)}
                          >
                            {t("notifications.open")}
                          </Link>
                        )}
                      </div>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}

          <Link
            className="block border-t border-[var(--line)] px-4 py-3 text-center text-sm font-semibold text-[var(--brand)]"
            href={notificationsUrl}
          >
            {t("notifications.view_all")}
          </Link>
        </section>
      )}
    </div>
  );
}
