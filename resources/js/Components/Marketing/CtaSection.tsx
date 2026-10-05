import { Link } from '@inertiajs/react';
import { useI18n } from '@/lib/i18n';
import { Reveal } from './Reveal';

export default function CtaSection() {
    const t = useI18n();
    return (
        <section className="marketing-section">
            <div className="marketing-container">
                <Reveal className="marketing-cta">
                    <div className="max-w-3xl">
                        <p className="marketing-eyebrow !text-white/75">{t('marketing.cta.eyebrow')}</p>
                        <h2 className="mt-4 text-3xl font-semibold leading-tight text-white md:text-5xl">{t('marketing.cta.title')}</h2>
                        <p className="mt-6 max-w-2xl text-base leading-8 text-white/75 md:text-lg">{t('marketing.cta.description')}</p>
                    </div>
                    <div className="mt-8 flex flex-wrap gap-4">
                        <Link href="/register/student" className="marketing-button bg-white text-[var(--marketing-navy)] hover:bg-white/90">{t('marketing.cta.primary')}</Link>
                        <Link href="/contact" className="marketing-button border border-white/25 bg-white/10 text-white hover:bg-white/15">{t('marketing.cta.secondary')}</Link>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}

