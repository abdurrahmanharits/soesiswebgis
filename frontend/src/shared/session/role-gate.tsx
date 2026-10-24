"use client";

import { useSession, type SessionUser } from "./session-provider";

/**
 * UX only: hides pages a role cannot use. The backend enforces the same rule
 * (403) — this never replaces that check.
 */
export function RoleGate({ roles, children }: { roles: SessionUser["role"][]; children: React.ReactNode }) {
  const { status, user } = useSession();

  if (status === "loading" || !user) {
    return <p className="text-sm text-slate-500">Loading…</p>;
  }

  if (!roles.includes(user.role)) {
    return (
      <p className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert" data-testid="forbidden">
        You do not have permission to view this page.
      </p>
    );
  }

  return children;
}
