"""Use cases: login, refresh, logout and per-request authentication."""

from dataclasses import dataclass
from datetime import timedelta

from soesis.modules.identity.application.ports import (
    AccessTokenCodec,
    IdentityUnitOfWork,
    LoginRateLimiter,
    PasswordHasher,
    SecretGenerator,
    UnitOfWorkFactory,
)
from soesis.modules.identity.domain.model import LoginIdentifier, User
from soesis.modules.identity.domain.session import AccessClaims, IssuedTokens, RefreshToken
from soesis.shared.kernel import Clock, TooManyRequests, Unauthenticated

INVALID_CREDENTIALS = "The provided credentials are invalid."
MAX_LOGIN_ATTEMPTS = 5  # failures per minute per (login, IP); see the rate limiter adapter


@dataclass(frozen=True, slots=True)
class TokenLifetimes:
    access: timedelta = timedelta(minutes=15)
    refresh: timedelta = timedelta(days=7)


@dataclass(frozen=True, slots=True)
class AuthResult:
    user: User
    tokens: IssuedTokens


class TokenIssuer:
    """Creates an access token plus a new refresh token in the given session."""

    def __init__(
        self, codec: AccessTokenCodec, secrets: SecretGenerator, clock: Clock, lifetimes: TokenLifetimes
    ) -> None:
        self._codec = codec
        self._secrets = secrets
        self._clock = clock
        self._lifetimes = lifetimes

    def issue(self, uow: IdentityUnitOfWork, user: User, session_id: str) -> IssuedTokens:
        assert user.id is not None
        now = self._clock.now()

        refresh_token = self._secrets.new_token()
        record = RefreshToken(
            id=self._secrets.new_id(),
            user_id=user.id,
            session_id=session_id,
            expires_at=now + self._lifetimes.refresh,
        )
        uow.refresh_tokens.add(record, self._secrets.digest(refresh_token), now)

        access_expires = now + self._lifetimes.access
        access_token = self._codec.encode(AccessClaims(user.id, session_id, access_expires), issued_at=now)

        return IssuedTokens(access_token, access_expires, refresh_token, record.expires_at)


class Login:
    def __init__(
        self,
        uow: UnitOfWorkFactory,
        hasher: PasswordHasher,
        issuer: TokenIssuer,
        secrets: SecretGenerator,
        limiter: LoginRateLimiter,
    ) -> None:
        self._uow = uow
        self._hasher = hasher
        self._issuer = issuer
        self._secrets = secrets
        self._limiter = limiter

    def execute(self, login: str, password: str, client_ip: str) -> AuthResult:
        identifier = LoginIdentifier.parse(login)
        key = f"{identifier.value}|{client_ip}"

        if (wait := self._limiter.retry_after(key)) is not None:
            raise TooManyRequests(f"Too many login attempts. Please try again in {wait} seconds.", retry_after=wait)

        with self._uow() as uow:
            user = uow.users.find_by_identifier(identifier)
            # Always verify (dummy hash when the account is unknown): same timing, same message.
            valid = self._hasher.verify(password, user.password_hash if user else None)

            if user is None or not valid or not user.is_active:
                self._limiter.hit(key)
                raise Unauthenticated(INVALID_CREDENTIALS)

            self._limiter.clear(key)
            tokens = self._issuer.issue(uow, user, session_id=self._secrets.new_id())
            uow.commit()

        return AuthResult(user, tokens)


class RefreshSession:
    """Rotates the refresh token. A replayed (already rotated) token ends the whole session."""

    def __init__(self, uow: UnitOfWorkFactory, issuer: TokenIssuer, secrets: SecretGenerator, clock: Clock) -> None:
        self._uow = uow
        self._issuer = issuer
        self._secrets = secrets
        self._clock = clock

    def execute(self, refresh_token: str | None) -> AuthResult:
        if not refresh_token:
            raise Unauthenticated()

        now = self._clock.now()

        with self._uow() as uow:
            record = uow.refresh_tokens.find_by_hash(self._secrets.digest(refresh_token))

            if record is None:
                raise Unauthenticated()

            if record.is_replay(now):
                uow.refresh_tokens.revoke_session(record.session_id, now)
                uow.commit()
                raise Unauthenticated()

            if not record.is_usable(now):
                raise Unauthenticated()

            user = uow.users.get(record.user_id)
            if user is None or not user.is_active:
                uow.refresh_tokens.revoke_user(record.user_id, now)
                uow.commit()
                raise Unauthenticated()

            uow.refresh_tokens.mark_used(record.id, now)
            tokens = self._issuer.issue(uow, user, session_id=record.session_id)
            uow.commit()

        return AuthResult(user, tokens)


class Logout:
    def __init__(self, uow: UnitOfWorkFactory, codec: AccessTokenCodec, secrets: SecretGenerator, clock: Clock) -> None:
        self._uow = uow
        self._codec = codec
        self._secrets = secrets
        self._clock = clock

    def execute(self, refresh_token: str | None, access_token: str | None) -> None:
        """Ends the session identified by either token; unknown or expired tokens are ignored."""
        now = self._clock.now()

        with self._uow() as uow:
            session_id = None

            if refresh_token and (record := uow.refresh_tokens.find_by_hash(self._secrets.digest(refresh_token))):
                session_id = record.session_id
            elif access_token:
                try:
                    session_id = self._codec.decode(access_token, now).session_id
                except Unauthenticated:
                    session_id = None

            if session_id:
                uow.refresh_tokens.revoke_session(session_id, now)
                uow.commit()


class Authenticate:
    """Resolves the access token to the current account, from the database on every request.

    Deactivation, role changes, logout and password resets therefore apply immediately.
    """

    def __init__(self, uow: UnitOfWorkFactory, codec: AccessTokenCodec, clock: Clock) -> None:
        self._uow = uow
        self._codec = codec
        self._clock = clock

    def execute(self, access_token: str | None) -> User:
        if not access_token:
            raise Unauthenticated()

        now = self._clock.now()
        claims = self._codec.decode(access_token, now)

        with self._uow() as uow:
            if not uow.refresh_tokens.is_session_active(claims.session_id, now):
                raise Unauthenticated()

            user = uow.users.get(claims.user_id)

        if user is None or not user.is_active:
            raise Unauthenticated()

        return user
