/* ============================================================
   LeadX — Page: Employee Dashboard
   ============================================================ */
import { $ } from '../core/dom.js';
import { initSidebar } from '../components/sidebar.js';
import { initNotificationDropdown } from '../components/notifications.js';
import { initModals, initTriggers } from '../components/modals.js';
import { initDropdowns } from '../components/dropdowns.js';
import { initForms } from '../components/forms.js';
import { initDismissibleAlerts, showToast } from '../components/alerts.js';

export function initEmployeeDashboard() {
    initSidebar({
        sidebarId: 'sidebar',
        overlayId: 'sidebar-overlay',
        toggleId: 'sidebar-toggle',
        closeBtnId: 'sidebar-close-btn',
        localStorageKey: 'leadx-employee-sidebar-desktop',
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

    initUnreadBadge();
    initRealtimeNotifications();
}

function initUnreadBadge() {
    // The unread count is rendered server-side in the layout; nothing to do here
    // beyond keeping the badge in sync if it is exposed on window.
    if (typeof window.leadxUnreadCount !== 'undefined' && window.leadxUnreadCount > 0) {
        const badge = $('#unread-badge');
        if (badge) {
            badge.textContent = window.leadxUnreadCount > 99 ? '99+' : window.leadxUnreadCount;
            badge.classList.add('visible');
        }
    }
}

function initRealtimeNotifications() {
    document.addEventListener('leadx:notification', (e) => {
        const data = e.detail;
        if (!data || !data.title) return;

        showToast({ title: data.title, message: data.message });

        const badge = $('#unread-badge');
        if (badge) {
            let current = parseInt(badge.textContent || '0', 10) || 0;
            badge.textContent = current + 1 > 99 ? '99+' : (current + 1);
            badge.classList.add('visible');
        }
    });

    const bell = $('#notification-bell');
    if (bell) {
        bell.addEventListener('click', () => {
            window.location.href = '/employee/notifications';
        });
    }
}

// Auto-run on page load (module scripts are deferred, so the DOM is ready)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEmployeeDashboard);
} else {
    initEmployeeDashboard();
}
