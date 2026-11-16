/* ============================================================
   LeadX — Core: DOM utilities
   ============================================================ */
export const $ = (sel, ctx = document) => ctx.querySelector(sel);
export const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

export function on(el, event, handler, opts) {
    el.addEventListener(event, handler, opts);
}

export function off(el, event, handler, opts) {
    el.removeEventListener(event, handler, opts);
}

export function delegate(parent, selector, event, handler) {
    parent.addEventListener(event, (e) => {
        const target = e.target.closest(selector);
        if (target && parent.contains(target)) {
            handler.call(target, e);
        }
    });
}

export function hide(el) { el.classList.add('hidden'); }
export function show(el) { el.classList.remove('hidden'); }
export function toggle(el, force) {
    if (typeof force === 'boolean') {
        el.classList.toggle('hidden', !force);
    } else {
        el.classList.toggle('hidden');
    }
}

export function addClass(el, ...classes) {
    el.classList.add(...classes.filter(Boolean));
}

export function removeClass(el, ...classes) {
    el.classList.remove(...classes.filter(Boolean));
}

export function hasClass(el, cls) {
    return el.classList.contains(cls);
}

export function createEl(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    for (const [key, val] of Object.entries(attrs)) {
        if (key === 'text') el.textContent = val;
        else if (key === 'html') el.innerHTML = val;
        else if (key.startsWith('data-')) el.dataset[key.slice(5)] = val;
        else if (key === 'className') el.className = val;
        else el.setAttribute(key, val);
    }
    for (const child of children) {
        el.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    }
    return el;
}

export function setAttr(el, attrs) {
    for (const [key, val] of Object.entries(attrs)) el.setAttribute(key, val);
}

export function debounce(fn, delay = 250) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

export function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

export function storageGet(key, fallback = null) {
    try {
        const val = localStorage.getItem(key);
        return val !== null ? val : fallback;
    } catch { return fallback; }
}

export function storageSet(key, val) {
    try { localStorage.setItem(key, val); } catch {}
}

export function storageRemove(key) {
    try { localStorage.removeItem(key); } catch {}
}
