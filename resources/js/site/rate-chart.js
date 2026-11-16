/**
 * Hover / touch crosshair and tooltip for the gold rate history charts.
 * Points are provided by the server in [data-rate-chart] as JSON.
 */
export function initRateCharts() {
    document.querySelectorAll('[data-rate-chart]').forEach((chart) => {
        let points;
        try {
            points = JSON.parse(chart.dataset.rateChart);
        } catch {
            return;
        }
        if (!points?.length) return;

        const plot = chart.querySelector('[data-plot]');
        const crosshair = chart.querySelector('[data-crosshair]');
        const dot = chart.querySelector('[data-dot]');
        const tooltip = chart.querySelector('[data-tooltip]');

        const show = (clientX) => {
            const rect = plot.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
            const index = Math.round(ratio * (points.length - 1));
            const point = points[index];

            crosshair.style.left = `${point.x}%`;
            dot.style.left = `${point.x}%`;
            dot.style.top = `${point.y}%`;
            tooltip.textContent = `${point.date} · ${point.value}/g`;
            // Keep the tooltip inside the plot near the edges.
            tooltip.style.left = `${Math.min(85, Math.max(15, point.x))}%`;

            [crosshair, dot, tooltip].forEach((el) => el.classList.remove('hidden'));
        };

        const hide = () => [crosshair, dot, tooltip].forEach((el) => el.classList.add('hidden'));

        // Hit target is the whole plot, larger than the line itself.
        plot.addEventListener('pointermove', (event) => show(event.clientX));
        plot.addEventListener('pointerdown', (event) => show(event.clientX));
        plot.addEventListener('pointerleave', hide);
    });
}
