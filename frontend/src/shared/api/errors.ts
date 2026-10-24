/** Backend errors share one shape: {"message": ..., "errors"?: {field: [messages]}}. */

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }

  get isForbidden(): boolean {
    return this.status === 403;
  }

  get isUnauthenticated(): boolean {
    return this.status === 401;
  }

  /** First message for a form field, if the backend rejected it. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

type ErrorBody = { message?: string; errors?: Record<string, string[]> } | undefined;

export function toApiError(error: unknown, response: Response): ApiError {
  const body = error as ErrorBody;
  return new ApiError(body?.message ?? `Request failed (HTTP ${response.status}).`, response.status, body?.errors ?? {});
}
