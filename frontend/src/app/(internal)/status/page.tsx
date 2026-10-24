import type { Metadata } from "next";
import { Suspense } from "react";

import { ReadinessPanel } from "@/features/system/ui/readiness-panel";

export const metadata: Metadata = { title: "System status" };

/** Rewrite foundation check: backend, PostGIS and Redis as seen by the frontend server. */
export default function StatusPage() {
  return (
    <main className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">System status</h1>
        <p className="mt-1 text-sm text-slate-600">Readiness of the FastAPI backend and its dependencies, fetched server-side on each request.</p>
      </div>

      {/* Uncached per-request data streams inside Suspense (Cache Components). */}
      <Suspense fallback={<p className="text-sm text-slate-500">Checking backend…</p>}>
        <ReadinessPanel />
      </Suspense>
    </main>
  );
}
