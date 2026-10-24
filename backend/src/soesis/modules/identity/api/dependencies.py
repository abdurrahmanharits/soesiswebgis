"""FastAPI dependencies: the identity services and the signed-in account.

Other modules protect their routers with `CurrentUser` (any active account) and
call the domain policy (soesis.modules.identity.domain.policy) for finer rules.
"""

from typing import Annotated

from fastapi import Depends, Request

from soesis.modules.identity.api.cookies import ACCESS_COOKIE
from soesis.modules.identity.application.services import IdentityServices
from soesis.modules.identity.domain.model import User


def get_identity(request: Request) -> IdentityServices:
    # Assembled once by the composition root (soesis.main).
    return request.app.state.identity


Identity = Annotated[IdentityServices, Depends(get_identity)]


def current_user(request: Request, identity: Identity) -> User:
    """401 {"message": "Unauthenticated."} unless the access cookie maps to an active account and session."""
    return identity.authenticate.execute(request.cookies.get(ACCESS_COOKIE))


CurrentUser = Annotated[User, Depends(current_user)]
