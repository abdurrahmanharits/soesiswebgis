"""CSRF protection for cookie-authenticated requests: SameSite=Lax cookies plus an Origin check.

Every state-changing request (POST/PUT/PATCH/DELETE) must come from an allowed
browser origin. Requests without Origin/Referer are accepted only when they carry
no SoESIS cookies (scripts and API clients cannot be tricked into using a
browser session).
"""

from urllib.parse import urlsplit

from starlette.requests import Request
from starlette.responses import JSONResponse
from starlette.types import ASGIApp, Receive, Scope, Send

SAFE_METHODS = frozenset({"GET", "HEAD", "OPTIONS"})
COOKIE_PREFIX = "soesis_"


def _origin_of(url: str) -> str | None:
    parts = urlsplit(url)
    return f"{parts.scheme}://{parts.netloc}" if parts.scheme and parts.netloc else None


class OriginCheckMiddleware:
    def __init__(self, app: ASGIApp, allowed_origins: list[str]) -> None:
        self._app = app
        self._allowed = {origin.rstrip("/") for origin in allowed_origins}

    async def __call__(self, scope: Scope, receive: Receive, send: Send) -> None:
        if scope["type"] == "http" and scope["method"] not in SAFE_METHODS:
            request = Request(scope)
            origin = request.headers.get("origin") or _origin_of(request.headers.get("referer", ""))
            has_session_cookie = any(name.startswith(COOKIE_PREFIX) for name in request.cookies)

            if (origin and origin not in self._allowed) or (not origin and has_session_cookie):
                response = JSONResponse({"message": "Cross-site request rejected."}, status_code=403)
                await response(scope, receive, send)
                return

        await self._app(scope, receive, send)
