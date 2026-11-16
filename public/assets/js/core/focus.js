/* Shared focus and scroll handling for overlays. */
const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
const scrollLocks = new Set();
let previousOverflow = '';

export function focusableElements(container) {
    return [...container.querySelectorAll(focusableSelector)].filter((element) =>
        element.tabIndex >= 0 && !element.closest('[inert], [hidden]') && element.getClientRects().length > 0
    );
}

export function focusFirst(container) {
    const elements = focusableElements(container);
    const target = elements.find((element) => element.hasAttribute('autofocus')) || elements[0] || container;
    if (target === container && !container.hasAttribute('tabindex')) container.tabIndex = -1;
    target.focus({ preventScroll: true });
}

export function trapFocus(event, container) {
    if (event.key !== 'Tab') return;
    const elements = focusableElements(container);
    const first = elements[0];
    const last = elements[elements.length - 1];
    if (!first) {
        event.preventDefault();
        focusFirst(container);
    } else if (event.shiftKey && (document.activeElement === first || !elements.includes(document.activeElement))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !elements.includes(document.activeElement))) {
        event.preventDefault();
        first.focus();
    }
}

export function lockScroll(owner) {
    if (scrollLocks.has(owner)) return;
    if (scrollLocks.size === 0) previousOverflow = document.body.style.overflow;
    scrollLocks.add(owner);
    document.body.style.overflow = 'hidden';
}

export function unlockScroll(owner) {
    if (!scrollLocks.delete(owner)) return;
    if (scrollLocks.size === 0) document.body.style.overflow = previousOverflow;
}
