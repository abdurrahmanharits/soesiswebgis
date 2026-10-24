"use client";

import { usePathname, useRouter } from "next/navigation";
import { createContext, use, useCallback, useEffect, useMemo, useRef, useState } from "react";

import { apiClient, onSessionExpired, type components } from "@/shared/api/client";

export type SessionUser = components["schemas"]["UserResponse"];

type Status = "loading" | "authenticated" | "expired";

type SessionValue = {
  status: Status;
  user: SessionUser | null;
  reload: () => Promise<void>;
};

const SessionContext = createContext<SessionValue | null>(null);

/**
 * The signed-in account for the internal area, from GET /api/auth/me (FastAPI is
 * the authority; the JWT is never read here). Not signed in on arrival → /login.
 * Session ending later (refresh failed) → "expired": the page stays, a banner explains.
 */
export function SessionProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const [status, setStatus] = useState<Status>("loading");
  const [user, setUser] = useState<SessionUser | null>(null);
  const statusRef = useRef<Status>("loading");

  const changeStatus = useCallback((next: Status) => {
    statusRef.current = next;
    setStatus(next);
  }, []);

  const apply = useCallback((data: SessionUser | undefined) => {
    if (data) {
      setUser(data);
      changeStatus("authenticated");
    } else if (statusRef.current === "loading") {
      router.replace(`/login?next=${encodeURIComponent(window.location.pathname + window.location.search)}`);
    } else if (statusRef.current === "authenticated") {
      changeStatus("expired");
    }
  }, [router, changeStatus]);

  const reload = useCallback(() => apiClient().GET("/api/auth/me").then(({ data }) => apply(data)), [apply]);

  // On arrival and on every client-side navigation: role or deactivation may have changed.
  useEffect(() => {
    let cancelled = false;
    apiClient().GET("/api/auth/me").then(({ data }) => {
      if (!cancelled) apply(data);
    });
    return () => {
      cancelled = true;
    };
  }, [apply, pathname]);

  useEffect(
    () =>
      onSessionExpired(() => {
        if (statusRef.current === "authenticated") changeStatus("expired");
      }),
    [changeStatus],
  );

  const value = useMemo(() => ({ status, user, reload }), [status, user, reload]);

  return <SessionContext value={value}>{children}</SessionContext>;
}

export function useSession(): SessionValue {
  const value = use(SessionContext);
  if (!value) throw new Error("useSession must be used inside <SessionProvider>.");
  return value;
}
