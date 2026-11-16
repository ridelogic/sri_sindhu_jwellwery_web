/**
 * Admin plan form helpers (display only — the server validates everything):
 *  - Plan Tenure Unit: Maximum Tenure follows the unit; Unlimited disables and clears it;
 *  - Maximum Amount: "Unlimited" disables and clears it;
 *  - Bonus Type / Bonus Value: enabled only when Bonus Applicable = Yes;
 *  - slug suggestion from the name for new plans.
 */
export function initAdminPlanForm() {
    const form = document.querySelector('[data-plan-form]');
    if (!form) return;

    // Tenure unit → maximum tenure ---------------------------------------------------
    const unit = form.querySelector('[data-tenure-unit]');
    const tenure = form.querySelector('[data-tenure-input]');
    const tenureLabel = tenure ? form.querySelector(`label[for="${tenure.id}"]`) : null;

    const syncTenure = () => {
        if (!unit || !tenure) return;
        const unlimited = unit.value === 'unlimited';
        tenure.disabled = unlimited;
        tenure.required = !unlimited;
        if (unlimited) tenure.value = '';
        if (tenureLabel?.firstChild) {
            tenureLabel.firstChild.textContent = `Maximum Tenure Allowed (${unlimited ? 'not applicable' : unit.value}) `;
        }
    };
    unit?.addEventListener('change', syncTenure);
    syncTenure();

    // Unlimited maximum amount ---------------------------------------------------------
    const maxInput = form.querySelector('[data-max-amount-input]');
    const maxUnlimited = form.querySelector('[data-max-amount-unlimited]');

    const syncMax = () => {
        if (!maxInput || !maxUnlimited) return;
        maxInput.disabled = maxUnlimited.checked;
        maxInput.required = !maxUnlimited.checked;
        if (maxUnlimited.checked) maxInput.value = '';
    };
    maxUnlimited?.addEventListener('change', syncMax);
    syncMax();

    // Bonus applicable → bonus type / value ------------------------------------------------
    const bonus = form.querySelector('[data-bonus-applicable]');
    const bonusFields = form.querySelectorAll('[data-bonus-field]');

    const syncBonus = () => {
        if (!bonus) return;
        const enabled = bonus.value === '1';
        bonusFields.forEach((field) => {
            field.disabled = !enabled;
            field.required = enabled;
            if (!enabled) field.value = '';
        });
    };
    bonus?.addEventListener('change', syncBonus);
    syncBonus();

    // Slug suggestion -------------------------------------------------------------
    const name = form.querySelector('[data-plan-name]');
    const slug = form.querySelector('[data-plan-slug]');
    const slugify = (value) => value.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');

    if (name && slug && slug.dataset.slugAuto === 'true') {
        let touched = slug.value !== '';
        slug.addEventListener('input', () => { touched = true; });
        name.addEventListener('input', () => {
            if (!touched) slug.value = slugify(name.value);
        });
    }
}
