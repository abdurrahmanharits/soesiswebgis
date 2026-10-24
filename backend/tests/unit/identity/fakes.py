"""In-memory adapters for the identity ports: use cases tested without database, Redis or JWT."""

import json
from dataclasses import replace
from datetime import UTC, datetime, timedelta
from itertools import count

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
from soesis.modules.identity.domain.model import LoginIdentifier, Organization, Role, User
from soesis.modules.identity.domain.session import AccessClaims, RefreshToken
from soesis.shared.kernel import Unauthenticated


class FakeClock:
    def __init__(self) -> None:
        self.current = datetime(2026, 10, 24, 9, 0, tzinfo=UTC)

    def now(self) -> datetime:
        return self.current

    def advance(self, **delta: float) -> None:
        self.current += timedelta(**delta)


class FakeHasher:
    def hash(self, password: str) -> str:
        return f"hashed:{password}"

    def verify(self, password: str, password_hash: str | None) -> bool:
        return password_hash == f"hashed:{password}"


class FakeCodec:
    def encode(self, claims: AccessClaims, issued_at: datetime) -> str:
        return json.dumps({"sub": claims.user_id, "sid": claims.session_id, "exp": claims.expires_at.isoformat()})

    def decode(self, token: str, now: datetime) -> AccessClaims:
        try:
            data = json.loads(token)
        except ValueError as error:
            raise Unauthenticated() from error
        expires = datetime.fromisoformat(data["exp"])
        if now > expires:
            raise Unauthenticated()
        return AccessClaims(data["sub"], data["sid"], expires)


class FakeSecrets:
    def __init__(self) -> None:
        self._ids = count(1)

    def new_id(self) -> str:
        return f"id-{next(self._ids)}"

    def new_token(self) -> str:
        return f"token-{next(self._ids)}"

    def digest(self, token: str) -> str:
        return f"digest:{token}"


class FakeLimiter:
    def __init__(self, max_attempts: int = 5) -> None:
        self.failures: dict[str, int] = {}
        self._max = max_attempts

    def retry_after(self, key: str) -> int | None:
        return 42 if self.failures.get(key, 0) >= self._max else None

    def hit(self, key: str) -> None:
        self.failures[key] = self.failures.get(key, 0) + 1

    def clear(self, key: str) -> None:
        self.failures.pop(key, None)


class Store:
    """Shared state behind every unit of work."""

    def __init__(self) -> None:
        self.users: dict[int, User] = {}
        self.organizations: dict[int, Organization] = {}
        self.tokens: dict[str, tuple[RefreshToken, str]] = {}
        self.ids = count(1)


class FakeUsers:
    def __init__(self, store: Store) -> None:
        self.s = store

    def get(self, user_id: int) -> User | None:
        return self.s.users.get(user_id)

    def find_by_identifier(self, identifier: LoginIdentifier) -> User | None:
        return next((u for u in self.s.users.values() if getattr(u, identifier.field) == identifier.value), None)

    def list(self) -> list[User]:
        return sorted(self.s.users.values(), key=lambda u: u.name)

    def is_taken(self, field: str, value: str, exclude_id: int | None = None) -> bool:
        return any(getattr(u, field) == value and u.id != exclude_id for u in self.s.users.values())

    def has_admin(self) -> bool:
        return any(u.role is Role.ADMIN for u in self.s.users.values())

    def add(self, user: User) -> User:
        user = user.with_changes(id=next(self.s.ids))
        self.s.users[user.id] = user
        return user

    def update(self, user: User) -> User:
        self.s.users[user.id] = user
        return user

    def delete_with_prefix(self, username_prefix: str) -> tuple[str, ...]:
        removed = sorted(u.username for u in self.s.users.values() if u.username.startswith(username_prefix))
        self.s.users = {i: u for i, u in self.s.users.items() if not u.username.startswith(username_prefix)}
        return tuple(removed)


