"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { apiClient } from "@/shared/api/client";

import { useSession } from "./session-provider";

/** Name, role and "Log out" in the internal header. */
export function UserMenu() {
  const { user } = useSession();
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  if (!user) return null;

  async function logout() {
    setBusy(true);
    await apiClient().POST("/api/auth/logout").catch(() => undefined);
    // /login has its own root layout, so this is a full page load that drops all session state.
    router.push("/login");
  }

  return (
    <div className="flex shrink-0 items-center gap-3" data-testid="user-menu">
      <div className="hidden text-right leading-tight sm:block">
        <p className="text-sm font-medium text-slate-900">{user.name}</p>
        <p className="text-xs text-slate-500">{user.role_label}</p>
      </div>
      <button type="button" onClick={logout} disabled={busy}
              className="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50 disabled:opacity-50">
        Log out
      </button>
    </div>
  );
}
