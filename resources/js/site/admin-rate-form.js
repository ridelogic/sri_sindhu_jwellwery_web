/**
 * Live preview of the 10-gram rate on the admin "Update Today's Rate" form.
 * Display only — the server always recalculates and stores its own value.
 */
export function initAdminRateForm() {
    const form = document.querySelector('[data-rate-form]');
    if (!form) return;

    const formatter = new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    form.querySelectorAll('[data-rate-input]').forEach((input) => {
        const output = form.querySelector(`[data-rate-10g="${input.dataset.rateInput}"]`);
        if (!output) return;

        const update = () => {
            const perGram = Number.parseFloat(input.value);
            output.textContent = Number.isFinite(perGram) && perGram > 0 ? formatter.format(Math.round(perGram * 10 * 100) / 100) : '—';
        };

        input.addEventListener('input', update);
        update();
    });
}
