import CtaSection from '@/Components/Marketing/CtaSection';
import PageHero from '@/Components/Marketing/PageHero';
import { Reveal } from '@/Components/Marketing/Reveal';
import SectionIntro from '@/Components/Marketing/SectionIntro';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

const activities = [
    ['premiere', '/images/marketing/creative-design.jpg'], ['children_code', '/images/marketing/robotics.jpg'],
    ['family_talk', '/images/marketing/facebook-values.jpg'], ['quran_classes', '/images/marketing/quran-girls-circle.jpg'],
    ['student_showcase', '/images/marketing/facebook-join.jpg'], ['academy_story', '/images/marketing/facebook-distance.jpg'],
] as const;

export default function Activities() {
    const t = useI18n();
    return <MarketingLayout>
        <Seo title={t('marketing.activities_page.seo_title')} description={t('marketing.activities_page.seo_description')} image="/images/marketing/creative-design.jpg" />
        <PageHero eyebrow={t('marketing.activities_page.hero.eyebrow')} title={t('marketing.activities_page.hero.title')} description={t('marketing.activities_page.hero.description')} image="/images/marketing/creative-design.jpg" imageAlt={t('marketing.activities_page.hero.image_alt')} />
        <section className="marketing-section bg-white"><div className="marketing-container"><SectionIntro eyebrow={t('marketing.activities_page.list.eyebrow')} title={t('marketing.activities_page.list.title')} description={t('marketing.activities_page.list.description')} /><div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">{activities.map(([activity, image], index) => <Reveal key={activity} delay={(index % 3) * 60} className="marketing-activity-card"><img src={image} alt={t(`marketing.activities_page.items.${activity}.alt`)} loading="lazy" /><div><p>{t(`marketing.activities_page.items.${activity}.type`)}</p><h2>{t(`marketing.activities_page.items.${activity}.title`)}</h2><span>{t(`marketing.activities_page.items.${activity}.text`)}</span></div></Reveal>)}</div></div></section>
        <section className="marketing-section"><div className="marketing-container"><SectionIntro eyebrow={t('marketing.activities_page.formats.eyebrow')} title={t('marketing.activities_page.formats.title')} description={t('marketing.activities_page.formats.description')} align="center" /><div className="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-4">{Array.from({ length: 4 }, (_, index) => <Reveal key={index} delay={index * 60} className="marketing-mini-card"><span>0{index + 1}</span><h3>{t(`marketing.activities_page.formats.items.${index}.title`)}</h3><p>{t(`marketing.activities_page.formats.items.${index}.text`)}</p></Reveal>)}</div></div></section>
        <CtaSection />
    </MarketingLayout>;
}

