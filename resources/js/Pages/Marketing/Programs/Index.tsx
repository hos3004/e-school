import CtaSection from '@/Components/Marketing/CtaSection';
import PageHero from '@/Components/Marketing/PageHero';
import ProgramCard from '@/Components/Marketing/ProgramCard';
import { Reveal } from '@/Components/Marketing/Reveal';
import SectionIntro from '@/Components/Marketing/SectionIntro';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';
import { programs } from '../content';

export default function Programs() {
    const t = useI18n();
    return <MarketingLayout>
        <Seo title={t('marketing.programs_page.seo_title')} description={t('marketing.programs_page.seo_description')} image="/images/marketing/ai-girls.jpg" />
        <PageHero eyebrow={t('marketing.programs_page.hero.eyebrow')} title={t('marketing.programs_page.hero.title')} description={t('marketing.programs_page.hero.description')} image="/images/marketing/ai-girls.jpg" imageAlt={t('marketing.programs_page.hero.image_alt')} />
        <section className="marketing-section bg-white"><div className="marketing-container"><SectionIntro eyebrow={t('marketing.programs_page.list.eyebrow')} title={t('marketing.programs_page.list.title')} description={t('marketing.programs_page.list.description')} /><div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">{programs.map((program, index) => <ProgramCard key={program.slug} program={program} delay={index * 60} />)}</div></div></section>
        <section className="marketing-section"><div className="marketing-container"><SectionIntro eyebrow={t('marketing.programs_page.guide.eyebrow')} title={t('marketing.programs_page.guide.title')} description={t('marketing.programs_page.guide.description')} align="center" /><div className="mt-12 grid gap-6 md:grid-cols-3">{Array.from({ length: 3 }, (_, index) => <Reveal key={index} delay={index * 70} className="marketing-mini-card"><span>0{index + 1}</span><h3>{t(`marketing.programs_page.guide.items.${index}.title`)}</h3><p>{t(`marketing.programs_page.guide.items.${index}.text`)}</p></Reveal>)}</div></div></section>
        <CtaSection />
    </MarketingLayout>;
}

