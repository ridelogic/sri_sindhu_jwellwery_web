/**
 * FAQ accordion built on native <details>; keeps only one answer open at a time
 * within each [data-faq] list.
 */
export function initFaq() {
    document.querySelectorAll('[data-faq]').forEach((list) => {
        const items = list.querySelectorAll('details');

        items.forEach((item) => {
            item.addEventListener('toggle', () => {
                if (!item.open) return;
                items.forEach((other) => {
                    if (other !== item) other.open = false;
                });
            });
        });
    });
}