class FakeOrganizations:
    def __init__(self, store: Store) -> None:
        self.s = store

    def get(self, organization_id: int) -> Organization | None:
        return self.s.organizations.get(organization_id)

    def list_with_user_counts(self) -> list[tuple[Organization, int]]:
        return [
            (o, sum(1 for u in self.s.users.values() if u.organization and u.organization.id == o.id))
            for o in self.s.organizations.values()
        ]

    def is_taken(self, name: str, exclude_id: int | None = None) -> bool:
        return any(o.name.lower() == name.lower() and o.id != exclude_id for o in self.s.organizations.values())

    def add(self, organization: Organization) -> Organization:
        organization = Organization(id=next(self.s.ids), name=organization.name)
        self.s.organizations[organization.id] = organization
        return organization

    def update(self, organization: Organization) -> Organization:
        self.s.organizations[organization.id] = organization
        return organization


class FakeTokens:
    def __init__(self, store: Store) -> None:
        self.s = store

    def add(self, token: RefreshToken, token_hash: str, now: datetime) -> None:
        self.s.tokens[token.id] = (token, token_hash)

    def find_by_hash(self, token_hash: str) -> RefreshToken | None:
        return next((t for t, h in self.s.tokens.values() if h == token_hash), None)

    def _set(self, predicate, **changes) -> None:
        for key, (token, digest) in list(self.s.tokens.items()):
            if predicate(token) and token.revoked_at is None:
                self.s.tokens[key] = (replace(token, **changes), digest)

    def mark_used(self, token_id: str, now: datetime) -> None:
        token, digest = self.s.tokens[token_id]
        self.s.tokens[token_id] = (replace(token, used_at=now), digest)

    def revoke_session(self, session_id: str, now: datetime) -> None:
        self._set(lambda t: t.session_id == session_id, revoked_at=now)

    def revoke_user(self, user_id: int, now: datetime) -> None:
        self._set(lambda t: t.user_id == user_id, revoked_at=now)

    def is_session_active(self, session_id: str, now: datetime) -> bool:
        return any(t.session_id == session_id and t.is_usable(now) for t, _ in self.s.tokens.values())


class FakeUnitOfWork:
    def __init__(self, store: Store) -> None:
        self.users = FakeUsers(store)
        self.organizations = FakeOrganizations(store)
        self.refresh_tokens = FakeTokens(store)
        self.committed = False

    def __enter__(self):
        return self

    def __exit__(self, *exc: object) -> None:
        pass

    def commit(self) -> None:
        self.committed = True


class Identity:
    """All identity use cases wired to the fakes, like the composition root does with real adapters."""

    def __init__(self) -> None:
        self.store = Store()
        self.clock = FakeClock()
        self.limiter = FakeLimiter()
        secrets = FakeSecrets()
        codec = FakeCodec()
        hasher = FakeHasher()

        def uow() -> FakeUnitOfWork:
            return FakeUnitOfWork(self.store)

        issuer = TokenIssuer(codec, secrets, self.clock, TokenLifetimes())
        self.login = Login(uow, hasher, issuer, secrets, self.limiter)
        self.refresh = RefreshSession(uow, issuer, secrets, self.clock)
        self.logout = Logout(uow, codec, secrets, self.clock)
        self.authenticate = Authenticate(uow, codec, self.clock)
        self.users = UserAdministration(uow, hasher, self.clock)
        self.organizations = OrganizationAdministration(uow)
        self.bootstrap = AccountBootstrap(uow, hasher, self.clock)
        self.demo = DemoAccounts(uow, hasher, self.clock)

    def add_user(
        self, username: str = "rina", role: Role = Role.UPLOADER, password: str = "secret-pass", **extra
    ) -> User:
        return FakeUsers(self.store).add(
            User(
                id=None,
                name=extra.pop("name", username.title()),
                username=username,
                email=extra.pop("email", f"{username}@example.org"),
                role=role,
                password_hash=f"hashed:{password}",
                **extra,
            )
        )
