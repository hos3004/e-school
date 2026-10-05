import { useState, type FormEvent } from 'react';
import PageHero from '@/Components/Marketing/PageHero';
import { Reveal } from '@/Components/Marketing/Reveal';
import Seo from '@/Components/Marketing/Seo';
import MarketingLayout from '@/Layouts/MarketingLayout';
import { useI18n } from '@/lib/i18n';

export default function Contact() {
    const t = useI18n();
    const [name, setName] = useState('');
    const [interest, setInterest] = useState('quran');
    const [message, setMessage] = useState('');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const text = t('marketing.contact.whatsapp_template').replace(':name', name).replace(':interest', t(`marketing.contact.interests.${interest}`)).replace(':message', message);
        window.open(`https://wa.me/905301833478?text=${encodeURIComponent(text)}`, '_blank', 'noopener,noreferrer');
    };
    return <MarketingLayout>
        <Seo title={t('marketing.contact.seo_title')} description={t('marketing.contact.seo_description')} image="/images/marketing/facebook-join.jpg" />
        <PageHero eyebrow={t('marketing.contact.hero.eyebrow')} title={t('marketing.contact.hero.title')} description={t('marketing.contact.hero.description')} image="/images/marketing/facebook-join.jpg" imageAlt={t('marketing.contact.hero.image_alt')} />
        <section className="marketing-section bg-white"><div className="marketing-container grid gap-12 lg:grid-cols-[0.8fr_1.2fr]"><div className="space-y-5">{Array.from({ length: 3 }, (_, index) => <Reveal key={index} delay={index * 60} className="marketing-mini-card"><span>0{index + 1}</span><h2>{t(`marketing.contact.channels.${index}.title`)}</h2><p>{t(`marketing.contact.channels.${index}.text`)}</p>{index === 0 && <a className="mt-4 inline-flex font-semibold text-[var(--marketing-blue)]" href="https://wa.me/905301833478" target="_blank" rel="noreferrer">{t('marketing.contact.open_whatsapp')} ←</a>}{index === 1 && <a className="mt-4 inline-flex font-semibold text-[var(--marketing-blue)]" href="tel:+905301833478" dir="ltr">+90 530 183 34 78</a>}</Reveal>)}</div><Reveal className="marketing-contact-form"><p className="marketing-eyebrow">{t('marketing.contact.form.eyebrow')}</p><h2 className="mt-4 text-3xl font-semibold">{t('marketing.contact.form.title')}</h2><p className="mt-4 leading-7 text-[var(--marketing-muted)]">{t('marketing.contact.form.description')}</p><form className="mt-8 space-y-5" onSubmit={submit}><label className="marketing-field"><span>{t('marketing.contact.form.name')}</span><input required value={name} onChange={(event) => setName(event.target.value)} /></label><label className="marketing-field"><span>{t('marketing.contact.form.interest')}</span><select value={interest} onChange={(event) => setInterest(event.target.value)}>{['quran', 'children', 'coding', 'professional', 'family'].map((item) => <option key={item} value={item}>{t(`marketing.contact.interests.${item}`)}</option>)}</select></label><label className="marketing-field"><span>{t('marketing.contact.form.message')}</span><textarea required rows={5} value={message} onChange={(event) => setMessage(event.target.value)} /></label><button type="submit" className="marketing-button marketing-button-primary w-full">{t('marketing.contact.form.submit')}</button><p className="text-xs leading-6 text-[var(--marketing-muted)]">{t('marketing.contact.form.note')}</p></form></Reveal></div></section>
    </MarketingLayout>;
}

