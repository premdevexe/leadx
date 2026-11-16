/* ============================================================
   LeadX — Form helpers
   ============================================================ */
import { $, $$, on, addClass, removeClass } from '../core/dom.js';

export function initForms() {
    initToggles();
    initAutoSubmit();
}

/* Toggle switches */
function initToggles() {
    $$('.form-switch input[type="checkbox"]').forEach((input) => {
        on(input, 'change', () => {
            const form = input.closest('form');
            if (form) {
                // Dispatch change event on the form for any listener
                const event = new Event('change', { bubbles: true });
                form.dispatchEvent(event);
            }
        });
    });
}

/* Auto-submit forms with [data-auto-submit] */
function initAutoSubmit() {
    $$('[data-auto-submit]').forEach((form) => {
        const delay = parseInt(form.dataset.autoSubmit || '300', 10);
        const inputs = $$('input, select', form);
        inputs.forEach((input) => {
            on(input, 'change', () => {
                clearTimeout(form._autoSubmitTimer);
                form._autoSubmitTimer = setTimeout(() => form.submit(), delay);
            });
        });
    });
}

/* Confirm before destructive action */
export function confirmAction(message = 'Are you sure?') {
    return confirm(message);
}

/* Show/hide loading state on a form submit button */
export function setFormLoading(form, loading) {
    const btn = form.querySelector('[type="submit"]');
    if (!btn) return;
    if (loading) {
        btn.dataset.originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner spinner-sm"></span> Please wait...';
    } else {
        btn.disabled = false;
        btn.textContent = btn.dataset.originalText || 'Submit';
    }
}
