/**
 * Session-expired banner for the internal pages. Shown once (401, or 419 for
 * an expired CSRF token) instead of redirecting straight away, so unsaved form
 * input is not discarded before the user has seen the warning.
 */

let shown = false;

export function notifySessionExpired() {
    if (shown) return;
    shown = true;

    const banner = document.createElement('div');
    banner.setAttribute('role', 'alert');
    banner.className = 'fixed inset-x-0 top-0 z-[60] border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow';

    const inner = document.createElement('div');
    inner.className = 'mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3';

    const text = document.createElement('p');
    text.innerHTML = '<strong class="font-semibold">Session expired. Please sign in again.</strong> Changes on this page that were not saved yet will be lost when you continue.';

    // Reloading this page sends the user through /login and back here.
    const link = document.createElement('a');
    link.href = window.location.href;
    link.className = 'shrink-0 rounded-md bg-amber-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-950';
    link.textContent = 'Sign in again';

    inner.append(text, link);
    banner.append(inner);
    document.body.append(banner);
}
