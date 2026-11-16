/* ============================================================
   LeadX — App entry point (loaded on every page)
   ============================================================ */
import {
    initDismissibleAlerts,
    showToast
} from './components/alerts.js';

// Run global initializations
initDismissibleAlerts();

// Expose toast globally for inline scripts
window.leadx = window.leadx || {};
window.leadx.showToast = showToast;
