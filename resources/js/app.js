import './bootstrap';
import { initHeader, initMobileNav } from './site/navigation';
import { initReveal } from './site/reveal';
import { initFaq } from './site/faq';
import { initRateCharts } from './site/rate-chart';
import { initPasswordToggles } from './site/password-toggle';
import { initAdminDrawer } from './site/admin-drawer';
import { initAdminRateForm } from './site/admin-rate-form';
import { initAdminPlanForm } from './site/admin-plan-form';
import { initFlashAutodismiss, initSubmitOnce } from './site/submit-once';
import { initOtpCountdown } from './site/otp-countdown';

document.addEventListener('DOMContentLoaded', () => {
    initHeader();
    initMobileNav();
    initReveal();
    initFaq();
    initRateCharts();
    initPasswordToggles();
    initAdminDrawer();
    initAdminRateForm();
    initAdminPlanForm();
    initSubmitOnce();
    initFlashAutodismiss();
    initOtpCountdown();
});
