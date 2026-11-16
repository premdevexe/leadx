/* ============================================================
   LeadX — Modal module
   ============================================================ */
import { $, $$, on, show, hide, addClass, removeClass } from '../core/dom.js';

export function initModals() {
    // Close buttons
    $$('[data-modal-close]').forEach((btn) => {
        on(btn, 'click', () => {
            const modal = btn.closest('.modal-overlay') || $(`#${btn.dataset.modalClose}`);
            if (modal) closeModal(modal);
        });
    });

    // Close on overlay click
    $$('.modal-overlay').forEach((overlay) => {
        on(overlay, 'click', (e) => {
            if (e.target === overlay) closeModal(overlay);
        });
    });

    // Close on Escape
    on(document, 'keydown', (e) => {
        if (e.key === 'Escape') {
            const openModal = $('.modal-overlay.active');
            if (openModal) closeModal(openModal);
        }
    });
}

export function openModal(id) {
    const modal = typeof id === 'string' ? $(`#${id}`) : id;
    if (!modal) return;
    addClass(modal, 'active');
    document.body.style.overflow = 'hidden';
}

export function closeModal(id) {
    const modal = typeof id === 'string' ? $(`#${id}`) : id;
    if (!modal) return;
    removeClass(modal, 'active');
    document.body.style.overflow = '';
}

export function initTriggers() {
    // [data-modal-open="modal-id"] opens modal
    $$('[data-modal-open]').forEach((trigger) => {
        on(trigger, 'click', () => {
            const targetId = trigger.dataset.modalOpen;
            if (targetId) openModal(targetId);
        });
    });
}
