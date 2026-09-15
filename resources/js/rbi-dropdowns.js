// Keep the original select name so existing Laravel row handling is unchanged.
// Custom values are written to its selected option; no sentinel is submitted.
document.addEventListener('change', (event) => {
    const select = event.target.closest('[data-rbi-select]');
    if (!select) return;
    const custom = select.closest('[data-rbi-dropdown]').querySelector('[data-rbi-custom]');
    const other = select.selectedOptions[0]?.hasAttribute('data-rbi-other');
    custom.hidden = !other;
    custom.disabled = !other;
    if (other) {
        select.selectedOptions[0].value = custom.value;
        custom.focus();
    }
});

document.addEventListener('input', (event) => {
    if (!event.target.matches('[data-rbi-custom]')) return;
    const select = event.target.closest('[data-rbi-dropdown]').querySelector('[data-rbi-select]');
    if (select.selectedOptions[0]?.hasAttribute('data-rbi-other')) select.selectedOptions[0].value = event.target.value;
});
