/**
 * "Resend OTP" countdown. Display only — the server enforces the cooldown.
 * <button data-resend-countdown="42"><span data-resend-label>…</span></button>
 */
export function initOtpCountdown() {
    document.querySelectorAll('[data-resend-countdown]').forEach((button) => {
        let seconds = Number.parseInt(button.dataset.resendCountdown, 10) || 0;
        const label = button.querySelector('[data-resend-label]') ?? button;

        const render = () => {
            button.disabled = seconds > 0;
            label.textContent = seconds > 0 ? `Resend OTP in ${seconds}s` : 'Resend OTP';
        };

        render();
        if (seconds <= 0) return;

        const timer = window.setInterval(() => {
            seconds -= 1;
            render();
            if (seconds <= 0) window.clearInterval(timer);
        }, 1000);
    });
}
