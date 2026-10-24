/** Pill badge in the existing SoESIS style (emerald = ok, rose = failed, slate = pending). */
export function StatusBadge({ ok, label, pending = false }: { ok: boolean; label: string; pending?: boolean }) {
  const tone = pending
    ? "bg-slate-100 text-slate-700 ring-slate-300"
    : ok
      ? "bg-emerald-50 text-emerald-800 ring-emerald-200"
      : "bg-rose-50 text-rose-800 ring-rose-200";
  const dot = pending ? "bg-slate-400 animate-pulse" : ok ? "bg-emerald-500" : "bg-rose-500";

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${tone}`}>
      <span className={`size-1.5 rounded-full ${dot}`} aria-hidden="true" />
      {label}
    </span>
  );
}
