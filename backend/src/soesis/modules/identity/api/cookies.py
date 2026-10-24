"""Auth cookies (decision: tokens live only in httpOnly cookies on the Next.js origin).

soesis_access   JWT, path "/", sent with every API request.
soesis_refresh  opaque token, path "/api/auth", only sent to refresh/logout.
soesis_session  "1", readable by the frontend, so proxy.ts can tell "signed in"
                without seeing any token. Carries no authority: FastAPI decides.
"""

from fastapi import Response

from soesis.modules.identity.application.auth import TokenLifetimes
from soesis.modules.identity.domain.session import IssuedTokens

ACCESS_COOKIE = "soesis_access"
REFRESH_COOKIE = "soesis_refresh"
SESSION_COOKIE = "soesis_session"
REFRESH_PATH = "/api/auth"


def set_auth_cookies(response: Response, tokens: IssuedTokens, lifetimes: TokenLifetimes, secure: bool) -> None:
    common = {"secure": secure, "samesite": "lax"}
    access_age = int(lifetimes.access.total_seconds())
    refresh_age = int(lifetimes.refresh.total_seconds())
    response.set_cookie(ACCESS_COOKIE, tokens.access_token, max_age=access_age, path="/", httponly=True, **common)
    response.set_cookie(
        REFRESH_COOKIE, tokens.refresh_token, max_age=refresh_age, path=REFRESH_PATH, httponly=True, **common
    )
    response.set_cookie(SESSION_COOKIE, "1", max_age=refresh_age, path="/", httponly=False, **common)


def clear_auth_cookies(response: Response, secure: bool) -> None:
    for name, path in ((ACCESS_COOKIE, "/"), (REFRESH_COOKIE, REFRESH_PATH), (SESSION_COOKIE, "/")):
        response.delete_cookie(name, path=path, secure=secure, samesite="lax", httponly=name != SESSION_COOKIE)
