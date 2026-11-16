/* ============================================================
   LeadX — Sidebar module (shared across admin, employee, team)
   ============================================================ */
import {
    $, on, off, hasClass, addClass, removeClass,
    hide, show, toggle, storageGet, storageSet
} from '../core/dom.js';

export function initSidebar({ sidebarId, overlayId, toggleId, closeBtnId, localStorageKey, linkSelector = 'a' }) {
    const sidebar = $(`#${sidebarId}`);
    const overlay = $(`#${overlayId}`);
    const toggle = $(`#${toggleId}`);
    const closeBtn = $(`#${closeBtnId}`);

    if (!sidebar || !toggle) return;

    const isMobile = () => window.matchMedia('(max-width: 767px)').matches;
    let mobileOpen = false;
    let desktopOpen = storageGet(localStorageKey, 'open') === 'open';

    function render() {
        if (isMobile()) {
            sidebar.classList.remove('sidebar-collapsed');
            if (mobileOpen) {
                removeClass(sidebar, '-translate-x-full');
                addClass(sidebar, 'translate-x-0');
                if (overlay) addClass(overlay, 'visible');
                document.body.classList.add('overflow-hidden');
                addClass(toggle, 'menu-open');
            } else {
                addClass(sidebar, '-translate-x-full');
                removeClass(sidebar, 'translate-x-0');
                if (overlay) removeClass(overlay, 'visible');
                document.body.classList.remove('overflow-hidden');
                removeClass(toggle, 'menu-open');
            }
        } else {
            document.body.classList.remove('overflow-hidden');
            if (overlay) removeClass(overlay, 'visible');
            removeClass(sidebar, '-translate-x-full', 'translate-x-0');
            if (desktopOpen) {
                removeClass(sidebar, 'sidebar-collapsed');
                addClass(toggle, 'menu-open');
            } else {
                addClass(sidebar, 'sidebar-collapsed');
                removeClass(toggle, 'menu-open');
            }
        }
    }

    function toggleSidebar() {
        if (isMobile()) {
            mobileOpen = !mobileOpen;
            render();
        } else {
            desktopOpen = !desktopOpen;
            storageSet(localStorageKey, desktopOpen ? 'open' : 'closed');
            render();
        }
    }

    function closeMobile() {
        if (isMobile() && mobileOpen) { mobileOpen = false; render(); }
    }

    on(toggle, 'click', toggleSidebar);
    if (overlay) on(overlay, 'click', closeMobile);
    if (closeBtn) on(closeBtn, 'click', closeMobile);

    // Close sidebar when a nav link is clicked on mobile
    sidebar.addEventListener('click', (e) => {
        if (isMobile() && e.target.closest(linkSelector)) closeMobile();
    });

    // Close on Escape
    on(document, 'keydown', (e) => {
        if (e.key === 'Escape' && isMobile() && mobileOpen) closeMobile();
    });

    // Handle resize across breakpoint
    let prevIsMobile = isMobile();
    on(window, 'resize', () => {
        const current = isMobile();
        if (current !== prevIsMobile) {
            prevIsMobile = current;
            if (!current) mobileOpen = false;
            render();
        }
    });

    render();
}
