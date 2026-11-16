/* LeadX ? dismissible alerts and accessible toast messages. */
let initialized = false;

export function initDismissibleAlerts() {
    if (initialized) return;
    initialized = true;
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.alert-close');
        if (!button) return;
        const alert = button.closest('.alert');
        if (!alert) return;
        event.preventDefault();
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        alert.style.transition = reducedMotion ? 'none' : 'opacity 200ms ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), reducedMotion ? 0 : 200);
    });
}

export function showToast({ title = '', message = '', duration = 6000 } = {}) {
    const container = document.getElementById('toast-container') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-atomic', 'true');
    const content = document.createElement('div');
    const heading = document.createElement('div');
    heading.className = 'toast-title';
    heading.textContent = title ?? '';
    const text = document.createElement('div');
    text.className = 'toast-message';
    text.textContent = message ?? '';
    content.append(heading, text);
    toast.append(content);
    container.appendChild(toast);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('visible'));
    });

    const timeout = Number.isFinite(duration) ? Math.max(0, duration) : 6000;
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, timeout);
    return toast;
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}
