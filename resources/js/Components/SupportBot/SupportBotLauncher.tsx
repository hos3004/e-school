import { lazy, Suspense, useCallback, useEffect, useRef, useState } from "react";

import { useI18n } from "@/lib/i18n";

import type { BotMessage } from "./SupportBotPanel";

import "../../../css/support-bot.css";

/**
 * الكرة العائمة التي تفتح محادثة البوت.
 *
 * ثلاثة قرارات تستحق الشرح:
 *
 *  1. **تُركَّب في الـLayout لا في الصفحة.** التنقّل في هذا التطبيق عبر Inertia،
 *     أي أن الصفحة تتبدّل ولا يُعاد تركيب الـLayout. فتبقى المحادثة مفتوحة
 *     وحيّة والمستخدم ينتقل بين الشاشات — وهو بالضبط ما يلزم لمتابعة خطوات
 *     يمليها البوت شاشةً بعد شاشة.
 *
 *  2. **اللوحة تُحمَّل كسولًا.** كود المحادثة لا يدخل حزمة أول تحميل، فلا يدفع
 *     ثمنَه من لا يفتح البوت.
 *
 *  3. **موضع الكرة محفوظ محليًا.** تفضيل عرض لصاحب الجهاز وحده، لا حالة مشتركة
 *     تستحق جدولًا. القراءة والكتابة داخل try لأن التخزين المحلي قد يكون
 *     محجوبًا في نافذة خاصة.
 */

const SupportBotPanel = lazy(() => import("./SupportBotPanel"));

const POSITION_KEY = "support-bot:position";
const EDGE_MARGIN = 12;
const DRAG_THRESHOLD = 4;

interface Position {
  x: number;
  y: number;
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max);
}

function readPosition(): Position | null {
  try {
    const raw = window.localStorage.getItem(POSITION_KEY);

    if (raw === null) {
      return null;
    }

    const parsed: unknown = JSON.parse(raw);

    if (
      typeof parsed === "object" &&
      parsed !== null &&
      typeof (parsed as Position).x === "number" &&
      typeof (parsed as Position).y === "number"
    ) {
      return parsed as Position;
    }
  } catch {
    // تخزين محجوب أو قيمة تالفة — نعود إلى الموضع الافتراضي بلا ضجيج.
  }

  return null;
}

function writePosition(position: Position): void {
  try {
    window.localStorage.setItem(POSITION_KEY, JSON.stringify(position));
  } catch {
    // لا شيء: الموضع تفضيل لا بيانات.
  }
}

export default function SupportBotLauncher() {
  const t = useI18n();
  const [available, setAvailable] = useState(false);
  const [open, setOpen] = useState(false);
  const [history, setHistory] = useState<BotMessage[]>([]);
  const [position, setPosition] = useState<Position | null>(null);

  const dragging = useRef(false);
  const moved = useRef(false);
  const origin = useRef<{ pointerX: number; pointerY: number; x: number; y: number }>({
    pointerX: 0,
    pointerY: 0,
    x: 0,
    y: 0,
  });

  useEffect(() => {
    setPosition(readPosition());
  }, []);

  /*
   * فحص واحد عند التركيب: هل لهذا المستخدم بوت أصلًا؟ الإخفاء عند عدم الإتاحة
   * أصدق من كرة تفتح لتعتذر.
   */
  useEffect(() => {
    const controller = new AbortController();

    void (async () => {
      try {
        const response = await fetch("/bot/history", {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
          signal: controller.signal,
        });

        if (!response.ok) {
          return;
        }

        const payload = (await response.json()) as {
          available?: unknown;
          messages?: unknown;
        };

        if (payload.available !== true) {
          return;
        }

        setAvailable(true);

        if (Array.isArray(payload.messages)) {
          setHistory(payload.messages as BotMessage[]);
        }
      } catch {
        // شبكة أو جلسة منتهية: تبقى الكرة مخفية.
      }
    })();

    return () => controller.abort();
  }, []);

  const onPointerDown = useCallback(
    (event: React.PointerEvent<HTMLButtonElement>): void => {
      dragging.current = true;
      moved.current = false;

      const rect = event.currentTarget.getBoundingClientRect();

      origin.current = {
        pointerX: event.clientX,
        pointerY: event.clientY,
        x: rect.left,
        y: rect.top,
      };

      event.currentTarget.setPointerCapture(event.pointerId);
    },
    [],
  );

  const onPointerMove = useCallback(
    (event: React.PointerEvent<HTMLButtonElement>): void => {
      if (!dragging.current) {
        return;
      }

      const dx = event.clientX - origin.current.pointerX;
      const dy = event.clientY - origin.current.pointerY;

      if (!moved.current && Math.hypot(dx, dy) < DRAG_THRESHOLD) {
        return;
      }

      moved.current = true;

      const size = event.currentTarget.offsetWidth;

      setPosition({
        x: clamp(
          origin.current.x + dx,
          EDGE_MARGIN,
          window.innerWidth - size - EDGE_MARGIN,
        ),
        y: clamp(
          origin.current.y + dy,
          EDGE_MARGIN,
          window.innerHeight - size - EDGE_MARGIN,
        ),
      });
    },
    [],
  );

  const onPointerUp = useCallback(
    (event: React.PointerEvent<HTMLButtonElement>): void => {
      if (!dragging.current) {
        return;
      }

      dragging.current = false;
      event.currentTarget.releasePointerCapture(event.pointerId);

      if (moved.current) {
        setPosition((current) => {
          if (current !== null) {
            writePosition(current);
          }

          return current;
        });

        return;
      }

      // سحبٌ لم يتجاوز العتبة = نقرة.
      setOpen((current) => !current);
    },
    [],
  );

  if (!available) {
    return null;
  }

  const anchor =
    position === null
      ? undefined
      : { left: `${position.x}px`, top: `${position.y}px`, insetInlineStart: "auto", bottom: "auto" };

  return (
    <div className="support-bot" style={anchor}>
      {open && (
        <Suspense
          fallback={
            <div className="support-bot-panel support-bot-panel--loading">
              {t("support_bot.loading")}
            </div>
          }
        >
          <SupportBotPanel
            initialMessages={history}
            onClose={() => setOpen(false)}
          />
        </Suspense>
      )}

      <button
        type="button"
        className="support-bot__ball"
        aria-label={open ? t("support_bot.collapse") : t("support_bot.open")}
        aria-expanded={open}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={() => {
          dragging.current = false;
        }}
      >
        <span aria-hidden="true">{open ? "×" : "؟"}</span>
      </button>
    </div>
  );
}
