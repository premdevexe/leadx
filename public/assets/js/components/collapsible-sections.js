/* LeadX — collapsible sidebar sections with persisted state. */
import { storageGet, storageSet } from '../core/dom.js';

const ACTIVE_SELECTOR = '[aria-current="page"]';

/**
 * Turn every `.sidebar-section[data-collapsible]` into an accordion group.
 *
 * - Section state (which groups are collapsed) is persisted per storage key.
 * - The section containing the current page (`aria-current="page"`) is always
 *   opened on load so navigation context is never hidden.
 * - Collapsed bodies become `inert` so their links stay out of tab order.
 */
export function initCollapsibleSections(root = document, { storageKey = 'leadx-sidebar-sections' } = {}) {
    const sections = [...root.querySelectorAll('[data-collapsible][data-section-key]')];
    if (!sections.length) return;

    // storageGet returns the raw string; the value is JSON-encoded because
    // localStorage coerces arrays to comma-joined strings, which would make
    // the persisted state unreadable on the next load.
    let persistedKeys = [];
    try {
        const parsed = JSON.parse(storageGet(storageKey, '[]'));
        if (Array.isArray(parsed)) persistedKeys = parsed.filter((k) => typeof k === 'string');
    } catch {
        persistedKeys = [];
    }
    const collapsed = new Set(persistedKeys);

    const activeSection = root.querySelector(ACTIVE_SELECTOR)?.closest('[data-collapsible][data-section-key]');

    for (const section of sections) {
        const key = section.dataset.sectionKey;
        const toggle = section.querySelector('[data-section-toggle]');
        const body = section.querySelector('[data-section-body]');

        let isCollapsed = collapsed.has(key) && section !== activeSection;

        const apply = (state) => {
            section.classList.toggle('is-collapsed', state);
            toggle?.classList.toggle('is-collapsed', state);
            toggle?.setAttribute('aria-expanded', String(!state));
            if (body) body.inert = state;
        };

        apply(isCollapsed);

        toggle?.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            apply(isCollapsed);
            if (isCollapsed) {
                collapsed.add(key);
            } else {
                collapsed.delete(key);
            }
            storageSet(storageKey, JSON.stringify([...collapsed]));
        });
    }
}
