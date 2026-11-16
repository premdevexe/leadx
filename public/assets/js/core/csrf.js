/* Attach Laravel CSRF protection only to same-origin modifying XHR requests. */
let initialized = false;

export function initCsrf() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token || initialized) return;
    initialized = true;
    const open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url, ...args) {
        const result = open.call(this, method, url, ...args);
        const target = new URL(url, document.baseURI);
        if (target.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(String(method).toUpperCase())) {
            this.setRequestHeader('X-CSRF-TOKEN', token);
        }
        return result;
    };
}
