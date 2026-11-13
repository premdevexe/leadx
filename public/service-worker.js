/*
 * Pusher Beams service worker.
 *
 * The Beams Web SDK (push-notifications-cdn.js, loaded via the
 * "partials.push-beams" Blade partial) auto-registers this file at the
 * root scope. The script imported below handles `onpush` and displays
 * notifications — keep it minimal, since push events are received while
 * the app is closed.
 */
importScripts("https://js.pusher.com/beams/service-worker.js");
