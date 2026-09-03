import { Link } from '@inertiajs/react';
import CtaSection from '@/Components/Marketing/CtaSection';
import ProgramCard from '@/Components/Marketing/ProgramCard';
import { Reveal, WordReveal } from '@/Components/Marketing/Reveal';
import SectionIntro from '@/Components/Marketing/SectionIntro';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';
import { programs } from './content';

const proofItems = ['live', 'flexible', 'followup'] as const;
const steps = ['discover', 'consult', 'start', 'grow'] as const;
const projects = [
    ['scratch', '/images/marketing/coding-boys.jpg'],
    ['python', '/images/marketing/python-lab.jpg'],
    ['quran', '/images/marketing/quran-girls-circle.jpg'],
] as const;
const activities = [
    ['premiere', '/images/marketing/creative-design.jpg'],
    ['family', '/images/marketing/facebook-values.jpg'],
    ['classes', '/images/marketing/facebook-distance.jpg'],
] as const;

export default function Home() {
    const t = useI18n();
    const schema = {
        '@context': 'https://schema.org', '@type': 'EducationalOrganization',
        name: 'Tele Course Academy', url: 'https://telecourse.org',
        description: t('marketing.home.seo_description'), telephone: '+90 530 183 34 78',
    };

    return (
        <MarketingLayout>
            <Seo title={t('marketing.home.seo_title')} description={t('marketing.home.seo_description')} image="/images/marketing/hero-learning.jpg" schema={schema} />
            <section className="marketing-hero">
                <div className="marketing-orb marketing-orb-one" aria-hidden="true" />
                <div className="marketing-orb marketing-orb-two" aria-hidden="true" />
                <div className="marketing-container relative grid min-h-[720px] items-center gap-12 py-16 lg:grid-cols-[0.88fr_1.12fr]">
                    <div className="relative z-10 max-w-[680px]">
                        <Reveal><p className="marketing-pill">{t('marketing.home.hero.eyebrow')}</p></Reveal>
                        <Reveal delay={70}><h1 className="mt-6 text-4xl font-semibold leading-[1.18] text-[var(--marketing-ink)] sm:text-5xl lg:text-7xl">{t('marketing.home.hero.title')}</h1></Reveal>
                        <Reveal delay={140}><p className="mt-7 max-w-2xl text-lg leading-9 text-[var(--marketing-muted)]">{t('marketing.home.hero.description')}</p></Reveal>
                        <Reveal delay={210} className="mt-8 flex flex-wrap gap-4"><Link href="/programs" className="marketing-button marketing-button-primary">{t('marketing.home.hero.primary')}</Link><Link href="/about" className="marketing-button marketing-button-ghost">{t('marketing.home.hero.secondary')}</Link></Reveal>
                        <Reveal delay={280} className="mt-10 grid gap-3 sm:grid-cols-3">{proofItems.map((item) => <div key={item} className="marketing-proof"><span className="marketing-proof-dot" /><span>{t(`marketing.home.proof.${item}`)}</span></div>)}</Reveal>
                    </div>
                    <Reveal delay={100} className="relative">
                        <div className="marketing-hero-image"><img src="/images/marketing/hero-learning.jpg" alt={t('marketing.home.hero.image_alt')} className="h-full w-full object-cover" fetchPriority="high" /></div>
                        <div className="marketing-float-card marketing-float-card-top"><strong>{t('marketing.home.hero.card_one_title')}</strong><span>{t('marketing.home.hero.card_one_text')}</span></div>
                        <div className="marketing-float-card marketing-float-card-bottom"><strong>{t('marketing.home.hero.card_two_title')}</strong><span>{t('marketing.home.hero.card_two_text')}</span></div>
                    </Reveal>
                </div>
            </section>

            <section className="marketing-section pt-24">
                <div className="marketing-container">
                    <SectionIntro eyebrow={t('marketing.home.tracks.eyebrow')} title={t('marketing.home.tracks.title')} description={t('marketing.home.tracks.description')} align="center" />
                    <div className="mt-12 grid gap-6 lg:grid-cols-2">
                        <Reveal className="marketing-track-card bg-[var(--marketing-navy)] text-white"><span className="marketing-track-number">01</span><h3>{t('marketing.home.tracks.skills_title')}</h3><p>{t('marketing.home.tracks.skills_text')}</p><Link href="/programs/coding">{t('marketing.home.tracks.skills_link')} ←</Link></Reveal>
                        <Reveal delay={90} className="marketing-track-card bg-[var(--marketing-gold-soft)] text-[var(--marketing-ink)]"><span className="marketing-track-number !border-[var(--marketing-gold)]/40">02</span><h3>{t('marketing.home.tracks.values_title')}</h3><p className="!text-[var(--marketing-muted)]">{t('marketing.home.tracks.values_text')}</p><Link className="!text-[var(--marketing-gold-dark)]" href="/programs/quran">{t('marketing.home.tracks.values_link')} ←</Link></Reveal>
                    </div>
                </div>
            </section>

            <section className="marketing-section bg-white">
                <div className="marketing-container">
                    <SectionIntro eyebrow={t('marketing.home.programs.eyebrow')} title={t('marketing.home.programs.title')} description={t('marketing.home.programs.description')} />
                    <div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">{programs.map((program, index) => <ProgramCard key={program.slug} program={program} delay={index * 60} />)}</div>
                </div>
            </section>

            <section className="marketing-section">
                <div className="marketing-container grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                    <div><SectionIntro eyebrow={t('marketing.home.process.eyebrow')} title={t('marketing.home.process.title')} description={t('marketing.home.process.description')} /><div className="mt-10 grid gap-4 sm:grid-cols-2">{steps.map((step, index) => <Reveal key={step} delay={index * 60} className="marketing-step"><span>0{index + 1}</span><h3>{t(`marketing.home.process.${step}_title`)}</h3><p>{t(`marketing.home.process.${step}_text`)}</p></Reveal>)}</div></div>
                    <Reveal className="marketing-image-frame aspect-[4/5]"><img src="/images/marketing/ai-girls.jpg" alt={t('marketing.home.process.image_alt')} className="h-full w-full object-cover" loading="lazy" /></Reveal>
                </div>
            </section>

            <section className="marketing-statement"><div className="marketing-container"><p className="marketing-eyebrow text-center !text-white/65">{t('marketing.home.statement.eyebrow')}</p><WordReveal text={t('marketing.home.statement.text')} className="mx-auto mt-8 max-w-5xl text-center" /></div></section>

            <section className="marketing-section bg-white">
                <div className="marketing-container">
                    <SectionIntro eyebrow={t('marketing.home.projects.eyebrow')} title={t('marketing.home.projects.title')} description={t('marketing.home.projects.description')} />
                    <div className="mt-12 grid gap-6 lg:grid-cols-3">{projects.map(([project, image], index) => <Reveal key={project} delay={index * 70} className="marketing-story-card"><img src={image} alt={t(`marketing.home.projects.${project}_alt`)} loading="lazy" /><div><p>{t(`marketing.home.projects.${project}_label`)}</p><h3>{t(`marketing.home.projects.${project}_title`)}</h3><span>{t(`marketing.home.projects.${project}_text`)}</span></div></Reveal>)}</div>
                    <Reveal className="mt-10"><Link href="/projects" className="marketing-button marketing-button-ghost">{t('marketing.home.projects.all')}</Link></Reveal>
                </div>
            </section>

            <section className="marketing-section">
                <div className="marketing-container">
                    <SectionIntro eyebrow={t('marketing.home.activities.eyebrow')} title={t('marketing.home.activities.title')} description={t('marketing.home.activities.description')} />
                    <div className="mt-12 grid gap-6 md:grid-cols-3">{activities.map(([activity, image], index) => <Reveal key={activity} delay={index * 70} className="marketing-activity-card"><img src={image} alt={t(`marketing.home.activities.${activity}_alt`)} loading="lazy" /><div><p>{t(`marketing.home.activities.${activity}_type`)}</p><h3>{t(`marketing.home.activities.${activity}_title`)}</h3><span>{t(`marketing.home.activities.${activity}_text`)}</span></div></Reveal>)}</div>
                </div>
            </section>

            <section className="marketing-section bg-white"><div className="marketing-container grid gap-12 lg:grid-cols-[0.75fr_1.25fr]"><SectionIntro eyebrow={t('marketing.home.faq.eyebrow')} title={t('marketing.home.faq.title')} description={t('marketing.home.faq.description')} /><div className="space-y-4">{Array.from({ length: 4 }, (_, index) => <Reveal key={index} delay={index * 50}><details className="marketing-faq"><summary>{t(`marketing.faq.items.${index}.question`)}</summary><p>{t(`marketing.faq.items.${index}.answer`)}</p></details></Reveal>)}<Link href="/faq" className="mt-6 inline-flex font-semibold text-[var(--marketing-blue)]">{t('marketing.home.faq.all')} ←</Link></div></div></section>
            <CtaSection />
        </MarketingLayout>
    );
}

