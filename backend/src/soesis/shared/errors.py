"""Uniform JSON error bodies: {"message": ...} plus "errors" for validation failures.

Same shape as the Laravel API, so the frontend handles both identically.
"""

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException


def _validation_errors(exc: RequestValidationError) -> dict[str, list[str]]:
    errors: dict[str, list[str]] = {}
    for error in exc.errors():
        # Drop the location prefix ("body", "query", ...) so keys are field names.
        location = [str(part) for part in error.get("loc", ())[1:]] or ["request"]
        errors.setdefault(".".join(location), []).append(error.get("msg", "Invalid value."))
    return errors


def register_error_handlers(app: FastAPI) -> None:
    @app.exception_handler(StarletteHTTPException)
    async def http_error(_: Request, exc: StarletteHTTPException) -> JSONResponse:
        message = exc.detail if isinstance(exc.detail, str) else "Request failed."
        return JSONResponse({"message": message}, status_code=exc.status_code, headers=exc.headers)

    @app.exception_handler(RequestValidationError)
    async def validation_error(_: Request, exc: RequestValidationError) -> JSONResponse:
        return JSONResponse(
            {"message": "The given data was invalid.", "errors": _validation_errors(exc)},
            status_code=422,
        )
