/* ============================================================
   LeadX — Page: Admin Dashboard
   ============================================================ */
import { $ } from '../core/dom.js';
import { initSidebar } from '../components/sidebar.js';
import { initNotificationDropdown } from '../components/notifications.js';
import { initModals, initTriggers } from '../components/modals.js';
import { initDropdowns } from '../components/dropdowns.js';
import { initForms } from '../components/forms.js';
import { initDismissibleAlerts, showToast } from '../components/alerts.js';
import { initCsrf } from '../core/csrf.js';

let initialized = false;

export function initAdminDashboard() {
    if (initialized) return;
    initialized = true;
    initSidebar({
        sidebarId: 'sidebar',
        overlayId: 'sidebar-overlay',
        toggleId: 'sidebar-toggle',
        closeBtnId: 'sidebar-close-btn',
        localStorageKey: 'leadx-sidebar-desktop',
    });

    initNotificationDropdown({
        bellId: 'notification-bell',
        dropdownId: 'notification-dropdown',
        badgeId: 'unread-badge',
    });

    initModals();
    initTriggers();
    initDropdowns();
    initForms();
    initDismissibleAlerts();

    // Admin notification bell — redirect to notifications page
    const bell = $('#notification-bell');
    if (bell?.dataset.notificationsUrl && !$('#notification-dropdown')) {
        bell.addEventListener('click', () => {
            window.location.assign(bell.dataset.notificationsUrl);
        });
    }

    initCsrf();
}

// Auto-run on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDashboard);
} else {
    initAdminDashboard();
}
