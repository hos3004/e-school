import { Link } from '@inertiajs/react';
import CtaSection from '@/Components/Marketing/CtaSection';
import { Reveal } from '@/Components/Marketing/Reveal';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';
import { getProgram, type ProgramSlug } from '../content';

export default function ProgramShow({ program: slug }: { program: ProgramSlug }) {
    const t = useI18n();
    const program = getProgram(slug);
    const key = `marketing.programs.${program.slug}`;
    const schema = { '@context': 'https://schema.org', '@type': 'Course', name: t(`${key}.title`), description: t(`${key}.description`), provider: { '@type': 'EducationalOrganization', name: 'Tele Course Academy' } };
    return <MarketingLayout>
        <Seo title={t(`${key}.seo_title`)} description={t(`${key}.summary`)} image={program.image} schema={schema} />
        <section className={`marketing-program-hero accent-${program.accent}`}><div className="marketing-container grid min-h-[680px] items-center gap-12 py-16 lg:grid-cols-[0.9fr_1.1fr]"><Reveal><p className="marketing-pill">{t(`${key}.eyebrow`)}</p><h1 className="mt-6 text-4xl font-semibold leading-tight md:text-6xl">{t(`${key}.title`)}</h1><p className="mt-7 max-w-2xl text-lg leading-9 text-[var(--marketing-muted)]">{t(`${key}.description`)}</p><div className="mt-8 flex flex-wrap gap-4"><Link href="/register/student" className="marketing-button marketing-button-primary">{t('marketing.common.register_now')}</Link><Link href="/contact" className="marketing-button marketing-button-ghost">{t('marketing.common.ask_advisor')}</Link></div></Reveal><Reveal delay={100} className="marketing-image-frame aspect-[4/3]"><img src={program.image} alt={t(`${key}.image_alt`)} className="h-full w-full object-cover" /></Reveal></div></section>
        <section className="marketing-section bg-white"><div className="marketing-container grid gap-12 lg:grid-cols-2"><div><p className="marketing-eyebrow">{t('marketing.program_detail.outcomes')}</p><h2 className="mt-4 text-3xl font-semibold md:text-5xl">{t(`${key}.outcomes_title`)}</h2><div className="mt-8 space-y-4">{Array.from({ length: program.outcomes }, (_, index) => <Reveal key={index} delay={index * 45} className="marketing-check-item"><span>✓</span><p>{t(`${key}.outcomes.${index}`)}</p></Reveal>)}</div></div><div><p className="marketing-eyebrow">{t('marketing.program_detail.journey')}</p><h2 className="mt-4 text-3xl font-semibold md:text-5xl">{t(`${key}.modules_title`)}</h2><div className="mt-8 space-y-4">{Array.from({ length: program.modules }, (_, index) => <Reveal key={index} delay={index * 55} className="marketing-module"><span>0{index + 1}</span><div><h3>{t(`${key}.modules.${index}.title`)}</h3><p>{t(`${key}.modules.${index}.text`)}</p></div></Reveal>)}</div></div></div></section>
        <section className="marketing-section"><div className="marketing-container"><div className="grid gap-6 md:grid-cols-3">{['audience', 'delivery', 'support'].map((item, index) => <Reveal key={item} delay={index * 70} className="marketing-mini-card"><span>0{index + 1}</span><h3>{t(`${key}.${item}_title`)}</h3><p>{t(`${key}.${item}_text`)}</p></Reveal>)}</div><div className="mt-16 grid gap-6 md:grid-cols-3">{program.gallery.map((image, index) => <Reveal key={image} delay={index * 70} className="marketing-image-frame aspect-[4/3]"><img src={image} alt={t(`${key}.gallery_alt`)} className="h-full w-full object-cover" loading="lazy" /></Reveal>)}</div></div></section>
        <CtaSection />
    </MarketingLayout>;
}

