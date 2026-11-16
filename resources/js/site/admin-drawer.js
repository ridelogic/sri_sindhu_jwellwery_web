/**
 * Header dropdowns (<details data-admin-dropdown>): only one open at a time,
 * closed by an outside click or Escape. They also work without JavaScript.
 */
function initAdminDropdowns() {
    const dropdowns = [...document.querySelectorAll('[data-admin-dropdown]')];
    if (!dropdowns.length) return;

    dropdowns.forEach((dropdown) => {
        dropdown.addEventListener('toggle', () => {
            if (dropdown.open) dropdowns.filter((other) => other !== dropdown).forEach((other) => (other.open = false));
        });
    });
    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown) => {
            if (dropdown.open && !dropdown.contains(event.target)) dropdown.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') dropdowns.forEach((dropdown) => (dropdown.open = false));
    });
}

/**
 * Admin sidebar drawer for screens below lg.
 */
export function initAdminDrawer() {
    initAdminDropdowns();

    const drawer = document.querySelector('[data-admin-drawer]');
    if (!drawer) return;

    const overlay = document.querySelector('[data-admin-overlay]');
    const openButton = document.querySelector('[data-admin-drawer-open]');
    const closeButton = drawer.querySelector('[data-admin-drawer-close]');

    const setOpen = (open) => {
        drawer.classList.toggle('-translate-x-full', !open);
        overlay?.classList.toggle('hidden', !open);
        openButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('overflow-hidden', open);
        if (open) closeButton?.focus();
    };

    openButton?.addEventListener('click', () => setOpen(true));
    closeButton?.addEventListener('click', () => {
        setOpen(false);
        openButton?.focus();
    });
    overlay?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) setOpen(false);
    });
}
