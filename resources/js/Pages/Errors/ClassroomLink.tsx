import Seo from "@/Components/Marketing/Seo";
import MarketingLayout from "@/Layouts/MarketingLayout";
import { useI18n } from "@/lib/i18n";

interface Props {
  reason: string;
}

/**
 * الصفحة التي يراها الطالب حين يفتح الرابط اليدوي خارج شروط الدخول.
 * الرسالة تأتي مترجمة من الخادم لأن الطالب هنا بلا جلسة ولا تفضيل لغة محفوظ.
 */
export default function ClassroomLink({ reason }: Props) {
  const t = useI18n();

  return (
    <MarketingLayout>
      <Seo title={t("marketing.classroom_link.title")} description={reason} />
      <section className="marketing-section">
        <div className="marketing-container grid min-h-[520px] place-items-center text-center">
          <div>
            <h1 className="mt-6 text-3xl font-semibold">
              {t("marketing.classroom_link.title")}
            </h1>
            <p className="mx-auto mt-5 max-w-xl leading-8 text-[var(--marketing-muted)]">
              {reason}
            </p>
            <p className="mx-auto mt-3 max-w-xl leading-8 text-[var(--marketing-muted)]">
              {t("marketing.classroom_link.help")}
            </p>
          </div>
        </div>
      </section>
    </MarketingLayout>
  );
}
