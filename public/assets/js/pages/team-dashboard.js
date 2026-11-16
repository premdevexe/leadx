/* ============================================================
   LeadX — Page: Team Dashboard
   ============================================================ */
import { initSidebar } from '../components/sidebar.js';
import { initModals, initTriggers } from '../components/modals.js';
import { initDropdowns } from '../components/dropdowns.js';
import { initForms } from '../components/forms.js';
import { initDismissibleAlerts } from '../components/alerts.js';

export function initTeamDashboard() {
    initSidebar({
        sidebarId: 'sidebar',
        overlayId: 'sidebar-overlay',
        toggleId: 'sidebar-toggle',
        closeBtnId: 'sidebar-close-btn',
        localStorageKey: 'leadx-team-sidebar-desktop',
    });

    initModals();
    initTriggers();
    initDropdowns();
    initForms();
    initDismissibleAlerts();
}

// Auto-run on page load (module scripts are deferred, so the DOM is ready)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTeamDashboard);
} else {
    initTeamDashboard();
}
