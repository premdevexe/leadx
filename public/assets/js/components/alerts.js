/* ============================================================
   LeadX — Alert messages
   ============================================================ */
import { $, $$, on } from '../core/dom.js';

export function initDismissibleAlerts() {
    $$('.alert-close').forEach((btn) => {
        on(btn, 'click', () => {
            const alert = btn.closest('.alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 200ms ease';
                setTimeout(() => alert.remove(), 200);
            }
        });
    });
}

export function showToast({ title, message, duration = 4000 } = {}) {
    const container = $('#toast-container') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `
        <div class="toast-title">${escapeHtml(title)}</div>
        <div class="toast-message">${escapeHtml(message)}</div>
    `;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => addClass(toast, 'visible'));
    });

    setTimeout(() => {
        addClass(toast, 'hiding');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function createToastContainer() {
    const el = document.createElement('div');
    el.id = 'toast-container';
    el.className = 'toast-container';
    document.body.appendChild(el);
    return el;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
