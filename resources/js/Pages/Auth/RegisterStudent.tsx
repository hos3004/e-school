import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent } from 'react';

import Button from '@/Components/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/lib/i18n';

interface Option {
    id: string;
    name: string;
    iso2?: string;
}

interface RegistrationFormSummary {
    slug: string;
    title: string;
    description: string;
}

interface EvalQuestion {
    id: string;
    question: string;
    type: 'text' | 'textarea' | 'select' | 'radio' | 'checkbox' | 'number';
    options: string[] | null;
    required: boolean;
}

interface Props {
    registrationForm: RegistrationFormSummary;
    submitUrl: string;
    countries?: readonly Option[];
    regions?: Record<string, readonly Option[]>;
    questions?: readonly EvalQuestion[];
}

type Answer = string | string[];

interface Data {
    full_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    gender: string;
    country_id: string;
    region_id: string;
    notes: string;
    evaluation: Record<string, Answer>;
}

type CoreField = Exclude<keyof Data, 'evaluation'>;

const inputClass =
    'mt-1 min-h-12 w-full rounded-[var(--radius-md)] border border-[var(--line-strong)] bg-[var(--surface)] px-4 text-base text-[var(--ink)] shadow-[0_1px_2px_rgb(20_37_54/0.04)] focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)] focus:ring-offset-2 focus:ring-offset-[var(--surface)] sm:text-sm';

