import { useI18n } from "@/lib/i18n";
export type SessionCounts = { month:string; upcoming:number; completed:number; cancelled:number };
export default function TeacherSessionCounts({counts}:{counts:SessionCounts}) {
 const t=useI18n();
 return <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5" aria-label={t("teacher_visibility.lesson_counts")}>
  <h3 className="font-bold text-lg">{t("teacher_visibility.lesson_counts")}</h3>
  <p className="text-sm text-[var(--ink-muted)] my-2">{t("teacher_visibility.counts_hint")} <bdi>{counts.month}</bdi></p>
  <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
   {(["upcoming","completed","cancelled"] as const).map(key=><div key={key}><dt className="text-sm">{t("teacher_visibility."+key)}</dt><dd className="text-2xl font-bold mt-1">{counts[key]}</dd></div>)}
  </dl>
 </section>;
}
