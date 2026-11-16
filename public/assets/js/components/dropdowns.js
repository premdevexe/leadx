/* LeadX ? dropdown toggles with keyboard and expanded-state support. */
import { focusableElements } from '../core/focus.js';

const dropdowns = new Map();
let initialized = false;

function setOpen(trigger, dropdown, open) {
    dropdown.classList.toggle('open', open);
    trigger.setAttribute('aria-expanded', String(open));
}

function closeAll(except = null) {
    dropdowns.forEach((dropdown, trigger) => {
        if (dropdown !== except) setOpen(trigger, dropdown, false);
    });
}

export function initDropdowns() {
    document.querySelectorAll('[data-dropdown-toggle]').forEach((trigger) => {
        const dropdown = document.getElementById(trigger.dataset.dropdownToggle);
        if (!dropdown || dropdowns.has(trigger)) return;
        dropdowns.set(trigger, dropdown);
        trigger.setAttribute('aria-controls', dropdown.id);
        trigger.setAttribute('aria-expanded', String(dropdown.classList.contains('open')));

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const open = !dropdown.classList.contains('open');
            closeAll(dropdown);
            setOpen(trigger, dropdown, open);
        });
        trigger.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            closeAll(dropdown);
            setOpen(trigger, dropdown, true);
            const items = focusableElements(dropdown).filter((item) => item !== trigger);
            (event.key === 'ArrowUp' ? items.at(-1) : items[0])?.focus();
        });
    });

    if (initialized) return;
    initialized = true;
    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown, trigger) => {
            if (!dropdown.contains(event.target) && !trigger.contains(event.target)) setOpen(trigger, dropdown, false);
        });
    });
    document.addEventListener('focusin', (event) => {
        dropdowns.forEach((dropdown, trigger) => {
            if (!dropdown.contains(event.target) && !trigger.contains(event.target)) setOpen(trigger, dropdown, false);
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        dropdowns.forEach((dropdown, trigger) => {
            if (!dropdown.classList.contains('open')) return;
            setOpen(trigger, dropdown, false);
            if (dropdown.contains(document.activeElement)) trigger.focus();
        });
    });
}
