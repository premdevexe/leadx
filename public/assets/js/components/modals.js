/* LeadX ? accessible shared modal controls. */
import { focusFirst, trapFocus, lockScroll, unlockScroll } from '../core/focus.js';

const modalSelector = '.modal-overlay, .leadx-modal-backdrop';
const openModals = [];
const returnFocus = new WeakMap();
let initialized = false;
let triggersInitialized = false;

export function initModals() {
    if (initialized) return;
    initialized = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-modal-close]');
        if (button) {
            event.preventDefault();
            closeModal(button.dataset.modalClose || button.closest(modalSelector));
        } else if (event.target.matches(modalSelector) && event.target.dataset.modalStatic !== 'true') {
            closeModal(event.target);
        }
    });
    document.addEventListener('keydown', (event) => {
        const modal = openModals.at(-1);
        if (!modal) return;
        if (event.key === 'Escape' && modal.dataset.modalStatic !== 'true') {
            event.preventDefault();
            closeModal(modal);
        } else {
            trapFocus(event, modal);
        }
    });
    document.addEventListener('focusin', (event) => {
        const modal = openModals.at(-1);
        if (modal && !modal.contains(event.target)) focusFirst(modal);
    });
}

export function openModal(id) {
    const modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal || openModals.includes(modal)) return;
    initModals();
    returnFocus.set(modal, document.activeElement);
    openModals.push(modal);
    modal.classList.remove('hidden');
    modal.classList.add('active');
    modal.hidden = false;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-hidden', 'false');
    lockScroll(modal);
    focusFirst(modal);
}

export function closeModal(id) {
    const modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;
    const index = openModals.indexOf(modal);
    const wasTopModal = index === openModals.length - 1;
    if (index >= 0) openModals.splice(index, 1);
    modal.classList.remove('active');
    if (!modal.classList.contains('modal-overlay')) modal.classList.add('hidden');
    unlockScroll(modal);
    if (index >= 0 && wasTopModal) {
        const target = returnFocus.get(modal);
        if (target?.isConnected && !target.closest('[inert]')) target.focus({ preventScroll: true });
        else if (openModals.length) focusFirst(openModals.at(-1));
    }
    modal.setAttribute('aria-hidden', 'true');
    returnFocus.delete(modal);
}

export function initTriggers() {
    if (triggersInitialized) return;
    triggersInitialized = true;
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-modal-open]');
        if (!trigger?.dataset.modalOpen) return;
        event.preventDefault();
        openModal(trigger.dataset.modalOpen);
    });
}
