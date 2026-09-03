import { Link } from '@inertiajs/react';
import { useI18n } from '@/lib/i18n';
import type { ProgramDefinition } from '@/Pages/Marketing/content';
import { Reveal } from './Reveal';

export default function ProgramCard({ program, delay = 0 }: { program: ProgramDefinition; delay?: number }) {
    const t = useI18n();
    const key = `marketing.programs.${program.slug}`;

    return (
        <Reveal delay={delay} className={`marketing-program-card accent-${program.accent}`}>
            <Link href={`/programs/${program.slug}`} className="group block h-full">
                <div className="aspect-[4/3] overflow-hidden rounded-[1.25rem]">
                    <img src={program.image} alt={t(`${key}.image_alt`)} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.035]" loading="lazy" />
                </div>
                <div className="p-6">
                    <p className="text-sm font-semibold text-[var(--program-accent)]">{t(`${key}.eyebrow`)}</p>
                    <h3 className="mt-3 text-2xl font-semibold text-[var(--marketing-ink)]">{t(`${key}.title`)}</h3>
                    <p className="mt-4 leading-7 text-[var(--marketing-muted)]">{t(`${key}.summary`)}</p>
                    <span className="mt-6 inline-flex items-center gap-2 font-semibold text-[var(--program-accent)]">
                        {t('marketing.common.explore_program')} <span aria-hidden="true">←</span>
                    </span>
                </div>
            </Link>
        </Reveal>
    );
}

