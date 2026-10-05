import { Link } from '@inertiajs/react';
import CtaSection from '@/Components/Marketing/CtaSection';
import PageHero from '@/Components/Marketing/PageHero';
import { Reveal } from '@/Components/Marketing/Reveal';
import SectionIntro from '@/Components/Marketing/SectionIntro';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

const projects = [
    ['scratch_game', '/images/marketing/coding-boys.jpg'], ['python_start', '/images/marketing/python-lab.jpg'],
    ['ai_experiment', '/images/marketing/ai-girls.jpg'], ['robotics', '/images/marketing/robotics.jpg'],
    ['quran_progress', '/images/marketing/quran-boy.jpg'], ['creative_video', '/images/marketing/creative-design.jpg'],
] as const;

export default function Projects() {
    const t = useI18n();
    return <MarketingLayout>
        <Seo title={t('marketing.projects_page.seo_title')} description={t('marketing.projects_page.seo_description')} image="/images/marketing/coding-boys.jpg" />
        <PageHero eyebrow={t('marketing.projects_page.hero.eyebrow')} title={t('marketing.projects_page.hero.title')} description={t('marketing.projects_page.hero.description')} image="/images/marketing/coding-boys.jpg" imageAlt={t('marketing.projects_page.hero.image_alt')} />
        <section className="marketing-section bg-white"><div className="marketing-container"><SectionIntro eyebrow={t('marketing.projects_page.grid.eyebrow')} title={t('marketing.projects_page.grid.title')} description={t('marketing.projects_page.grid.description')} /><div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">{projects.map(([project, image], index) => <Reveal key={project} delay={(index % 3) * 60} className="marketing-project-card"><div className="aspect-[4/3] overflow-hidden"><img src={image} alt={t(`marketing.projects_page.items.${project}.alt`)} loading="lazy" className="h-full w-full object-cover" /></div><div className="p-6"><p className="marketing-eyebrow">{t(`marketing.projects_page.items.${project}.category`)}</p><h2 className="mt-3 text-2xl font-semibold">{t(`marketing.projects_page.items.${project}.title`)}</h2><p className="mt-4 leading-7 text-[var(--marketing-muted)]">{t(`marketing.projects_page.items.${project}.text`)}</p></div></Reveal>)}</div></div></section>
        <section className="marketing-section"><div className="marketing-container grid items-center gap-12 lg:grid-cols-2"><Reveal className="marketing-image-frame aspect-[4/3]"><img src="/images/marketing/app-development.jpg" alt={t('marketing.projects_page.process.image_alt')} className="h-full w-full object-cover" loading="lazy" /></Reveal><div><SectionIntro eyebrow={t('marketing.projects_page.process.eyebrow')} title={t('marketing.projects_page.process.title')} description={t('marketing.projects_page.process.description')} />{Array.from({ length: 4 }, (_, index) => <Reveal key={index} delay={index * 55} className="marketing-module mt-4"><span>0{index + 1}</span><div><h3>{t(`marketing.projects_page.process.items.${index}.title`)}</h3><p>{t(`marketing.projects_page.process.items.${index}.text`)}</p></div></Reveal>)}<Link href="/programs" className="marketing-button marketing-button-primary mt-8">{t('marketing.common.explore_programs')}</Link></div></div></section>
        <CtaSection />
    </MarketingLayout>;
}

