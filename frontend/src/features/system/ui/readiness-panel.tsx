import { StatusBadge } from "@/shared/ui/status-badge";

import { getReadiness } from "../services/health";

/** Server component: rendered per request from GET /api/health/ready. */
export async function ReadinessPanel() {
  const readiness = await getReadiness();

  if (!readiness) {
    return (
      <p className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert" data-testid="readiness-unreachable">
        Backend is unreachable. Start it with <code className="font-mono">docker compose up -d backend</code>.
      </p>
    );
  }

  return (
    <section className="overflow-hidden rounded-lg border border-slate-200 bg-white" data-testid="readiness" data-status={readiness.status}>
      <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
        <h2 className="text-sm font-semibold text-slate-900">Backend readiness</h2>
        <StatusBadge ok={readiness.status === "ok"} label={readiness.status === "ok" ? "Ready" : "Unavailable"} />
      </div>
      <dl className="divide-y divide-slate-100">
        {Object.entries(readiness.components).map(([name, component]) => (
          <div key={name} className="grid grid-cols-3 gap-4 px-5 py-3 text-sm" data-testid={`component-${name}`}>
            <dt className="font-medium text-slate-700 capitalize">{name}</dt>
            <dd><StatusBadge ok={component.status === "ok"} label={component.status === "ok" ? "OK" : "Failed"} /></dd>
            <dd className="font-mono text-xs text-slate-500">
              {Object.entries(component.detail).map(([key, value]) => `${key}: ${value}`).join(" · ") || "—"}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  );
}
