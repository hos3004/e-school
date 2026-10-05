import { useEffect, useRef, type PropsWithChildren } from "react";
import { useI18n } from "@/lib/i18n";

export default function AccountEditor({
  open,
  title,
  subtitle,
  busy,
  onDismiss,
  children,
}: PropsWithChildren<{
  open: boolean;
  title: string;
  subtitle?: string;
  busy: boolean;
  onDismiss: () => void;
}>) {
  const t = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const element = dialog.current;
    if (!open || !element) return;
    const previousOverflow = document.body.style.overflow;
    const previousFocus = document.activeElement;
    document.body.style.overflow = "hidden";
    element.showModal();
    element.querySelector<HTMLInputElement>("[data-editor-focus]")?.focus();
    return () => {
      element.close();
      document.body.style.overflow = previousOverflow;
      if (previousFocus instanceof HTMLElement && previousFocus.isConnected)
        previousFocus.focus();
    };
  }, [open]);
  return (
    <dialog
      ref={dialog}
      className="learning-editor learning-no-print"
      dir="rtl"
      lang="ar"
      aria-labelledby={
        subtitle ? "account-editor-title" : "password-editor-title"
      }
      onCancel={(event) => {
        event.preventDefault();
        if (!busy) onDismiss();
      }}
    >
      <div className="learning-editor-heading">
        <div>
          <h2 id={subtitle ? "account-editor-title" : "password-editor-title"}>
            {title}
          </h2>
          {subtitle && <p>{subtitle}</p>}
        </div>
        <button
          type="button"
          className="learning-button secondary"
          disabled={busy}
          onClick={onDismiss}
        >
          {t("learning.close")}
        </button>
      </div>
      {children}
    </dialog>
  );
}
