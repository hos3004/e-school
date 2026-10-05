import { useForm } from "@inertiajs/react";
import { useI18n } from "@/lib/i18n";
export type FinancialVisibilityData={visible:boolean;updateUrl:string|null};
export default function FinancialVisibility({setting}:{setting:FinancialVisibilityData}){
 const t=useI18n();
 const form=useForm({financials_visible:setting.visible,reason:""});
 return <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
  <h2 className="font-bold text-lg">{t("teacher_visibility.title")}</h2>
  <p className="text-sm my-2">{t("teacher_visibility.hint")}</p>
  {setting.updateUrl ? <form onSubmit={event=>{event.preventDefault();form.put(setting.updateUrl!,{preserveScroll:true,onSuccess:()=>form.reset("reason")});}}>
   <label className="flex items-center gap-3 my-4"><input type="checkbox" checked={form.data.financials_visible} onChange={e=>form.setData("financials_visible",e.target.checked)} /><span>{t("teacher_visibility.toggle")}</span></label>
   <label className="block my-3"><span className="block mb-2">{t("teacher_visibility.reason")}</span><input className="w-full rounded-lg border border-[var(--line)] p-3" value={form.data.reason} onChange={e=>form.setData("reason",e.target.value)} required maxLength={1000} /></label>
   {Object.values(form.errors).map((error,i)=><p key={i} role="alert" className="text-red-700">{error}</p>)}
   <button className="rounded-lg bg-[var(--brand)] px-4 py-2 text-white disabled:opacity-50" disabled={form.processing || form.data.financials_visible===setting.visible}>{t("teacher_visibility.save")}</button>
  </form> : <p>{t(setting.visible?"teacher_visibility.visible":"teacher_visibility.hidden")}</p>}
 </section>;
}
