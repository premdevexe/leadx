/* LeadX ? form helpers. */
const initializedForms = new WeakSet();
const loadingStates = new WeakMap();

export function initForms() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        if (initializedForms.has(form)) return;
        initializedForms.add(form);
        const configuredDelay = Number.parseInt(form.dataset.autoSubmit, 10);
        const delay = Number.isFinite(configuredDelay) ? Math.max(0, configuredDelay) : 300;
        let timer;
        form.addEventListener('change', (event) => {
            if (!event.target.matches('input, select, textarea')) return;
            clearTimeout(timer);
            // requestSubmit preserves browser validation and submit listeners.
            timer = setTimeout(() => form.requestSubmit(), delay);
        });
        form.addEventListener('submit', () => clearTimeout(timer));
    });
}

export function confirmAction(message = 'Are you sure?') {
    return window.confirm(message);
}

export function setFormLoading(form, loading) {
    if (loading) {
        if (loadingStates.has(form)) return;
        const buttons = [...form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]')];
        const state = buttons.map((button) => ({
            button,
            disabled: button.disabled,
            html: button.innerHTML,
            value: button.value,
        }));
        loadingStates.set(form, { buttons: state, busy: form.getAttribute('aria-busy') });
        form.setAttribute('aria-busy', 'true');
        state.forEach(({ button }) => {
            button.disabled = true;
            if (button.tagName === 'INPUT') button.value = 'Please wait?';
            else button.innerHTML = '<span class="spinner spinner-sm" aria-hidden="true"></span> Please wait?';
        });
    } else {
        const state = loadingStates.get(form);
        if (!state) return;
        state.buttons.forEach(({ button, disabled, html, value }) => {
            button.disabled = disabled;
            if (button.tagName === 'INPUT') button.value = value;
            else button.innerHTML = html;
        });
        if (state.busy === null) form.removeAttribute('aria-busy');
        else form.setAttribute('aria-busy', state.busy);
        loadingStates.delete(form);
    }
}
