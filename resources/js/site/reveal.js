/**
 * Fades elements marked with [data-reveal] into view as they enter the viewport.
 * An optional data-reveal-delay="150" staggers sibling elements.
 */
export function initReveal() {
    const elements = document.querySelectorAll('[data-reveal]');
    if (!elements.length) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducedMotion || !('IntersectionObserver' in window)) {
        elements.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.12 },
    );

    elements.forEach((el) => {
        if (el.dataset.revealDelay) {
            el.style.setProperty('--reveal-delay', `${el.dataset.revealDelay}ms`);
        }
        observer.observe(el);
    });
}
