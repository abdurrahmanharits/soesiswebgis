import { ApiError, apiClient, toApiError } from "@/shared/api/client";

/** POST /api/auth/login — the backend sets the httpOnly cookies; nothing is stored here. */
export async function signIn(login: string, password: string): Promise<ApiError | null> {
  try {
    const { error, response } = await apiClient().POST("/api/auth/login", { body: { login, password } });
    return response.ok ? null : toApiError(error, response);
  } catch {
    return new ApiError("Cannot reach the server. Please try again.", 0);
  }
}

/** Only same-site relative paths are followed after sign-in (no open redirect). */
export function safeNextPath(next: string | null, fallback = "/status"): string {
  return next && next.startsWith("/") && !next.startsWith("//") && !next.startsWith("/login") ? next : fallback;
}
