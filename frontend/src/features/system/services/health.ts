import { apiClient, type components } from "@/shared/api/client";

export type Readiness = components["schemas"]["ReadinessResponse"];

/** Backend process alive (GET /api/health). */
export async function getLiveness(): Promise<boolean> {
  const { data, response } = await apiClient().GET("/api/health");

  return response.ok && data?.status === "ok";
}

/**
 * Backend dependencies (GET /api/health/ready). A 503 still carries the report,
 * so it is returned rather than thrown; null means the backend is unreachable.
 */
export async function getReadiness(): Promise<Readiness | null> {
  try {
    const { data, error } = await apiClient().GET("/api/health/ready", { cache: "no-store" });

    return data ?? (error as Readiness | undefined) ?? null;
  } catch {
    return null;
  }
}
