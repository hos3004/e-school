export const NOTIFICATION_POLL_INTERVAL = 30_000;
const MAX_RETRY_DELAY = 300_000;
const REQUEST_TIMEOUT = 15_000;

export function isExpiredSession(status: number): boolean {
  return status === 401 || status === 403 || status === 419;
}

export function startNotificationPolling(
  onCount: (count: number) => void,
  onSessionExpired: () => void,
): () => void {
  let stopped = false;
  let failures = 0;
  let nextAttempt = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let active: AbortController | undefined;

  function available(): boolean {
    return !stopped && document.visibilityState === "visible" && navigator.onLine;
  }

  function stop(): void {
    stopped = true;
    clearTimeout(timer);
    active?.abort();
    document.removeEventListener("visibilitychange", resume);
    window.removeEventListener("online", resume);
    window.removeEventListener("offline", resume);
  }

  function schedule(): void {
    clearTimeout(timer);
    if (available() && !active) {
      timer = setTimeout(() => void poll(), Math.max(0, nextAttempt - Date.now()));
    }
  }

  async function poll(): Promise<void> {
    if (!available() || active) return;
    const controller = new AbortController();
    active = controller;
    const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT);
    let retryAfter = 0;
    try {
      const response = await fetch("/api/notifications/unread-count", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      if (stopped || controller.signal.aborted) return;
      if (isExpiredSession(response.status)) {
        stop();
        onSessionExpired();
        return;
      }
      if (response.status === 429 || response.status === 503) {
        const value = response.headers.get("Retry-After");
        if (value) {
          retryAfter = /^\d+$/.test(value)
            ? Number(value) * 1000
            : Math.max(0, Date.parse(value) - Date.now()) || 0;
        }
      }
      if (!response.ok) throw new Error("notification-count-failed");
      const payload = (await response.json()) as { data?: { unread_count?: number } };
      const count = Number(payload.data?.unread_count);
      if (!Number.isFinite(count) || count < 0) throw new Error("notification-count-invalid");
      if (stopped || controller.signal.aborted) return;
      failures = 0;
      nextAttempt = Date.now() + NOTIFICATION_POLL_INTERVAL;
      onCount(Math.floor(count));
    } catch {
      if (!stopped && available()) {
        failures++;
        nextAttempt = Date.now() + Math.max(
          retryAfter,
          Math.min(MAX_RETRY_DELAY, NOTIFICATION_POLL_INTERVAL * 2 ** Math.min(failures, 4)),
        );
      }
    } finally {
      clearTimeout(timeout);
      active = undefined;
      schedule();
    }
  }

  function resume(): void {
    if (!available()) {
      clearTimeout(timer);
      active?.abort();
    } else {
      schedule();
    }
  }

  document.addEventListener("visibilitychange", resume);
  window.addEventListener("online", resume);
  window.addEventListener("offline", resume);
  schedule();
  return stop;
}
