import createClient from "openapi-fetch";

import type { paths } from "./schema";
import { notifySessionExpired } from "./session-events";

/**
 * The only way the frontend talks to the backend. Typed from the OpenAPI
 * contract, so a changed endpoint fails the TypeScript build.
 *
 * Browser: same-origin "/api/..." (proxied to FastAPI by next.config.ts); the
 * httpOnly auth cookies travel automatically and JavaScript never sees a token.
 * Server (SSR): straight to BACKEND_URL — public data only, never the database.
 */

const REFRESH_URL = "/api/auth/refresh";
// Requests that must not trigger a refresh-and-retry themselves.
const NO_RETRY = ["/api/auth/login", "/api/auth/refresh", "/api/auth/logout"];

let refreshing: Promise<boolean> | null = null;

/** Single-flight: concurrent 401s share one refresh request. */
function refreshSession(): Promise<boolean> {
  refreshing ??= fetch(REFRESH_URL, { method: "POST", credentials: "same-origin" })
    .then((response) => response.ok)
    .catch(() => false)
    .finally(() => {
      refreshing = null;
    });
  return refreshing;
}

/** On 401: refresh once, then retry the original request; if refreshing fails, the session is over. */
async function browserFetch(request: Request): Promise<Response> {
  const retry = request.clone();
  const response = await fetch(request);
  const path = new URL(request.url).pathname;

  if (response.status !== 401 || NO_RETRY.includes(path)) return response;

  if (await refreshSession()) return fetch(retry);

  notifySessionExpired();
  return response;
}

export function apiClient() {
  if (typeof window === "undefined") {
    return createClient<paths>({ baseUrl: process.env.BACKEND_URL ?? "http://127.0.0.1:8001" });
  }

  return createClient<paths>({ baseUrl: "", credentials: "same-origin", fetch: browserFetch });
}

export type { components, paths } from "./schema";
export { onSessionExpired } from "./session-events";
export { ApiError, toApiError } from "./errors";
