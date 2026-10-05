import { Link } from '@inertiajs/react';
import CtaSection from '@/Components/Marketing/CtaSection';
import PageHero from '@/Components/Marketing/PageHero';
import { Reveal, WordReveal } from '@/Components/Marketing/Reveal';
import SectionIntro from '@/Components/Marketing/SectionIntro';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

export default function About() {
    const t = useI18n();
    return <MarketingLayout>
        <Seo title={t('marketing.about.seo_title')} description={t('marketing.about.seo_description')} />
        <PageHero eyebrow={t('marketing.about.hero.eyebrow')} title={t('marketing.about.hero.title')} description={t('marketing.about.hero.description')} image="/images/marketing/brand-story.jpg" imageAlt={t('marketing.about.hero.image_alt')} />
        <section className="marketing-section bg-white"><div className="marketing-container grid gap-6 lg:grid-cols-2"><Reveal className="marketing-value-panel"><p className="marketing-eyebrow">{t('marketing.about.mission.eyebrow')}</p><h2>{t('marketing.about.mission.title')}</h2><p>{t('marketing.about.mission.text')}</p></Reveal><Reveal delay={80} className="marketing-value-panel accent"><p className="marketing-eyebrow">{t('marketing.about.vision.eyebrow')}</p><h2>{t('marketing.about.vision.title')}</h2><p>{t('marketing.about.vision.text')}</p></Reveal></div></section>
        <section className="marketing-section"><div className="marketing-container"><SectionIntro eyebrow={t('marketing.about.values.eyebrow')} title={t('marketing.about.values.title')} description={t('marketing.about.values.description')} align="center" /><div className="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-4">{Array.from({ length: 4 }, (_, index) => <Reveal key={index} delay={index * 60} className="marketing-mini-card"><span>0{index + 1}</span><h3>{t(`marketing.about.values.items.${index}.title`)}</h3><p>{t(`marketing.about.values.items.${index}.text`)}</p></Reveal>)}</div></div></section>
        <section className="marketing-statement"><div className="marketing-container"><WordReveal text={t('marketing.about.statement')} className="mx-auto max-w-5xl text-center" /></div></section>
        <section className="marketing-section bg-white"><div className="marketing-container grid items-center gap-12 lg:grid-cols-2"><Reveal className="marketing-image-frame aspect-[4/3]"><img src="/images/marketing/quran-hero.jpg" alt={t('marketing.about.dual.image_alt')} className="h-full w-full object-cover" loading="lazy" /></Reveal><div><SectionIntro eyebrow={t('marketing.about.dual.eyebrow')} title={t('marketing.about.dual.title')} description={t('marketing.about.dual.description')} />{Array.from({ length: 3 }, (_, index) => <Reveal key={index} delay={index * 60} className="mt-6 border-s-2 border-[var(--marketing-line)] ps-5"><h3 className="font-semibold">{t(`marketing.about.dual.items.${index}.title`)}</h3><p className="mt-2 leading-7 text-[var(--marketing-muted)]">{t(`marketing.about.dual.items.${index}.text`)}</p></Reveal>)}<Link href="/programs" className="marketing-button marketing-button-primary mt-8">{t('marketing.common.explore_programs')}</Link></div></div></section>
        <CtaSection />
    </MarketingLayout>;
}

