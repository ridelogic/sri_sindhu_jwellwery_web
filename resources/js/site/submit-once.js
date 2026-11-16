/**
 * Disables [data-submit-once] buttons after their form is submitted, to avoid
 * accidental double submission. The server enforces this too — this is UX only.
 * A button with data-saving-label (e.g. "Saving…") shows that text while the request is in flight.
 */
export function initSubmitOnce() {
    document.querySelectorAll('[data-submit-once]').forEach((button) => {
        const form = button.closest('form');
        if (!form) return;

        const original = button.innerHTML;

        form.addEventListener('submit', () => {
            // Defer so the button's own value is still sent with the form.
            setTimeout(() => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.classList.add('opacity-60', 'cursor-wait');
                if (button.dataset.savingLabel) button.textContent = button.dataset.savingLabel;
            }, 0);
        });

        // Coming back via the browser's back/forward cache: make the button usable again.
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) return;
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.classList.remove('opacity-60', 'cursor-wait');
            button.innerHTML = original;
        });
    });
}

/**
 * Fades out flash messages marked [data-autodismiss="<ms>"] (e.g. "Settings saved successfully!").
 */
export function initFlashAutodismiss() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-flash-status][data-autodismiss]').forEach((flash) => {
        const delay = Number(flash.dataset.autodismiss) || 5000;

        setTimeout(() => {
            if (reduceMotion) {
                flash.remove();
                return;
            }
            flash.style.transition = 'opacity 300ms ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 320);
        }, delay);
    });
}
