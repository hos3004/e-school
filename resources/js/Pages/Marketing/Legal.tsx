import PageHero from '@/Components/Marketing/PageHero';
import { Reveal } from '@/Components/Marketing/Reveal';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

export default function Legal({ document }: { document: 'privacy' | 'terms' }) {
    const t = useI18n();
    const key = `marketing.legal.${document}`;
    return <MarketingLayout><Seo title={t(`${key}.title`)} description={t(`${key}.description`)} /><PageHero eyebrow={t('marketing.legal.eyebrow')} title={t(`${key}.title`)} description={t(`${key}.description`)} image="/images/marketing/facebook-identity.jpg" imageAlt={t('marketing.legal.image_alt')} /><section className="marketing-section bg-white"><div className="marketing-container max-w-4xl">{Array.from({ length: 6 }, (_, index) => <Reveal key={index} className="mb-10"><h2 className="text-2xl font-semibold">{t(`${key}.sections.${index}.title`)}</h2><p className="mt-4 leading-8 text-[var(--marketing-muted)]">{t(`${key}.sections.${index}.text`)}</p></Reveal>)}</div></section></MarketingLayout>;
}

