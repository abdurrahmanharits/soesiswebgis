import { ApiError, apiClient, toApiError, type components } from "@/shared/api/client";

export type { ApiError };

export type AdminUser = components["schemas"]["UserResponse"];
export type Organization = components["schemas"]["OrganizationWithCountResponse"];
export type UserWrite = components["schemas"]["UserWriteRequest"];
export type UserCreate = components["schemas"]["UserCreateRequest"];

type Result<T> = { data: T; error: null } | { data: null; error: ApiError };

/** Checked before sending, worded like the backend/Laravel message. */
export const passwordMismatch = () =>
  new ApiError("The given data was invalid.", 422, { password: ["The password field confirmation does not match."] });

const unreachable = () => new ApiError("Cannot reach the server. Please try again.", 0);

async function call<T>(request: () => Promise<{ data?: T; error?: unknown; response: Response }>): Promise<Result<T>> {
  try {
    const { data, error, response } = await request();
    return response.ok ? { data: data as T, error: null } : { data: null, error: toApiError(error, response) };
  } catch {
    return { data: null, error: unreachable() };
  }
}

export const listUsers = () => call(() => apiClient().GET("/api/admin/users"));

export const createUser = (body: UserCreate) => call(() => apiClient().POST("/api/admin/users", { body }));

export const updateUser = (id: number, body: UserWrite) =>
  call(() => apiClient().PATCH("/api/admin/users/{user_id}", { params: { path: { user_id: id } }, body }));

export const resetPassword = (id: number, password: string) =>
  call(() => apiClient().POST("/api/admin/users/{user_id}/reset-password", { params: { path: { user_id: id } }, body: { password } }));

export const listOrganizations = () => call(() => apiClient().GET("/api/admin/organizations"));

export const createOrganization = (name: string) => call(() => apiClient().POST("/api/admin/organizations", { body: { name } }));

export const renameOrganization = (id: number, name: string) =>
  call(() => apiClient().PATCH("/api/admin/organizations/{organization_id}", { params: { path: { organization_id: id } }, body: { name } }));
