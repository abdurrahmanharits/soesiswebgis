import createClient from "openapi-fetch";

import type { paths } from "./schema";

/**
 * The only way the frontend talks to the backend. Typed from the OpenAPI
 * contract, so a changed endpoint fails the TypeScript build.
 *
 * Browser: same-origin "/api/..." (proxied to FastAPI by next.config.ts).
 * Server (SSR): straight to BACKEND_URL, never to the database.
 */
export function apiClient() {
  const baseUrl = typeof window === "undefined" ? (process.env.BACKEND_URL ?? "http://127.0.0.1:8001") : "";

  return createClient<paths>({ baseUrl });
}

export type { components, paths } from "./schema";
