"use client";

import { useSession } from "./session-provider";

/**
 * Same behaviour as the Laravel version: warn before leaving, so unsaved form input
 * is not discarded silently. "Sign in again" goes through /login and back here.
 */
export function SessionExpiredBanner() {
  const { status } = useSession();

  if (status !== "expired") return null;

  const next = typeof window === "undefined" ? "/" : window.location.pathname + window.location.search;

  return (
    <div role="alert" data-testid="session-expired" className="fixed inset-x-0 top-0 z-[60] border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
        <p>
          <strong className="font-semibold">Session expired. Please sign in again.</strong> Changes on this page that
          were not saved yet will be lost when you continue.
        </p>
        <a href={`/login?next=${encodeURIComponent(next)}`} className="shrink-0 rounded-md bg-amber-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-950">
          Sign in again
        </a>
      </div>
    </div>
  );
}
