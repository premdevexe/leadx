/* ============================================================
   LeadX — Dropdown toggle module
   ============================================================ */
import { $, on } from '../core/dom.js';

export function initDropdowns() {
    // Toggle on click for [data-dropdown] triggers
    $$('[data-dropdown-toggle]').forEach((trigger) => {
        const targetId = trigger.dataset.dropdownToggle;
        const dropdown = $(`#${targetId}`);
        if (!dropdown) return;

        on(trigger, 'click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('open');

            // Close all dropdowns
            $$('[data-dropdown]').forEach((d) => d.classList.remove('open'));

            if (!isOpen) addClass(dropdown, 'open');
        });
    });

    // Close all dropdowns on outside click
    on(document, 'click', () => {
        $$('[data-dropdown]').forEach((d) => d.classList.remove('open'));
    });
}
