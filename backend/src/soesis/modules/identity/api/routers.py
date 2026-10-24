"""/api/auth (login, refresh, logout, me) and /api/admin (users, organizations)."""

from fastapi import APIRouter, Request, Response, status

from soesis.modules.identity.api.cookies import ACCESS_COOKIE, REFRESH_COOKIE, clear_auth_cookies, set_auth_cookies
from soesis.modules.identity.api.dependencies import CurrentUser, Identity
from soesis.modules.identity.api.schemas import (
    LoginRequest,
    MessageResponse,
    OrganizationResponse,
    OrganizationWithCountResponse,
    OrganizationWriteRequest,
    PasswordResetRequest,
    SessionResponse,
    UserCreateRequest,
    UserResponse,
    UserWriteRequest,
)
from soesis.modules.identity.application.accounts import UserInput
from soesis.modules.identity.application.auth import AuthResult
from soesis.modules.identity.domain.model import Role
from soesis.shared.errors import application_error_response
from soesis.shared.kernel import Unauthenticated

ERRORS = {
    401: {"model": MessageResponse, "description": "Not signed in (or session ended)"},
    403: {"model": MessageResponse, "description": "Signed in without the required role"},
}

auth_router = APIRouter(prefix="/api/auth", tags=["auth"])
admin_router = APIRouter(prefix="/api/admin", tags=["admin"], responses=ERRORS)


def _session(response: Response, request: Request, result: AuthResult) -> SessionResponse:
    identity = request.app.state.identity
    set_auth_cookies(response, result.tokens, identity.lifetimes, secure=request.app.state.settings.cookie_secure)
    return SessionResponse(user=UserResponse.of(result.user), access_expires_at=result.tokens.access_expires_at)


def _client_ip(request: Request) -> str:
    # Behind the Next.js proxy uvicorn resolves X-Forwarded-For (--proxy-headers).
    return request.client.host if request.client else "unknown"


@auth_router.post(
    "/login",
    response_model=SessionResponse,
    responses={401: {"model": MessageResponse}, 429: {"model": MessageResponse}},
    summary="Sign in with e-mail or username; sets the httpOnly auth cookies",
)
def login(body: LoginRequest, request: Request, response: Response, identity: Identity) -> SessionResponse:
    result = identity.login.execute(body.login, body.password, _client_ip(request))
    return _session(response, request, result)


@auth_router.post(
    "/refresh",
    response_model=SessionResponse,
    responses={401: {"model": MessageResponse}},
    summary="Rotate the refresh token and issue a new access token",
)
def refresh(request: Request, response: Response, identity: Identity) -> Response | SessionResponse:
    try:
        result = identity.refresh.execute(request.cookies.get(REFRESH_COOKIE))
    except Unauthenticated as error:
        # A dead refresh token also clears the browser's cookies.
        failed = application_error_response(error)
        clear_auth_cookies(failed, secure=request.app.state.settings.cookie_secure)
        return failed
    return _session(response, request, result)


@auth_router.post("/logout", status_code=status.HTTP_204_NO_CONTENT, summary="End this session and clear cookies")
def logout(request: Request, identity: Identity) -> Response:
    identity.logout.execute(request.cookies.get(REFRESH_COOKIE), request.cookies.get(ACCESS_COOKIE))
    response = Response(status_code=status.HTTP_204_NO_CONTENT)
    clear_auth_cookies(response, secure=request.app.state.settings.cookie_secure)
    return response


@auth_router.get("/me", response_model=UserResponse, responses=ERRORS, summary="The signed-in account")
def me(user: CurrentUser) -> UserResponse:
    return UserResponse.of(user)


# ---- Admin -------------------------------------------------------------------


def _user_input(body: UserWriteRequest) -> UserInput:
    return UserInput(
        name=body.name,
        username=body.username,
        email=body.email,
        role=Role(body.role),
        organization_id=body.organization_id,
        is_active=body.is_active,
    )


@admin_router.get("/users", response_model=list[UserResponse])
def list_users(user: CurrentUser, identity: Identity) -> list[UserResponse]:
    return [UserResponse.of(u) for u in identity.users.list(user)]


@admin_router.post("/users", response_model=UserResponse, status_code=status.HTTP_201_CREATED)
def create_user(body: UserCreateRequest, user: CurrentUser, identity: Identity) -> UserResponse:
    return UserResponse.of(identity.users.create(user, _user_input(body), body.password))


@admin_router.patch("/users/{user_id}", response_model=UserResponse)
def update_user(user_id: int, body: UserWriteRequest, user: CurrentUser, identity: Identity) -> UserResponse:
    return UserResponse.of(identity.users.update(user, user_id, _user_input(body)))


@admin_router.post("/users/{user_id}/reset-password", status_code=status.HTTP_204_NO_CONTENT)
def reset_password(user_id: int, body: PasswordResetRequest, user: CurrentUser, identity: Identity) -> Response:
    identity.users.reset_password(user, user_id, body.password)
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@admin_router.get("/organizations", response_model=list[OrganizationWithCountResponse])
def list_organizations(user: CurrentUser, identity: Identity) -> list[OrganizationWithCountResponse]:
    return [
        OrganizationWithCountResponse(id=o.id, name=o.name, users_count=count)
        for o, count in identity.organizations.list(user)
    ]


@admin_router.post("/organizations", response_model=OrganizationResponse, status_code=status.HTTP_201_CREATED)
def create_organization(body: OrganizationWriteRequest, user: CurrentUser, identity: Identity) -> OrganizationResponse:
    return OrganizationResponse.of(identity.organizations.create(user, body.name))


@admin_router.patch("/organizations/{organization_id}", response_model=OrganizationResponse)
def rename_organization(
    organization_id: int, body: OrganizationWriteRequest, user: CurrentUser, identity: Identity
) -> OrganizationResponse:
    return OrganizationResponse.of(identity.organizations.rename(user, organization_id, body.name))
