/* ============================================================
   LeadX — Notification dropdown module
   ============================================================ */
import { $, on, addClass, removeClass } from '../core/dom.js';

export function initNotificationDropdown({ bellId, dropdownId, badgeId }) {
    const bell = $(`#${bellId}`);
    const dropdown = $(`#${dropdownId}`);
    const badge = $(`#${badgeId}`);

    if (!bell || !dropdown) return;

    bell.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
            removeClass(dropdown, 'open');
        }
    });

    if (badge) {
        // Hide badge when clicked
        bell.addEventListener('click', () => {
            badge.classList.remove('visible');
        });
    }
}
