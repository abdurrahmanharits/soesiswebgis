"use client";

import { useSyncExternalStore } from "react";

// soesis_session is the readable "signed in" marker set next to the httpOnly token cookies.
const hasSessionCookie = () => document.cookie.split("; ").some((cookie) => cookie.startsWith("soesis_session="));

/** "Masuk" in the public header, or "Area internal" when already signed in (Laravel parity). */
export function PublicAccountLink() {
  const signedIn = useSyncExternalStore(() => () => undefined, hasSessionCookie, () => false);

  return (
    <a href={signedIn ? "/status" : "/login"} className="ml-auto shrink-0 text-sm font-medium text-slate-500 hover:text-slate-900">
      {signedIn ? "Area internal" : "Masuk"}
    </a>
  );
}
