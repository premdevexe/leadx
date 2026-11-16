/* LeadX ? responsive navigation shared by all portals. */
import { storageGet, storageSet } from '../core/dom.js';
import { focusFirst, trapFocus, lockScroll, unlockScroll } from '../core/focus.js';

export function initSidebar({ sidebarId, overlayId, toggleId, closeBtnId, localStorageKey, linkSelector = 'a' }) {
    const sidebar = document.getElementById(sidebarId);
    const overlay = document.getElementById(overlayId);
    const toggle = document.getElementById(toggleId);
    const closeBtn = document.getElementById(closeBtnId);
    if (!sidebar || !toggle || sidebar.dataset.initialized === 'true') return;
    sidebar.dataset.initialized = 'true';

    const main = document.querySelector('.main-column');
    const mainWasInert = main?.inert ?? false;
    const media = window.matchMedia('(max-width: 767px)');
    let mobileOpen = false;
    let desktopOpen = storageGet(localStorageKey, 'open') === 'open';
    toggle.setAttribute('aria-controls', sidebar.id);

    function render() {
        const open = media.matches ? mobileOpen : desktopOpen;
        sidebar.classList.toggle('sidebar-collapsed', !media.matches && !open);
        sidebar.classList.toggle('-translate-x-full', media.matches && !open);
        sidebar.classList.toggle('translate-x-0', media.matches && open);
        sidebar.inert = !open;
        sidebar.setAttribute('aria-hidden', String(!open));
        toggle.classList.toggle('menu-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        overlay?.classList.toggle('visible', media.matches && open);
        if (main) main.inert = mainWasInert || (media.matches && open);
        if (media.matches && open) lockScroll(sidebar);
        else unlockScroll(sidebar);
    }

    function closeMobile() {
        if (!media.matches || !mobileOpen) return;
        mobileOpen = false;
        if (main) main.inert = mainWasInert;
        toggle.focus({ preventScroll: true });
        render();
    }

    toggle.addEventListener('click', () => {
        if (media.matches) {
            if (mobileOpen) return closeMobile();
            mobileOpen = true;
            render();
            focusFirst(sidebar);
        } else {
            desktopOpen = !desktopOpen;
            storageSet(localStorageKey, desktopOpen ? 'open' : 'closed');
            render();
        }
    });
    overlay?.addEventListener('click', closeMobile);
    closeBtn?.addEventListener('click', closeMobile);
    sidebar.addEventListener('click', (event) => {
        if (media.matches && event.target.closest(linkSelector)) closeMobile();
    });
    document.addEventListener('keydown', (event) => {
        if (!media.matches || !mobileOpen) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMobile();
        } else trapFocus(event, sidebar);
    });
    media.addEventListener('change', () => {
        mobileOpen = false;
        if (main) main.inert = mainWasInert;
        if ((media.matches || !desktopOpen) && sidebar.contains(document.activeElement)) toggle.focus();
        render();
    });
    render();
}
