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

export function initAdminDashboard() {
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
    if (bell) {
        bell.addEventListener('click', () => {
            window.location.href = '/admin/notifications';
        });
    }

    initCsrf();
}

function initCsrf() {
    const token = $('meta[name="csrf-token"]')?.content;
    if (!token) return;
    const orig = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
        this.setRequestHeader('X-CSRF-TOKEN', token);
        return orig.apply(this, arguments);
    };
}

// Auto-run on page load
document.addEventListener('DOMContentLoaded', initAdminDashboard);
