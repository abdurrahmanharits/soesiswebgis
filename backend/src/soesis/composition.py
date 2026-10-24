"""Builds module services from settings and infrastructure. Shared by the API (main.py) and the CLI.

The only place outside soesis.main that knows concrete adapters.
"""

from datetime import timedelta

from redis import Redis
from sqlalchemy import Engine

from soesis.modules.identity.application.accounts import (
    AccountBootstrap,
    OrganizationAdministration,
    UserAdministration,
)
from soesis.modules.identity.application.auth import (
    Authenticate,
    Login,
    Logout,
    RefreshSession,
    TokenIssuer,
    TokenLifetimes,
)
from soesis.modules.identity.application.demo_seed import DemoAccounts
from soesis.modules.identity.application.services import IdentityServices
from soesis.modules.identity.infrastructure.rate_limit import RedisLoginRateLimiter
from soesis.modules.identity.infrastructure.repositories import SqlIdentityUnitOfWork
from soesis.modules.identity.infrastructure.security import (
    BcryptPasswordHasher,
    JwtAccessTokenCodec,
    SystemClock,
    TokenSecrets,
)
from soesis.shared.config import Settings
from soesis.shared.db import build_session_factory


def build_identity_services(settings: Settings, engine: Engine, rate_limit_redis: Redis) -> IdentityServices:
    session_factory = build_session_factory(engine)

    def uow() -> SqlIdentityUnitOfWork:
        return SqlIdentityUnitOfWork(session_factory)

    clock = SystemClock()
    hasher = BcryptPasswordHasher()
    codec = JwtAccessTokenCodec(settings.jwt_secret)
    secrets = TokenSecrets()
    lifetimes = TokenLifetimes(
        access=timedelta(minutes=settings.access_token_minutes),
        refresh=timedelta(days=settings.refresh_token_days),
    )
    issuer = TokenIssuer(codec, secrets, clock, lifetimes)

    return IdentityServices(
        login=Login(uow, hasher, issuer, secrets, RedisLoginRateLimiter(rate_limit_redis)),
        refresh=RefreshSession(uow, issuer, secrets, clock),
        logout=Logout(uow, codec, secrets, clock),
        authenticate=Authenticate(uow, codec, clock),
        users=UserAdministration(uow, hasher, clock),
        organizations=OrganizationAdministration(uow),
        bootstrap=AccountBootstrap(uow, hasher, clock),
        demo=DemoAccounts(uow, hasher, clock),
        lifetimes=lifetimes,
    )