export default function RegisterStudent({
    registrationForm,
    submitUrl,
    countries = [],
    regions = {},
    questions = [],
}: Props) {
    const t = useI18n();
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const form = useForm<Data>({
        full_name: '',
        email: '',
        phone: '',
        date_of_birth: '',
        gender: '',
        country_id: '',
        region_id: '',
        notes: '',
        evaluation: {},
    });
    const errors = form.errors as Record<string, string | undefined>;

    useEffect(() => {
        if (form.hasErrors) {
            errorSummaryRef.current?.focus();
        }
    }, [form.hasErrors]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(submitUrl, { preserveScroll: true });
    };

    const setAnswer = (questionId: string, value: Answer) => {
        form.setData('evaluation', {
            ...form.data.evaluation,
            [questionId]: value,
        });
    };

    const field = (
        name: CoreField,
        label: string,
        type = 'text',
        required = true,
    ) => {
        const errorId = `${name}-error`;

        return (
            <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor={name}>
                <span>
                    {label}
                    {!required && (
                        <span className="ms-2 text-xs font-normal text-[var(--ink-muted)]">
                            {t('auth.register.optional')}
                        </span>
                    )}
                </span>
                <input
                    id={name}
                    className={inputClass}
                    type={type}
                    value={form.data[name]}
                    required={required}
                    aria-invalid={Boolean(errors[name])}
                    aria-describedby={errors[name] ? errorId : undefined}
                    onChange={(event) => form.setData(name, event.target.value)}
                />
                {errors[name] && (
                    <span id={errorId} className="mt-1 block text-xs text-[var(--danger)]">
                        {errors[name]}
                    </span>
                )}
            </label>
        );
    };

    const questionField = (question: EvalQuestion) => {
        const answer = form.data.evaluation[question.id];
        const error = errors[`evaluation.${question.id}`];
        const errorId = `evaluation-${question.id}-error`;
        const legend = (
            <span>
                {question.question}
                {!question.required && (
                    <span className="ms-2 text-xs font-normal text-[var(--ink-muted)]">
                        {t('auth.register.optional')}
                    </span>
                )}
            </span>
        );

        if (question.type === 'radio' || question.type === 'checkbox') {
            const selected = Array.isArray(answer) ? answer : [];

            return (
                <fieldset
                    key={question.id}
                    className="space-y-3"
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? errorId : undefined}
                >
                    <legend className="text-sm font-semibold text-[var(--ink)]">{legend}</legend>
                    <div className="space-y-2">
                        {(question.options ?? []).map((option) => (
                            <label key={option} className="flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] border border-[var(--line)] px-3 text-sm text-[var(--ink)] hover:bg-[var(--surface-subtle)] focus-within:border-[var(--brand)] focus-within:ring-2 focus-within:ring-[var(--focus-ring)]">
                                <input
                                    type={question.type}
                                    name={`evaluation.${question.id}`}
                                    value={option}
                                    checked={question.type === 'radio' ? answer === option : selected.includes(option)}
                                    required={question.required && question.type === 'radio'}
                                    onChange={(event) => {
                                        if (question.type === 'radio') {
                                            setAnswer(question.id, option);
                                            return;
                                        }

                                        setAnswer(
                                            question.id,
                                            event.target.checked
                                                ? [...selected, option]
                                                : selected.filter((value) => value !== option),
                                        );
                                    }}
                                />
                                <span>{option}</span>
                            </label>
                        ))}
                    </div>
                    {error && <p id={errorId} className="text-xs text-[var(--danger)]">{error}</p>}
                </fieldset>
            );
        }

        return (
            <label key={question.id} className="block text-sm font-semibold text-[var(--ink)]" htmlFor={`evaluation-${question.id}`}>
                {legend}
                {question.type === 'textarea' ? (
                    <textarea
                        id={`evaluation-${question.id}`}
                        className={`${inputClass} p-3`}
                        rows={4}
                        value={typeof answer === 'string' ? answer : ''}
                        required={question.required}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(event) => setAnswer(question.id, event.target.value)}
                    />
                ) : question.type === 'select' ? (
                    <select
                        id={`evaluation-${question.id}`}
                        className={inputClass}
                        value={typeof answer === 'string' ? answer : ''}
                        required={question.required}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(event) => setAnswer(question.id, event.target.value)}
                    >
                        <option value="">{t('auth.register.choose')}</option>
                        {(question.options ?? []).map((option) => (
                            <option key={option} value={option}>{option}</option>
                        ))}
                    </select>
                ) : (
                    <input
                        id={`evaluation-${question.id}`}
                        className={inputClass}
                        type={question.type === 'number' ? 'number' : 'text'}
                        value={typeof answer === 'string' ? answer : ''}
                        required={question.required}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(event) => setAnswer(question.id, event.target.value)}
                    />
                )}
                {error && <span id={errorId} className="mt-1 block text-xs text-[var(--danger)]">{error}</span>}
            </label>
        );
    };

    return (
        <GuestLayout>
            <Head title={registrationForm.title} />
            <div className="text-center">
                <h1 className="text-2xl font-semibold leading-tight tracking-[-0.02em] text-[var(--ink)] [text-wrap:balance]">{registrationForm.title}</h1>
                {registrationForm.description && (
                    <p className="mt-2 whitespace-pre-line text-sm leading-6 text-[var(--ink-muted)] [text-wrap:pretty]">
                        {registrationForm.description}
                    </p>
                )}
            </div>

            {form.hasErrors && (
                <div ref={errorSummaryRef} role="alert" tabIndex={-1} className="mt-5 rounded-[var(--radius-md)] border border-[color:var(--danger)]/30 bg-[var(--danger-soft)] p-3 text-sm font-medium text-[var(--danger)]">
                    {t('auth.register.validation_error')}
                </div>
            )}

            <form className="mt-7 space-y-6" onSubmit={submit}>
                <fieldset className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-subtle)] p-5">
                    <legend className="px-2 text-sm font-semibold text-[var(--ink)]">
                        {t('auth.register.student_information')}
                    </legend>
                    {field('full_name', t('auth.register.full_name'))}
                    {field('date_of_birth', t('auth.register.date_of_birth'), 'date')}
                    <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="gender">
                        {t('auth.register.gender')}
                        <select id="gender" className={inputClass} value={form.data.gender} required aria-invalid={Boolean(errors.gender)} aria-describedby={errors.gender ? 'gender-error' : undefined} onChange={(event) => form.setData('gender', event.target.value)}>
                            <option value="">{t('auth.register.choose')}</option>
                            <option value="male">{t('auth.register.male')}</option>
                            <option value="female">{t('auth.register.female')}</option>
                        </select>
                        {errors.gender && <span id="gender-error" className="mt-1 block text-xs text-[var(--danger)]">{errors.gender}</span>}
                    </label>
                    <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="country_id">
                        {t('auth.register.country')}
                        <select id="country_id" className={inputClass} value={form.data.country_id} required aria-invalid={Boolean(errors.country_id)} aria-describedby={errors.country_id ? 'country-error' : undefined} onChange={(event) => {
                            form.setData('country_id', event.target.value);
                            form.setData('region_id', '');
                        }}>
                            <option value="">{t('auth.register.choose')}</option>
                            {countries.map((country) => <option key={country.id} value={country.id}>{country.name}</option>)}
                        </select>
                        {errors.country_id && <span id="country-error" className="mt-1 block text-xs text-[var(--danger)]">{errors.country_id}</span>}
                    </label>
                    <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="region_id">
                        {t('auth.register.region')}
                        <select id="region_id" className={inputClass} value={form.data.region_id} required aria-invalid={Boolean(errors.region_id)} aria-describedby={errors.region_id ? 'region-error' : undefined} onChange={(event) => form.setData('region_id', event.target.value)}>
                            <option value="">{t('auth.register.choose')}</option>
                            {(regions[form.data.country_id] ?? []).map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}
                        </select>
                        {errors.region_id && <span id="region-error" className="mt-1 block text-xs text-[var(--danger)]">{errors.region_id}</span>}
                    </label>
                </fieldset>

                <fieldset className="space-y-4 rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-subtle)] p-5">
                    <legend className="px-2 text-sm font-semibold text-[var(--ink)]">
                        {t('auth.register.contact_information')}
                    </legend>
                    <p className="text-xs text-[var(--ink-muted)]">{t('auth.register.contact_help')}</p>
                    {field('email', t('auth.register.email'), 'email', false)}
                    {field('phone', t('auth.register.phone'), 'tel', false)}
                </fieldset>

                {questions.length > 0 && (
                    <fieldset className="space-y-5 rounded-[var(--radius-lg)] border border-[var(--line)] bg-[var(--surface-subtle)] p-5">
                        <legend className="px-2 text-sm font-semibold text-[var(--ink)]">
                            {t('auth.register.evaluation_questions')}
                        </legend>
                        {questions.map(questionField)}
                    </fieldset>
                )}

                <label className="block text-sm font-semibold text-[var(--ink)]" htmlFor="notes">
                    {t('auth.register.notes')}
                    <span className="ms-2 text-xs font-normal text-[var(--ink-muted)]">{t('auth.register.optional')}</span>
                    <textarea id="notes" className={`${inputClass} p-3`} rows={4} value={form.data.notes} aria-invalid={Boolean(errors.notes)} aria-describedby={errors.notes ? 'notes-error' : undefined} onChange={(event) => form.setData('notes', event.target.value)} />
                    {errors.notes && <span id="notes-error" className="mt-1 block text-xs text-[var(--danger)]">{errors.notes}</span>}
                </label>

                <Button disabled={form.processing} fullWidth type="submit">
                    {form.processing ? t('auth.register.submitting') : t('auth.register.submit')}
                </Button>
            </form>
        </GuestLayout>
    );
}
