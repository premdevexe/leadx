/* LeadX ? notification disclosure. Opening it does not mark messages read. */
import { initDropdowns } from './dropdowns.js';

export function initNotificationDropdown({ bellId, dropdownId }) {
    const bell = document.getElementById(bellId);
    const dropdown = document.getElementById(dropdownId);
    if (!bell || !dropdown) return;
    bell.dataset.dropdownToggle = dropdown.id;
    initDropdowns();
}
