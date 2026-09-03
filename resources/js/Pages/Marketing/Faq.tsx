import CtaSection from '@/Components/Marketing/CtaSection';
import PageHero from '@/Components/Marketing/PageHero';
import { Reveal } from '@/Components/Marketing/Reveal';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

export default function Faq() {
    const t = useI18n();
    const items = Array.from({ length: 12 }, (_, index) => ({ question: t(`marketing.faq.items.${index}.question`), answer: t(`marketing.faq.items.${index}.answer`) }));
    const schema = { '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: items.map((item) => ({ '@type': 'Question', name: item.question, acceptedAnswer: { '@type': 'Answer', text: item.answer } })) };
    return <MarketingLayout>
        <Seo title={t('marketing.faq.seo_title')} description={t('marketing.faq.seo_description')} image="/images/marketing/facebook-identity.jpg" schema={schema} />
        <PageHero eyebrow={t('marketing.faq.hero.eyebrow')} title={t('marketing.faq.hero.title')} description={t('marketing.faq.hero.description')} image="/images/marketing/facebook-identity.jpg" imageAlt={t('marketing.faq.hero.image_alt')} />
        <section className="marketing-section bg-white"><div className="marketing-container max-w-4xl space-y-4">{items.map((item, index) => <Reveal key={item.question} delay={(index % 4) * 40}><details className="marketing-faq"><summary>{item.question}</summary><p>{item.answer}</p></details></Reveal>)}</div></section>
        <CtaSection />
    </MarketingLayout>;
}

