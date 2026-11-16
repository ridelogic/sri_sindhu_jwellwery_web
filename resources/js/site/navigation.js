/**
 * Turns the transparent header solid once the page scrolls past the top.
 */
export function initHeader() {
    const header = document.querySelector('[data-site-header]');
    if (!header) return;

    const update = () => {
        header.dataset.scrolled = window.scrollY > 24 ? 'true' : 'false';
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
}

/**
 * Hamburger menu for tablet/mobile widths.
 */
export function initMobileNav() {
    const header = document.querySelector('[data-site-header]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const panel = document.querySelector('[data-mobile-nav]');
    if (!toggle || !panel) return;

    const setOpen = (open) => {
        panel.dataset.open = open ? 'true' : 'false';
        if (header) header.dataset.menuOpen = open ? 'true' : 'false';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        toggle.querySelector('[data-icon-open]')?.classList.toggle('hidden', open);
        toggle.querySelector('[data-icon-close]')?.classList.toggle('hidden', !open);
    };

    toggle.addEventListener('click', () => setOpen(panel.dataset.open !== 'true'));

    // Close after choosing a link or pressing Escape.
    panel.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && panel.dataset.open === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });

    // Reset when resizing up to the desktop layout.
    window.matchMedia('(min-width: 1280px)').addEventListener('change', (event) => {
        if (event.matches) setOpen(false);
    });
}
