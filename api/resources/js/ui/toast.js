const DOT = {
    success: 'bg-emerald-500',
    error: 'bg-rose-500',
    info: 'bg-sky-500',
};

/** Small transient notice rendered into #toast-region (layouts/app.blade.php). */
export function showToast(message, variant = 'info', timeout = 5000) {
    const region = document.getElementById('toast-region');

    if (!region) return;

    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-lg';

    const dot = document.createElement('span');
    dot.className = `mt-1.5 size-2 shrink-0 rounded-full ${DOT[variant] ?? DOT.info}`;
    dot.setAttribute('aria-hidden', 'true');

    const text = document.createElement('p');
    text.textContent = message;

    toast.append(dot, text);
    region.append(toast);

    setTimeout(() => toast.remove(), timeout);
}
