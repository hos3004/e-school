import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';

interface RevealProps {
    children: ReactNode;
    className?: string;
    delay?: number;
}

export function Reveal({ children, className = '', delay = 0 }: RevealProps) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const element = ref.current;
        if (!element) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    setVisible(true);
                    observer.unobserve(element);
                }
            },
            { threshold: 0.2, rootMargin: '0px 0px -8% 0px' },
        );

        observer.observe(element);
        return () => observer.disconnect();
    }, []);

    const style = { '--reveal-delay': `${delay}ms` } as CSSProperties;

    return <div ref={ref} className={`marketing-reveal ${visible ? 'is-visible' : ''} ${className}`} style={style}>{children}</div>;
}

export function WordReveal({ text, className = '' }: { text: string; className?: string }) {
    const words = text.split(' ');
    return (
        <Reveal className={className}>
            <p className="marketing-word-reveal" aria-label={text}>
                {words.map((word, index) => (
                    <span key={`${word}-${index}`} aria-hidden="true" style={{ '--word-index': index } as CSSProperties}>{word}</span>
                ))}
            </p>
        </Reveal>
    );
}

