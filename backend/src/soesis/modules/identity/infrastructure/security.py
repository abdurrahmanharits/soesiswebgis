"""Password hashing (bcrypt, Laravel-compatible), JWT access tokens (PyJWT), opaque refresh tokens."""

import hashlib
import secrets
import uuid
from datetime import UTC, datetime

import bcrypt
import jwt

from soesis.modules.identity.domain.session import AccessClaims
from soesis.shared.kernel import Unauthenticated

# PHP's crypt() (Laravel) only reads the first 72 bytes; bcrypt>=5 rejects longer input instead.
_BCRYPT_MAX_BYTES = 72


def _bcrypt_input(password: str) -> bytes:
    return password.encode("utf-8")[:_BCRYPT_MAX_BYTES]


class BcryptPasswordHasher:
    """bcrypt cost 12 with the "$2y$" prefix Laravel writes and requires.

    $2y$ and $2b$ are the same algorithm, but Laravel's Hash::check() throws
    "This password does not use the Bcrypt algorithm" for $2b$ — the prefix the
    Python library emits — so hashes are relabelled before they are stored.
    """

    def __init__(self, rounds: int = 12) -> None:
        self._rounds = rounds
        self._dummy = self.hash(secrets.token_urlsafe(16))

    def hash(self, password: str) -> str:
        hashed = bcrypt.hashpw(_bcrypt_input(password), bcrypt.gensalt(self._rounds)).decode("ascii")
        return "$2y$" + hashed[4:] if hashed.startswith("$2b$") else hashed

    def verify(self, password: str, password_hash: str | None) -> bool:
        candidate = password_hash or self._dummy
        try:
            matches = bcrypt.checkpw(_bcrypt_input(password), candidate.encode("ascii"))
        except ValueError:  # not a bcrypt hash at all
            return False
        return matches and password_hash is not None


class JwtAccessTokenCodec:
    """HS256 access tokens. Only the backend holds the secret (Next.js never verifies them)."""

    ALGORITHM = "HS256"
    ISSUER = "soesis"
    LEEWAY_SECONDS = 10

    def __init__(self, secret: str) -> None:
        if len(secret) < 32:
            raise ValueError("SOESIS_JWT_SECRET must be at least 32 characters.")
        self._secret = secret

    def encode(self, claims: AccessClaims, issued_at: datetime) -> str:
        return jwt.encode(
            {
                "iss": self.ISSUER,
                "typ": "access",
                "sub": str(claims.user_id),
                "sid": claims.session_id,
                "jti": uuid.uuid4().hex,
                "iat": int(issued_at.timestamp()),
                "exp": int(claims.expires_at.timestamp()),
            },
            self._secret,
            algorithm=self.ALGORITHM,
        )

    def decode(self, token: str, now: datetime) -> AccessClaims:
        try:
            payload = jwt.decode(
                token,
                self._secret,
                algorithms=[self.ALGORITHM],
                issuer=self.ISSUER,
                leeway=self.LEEWAY_SECONDS,
                # Time claims are checked below against the injected clock, not the system clock.
                options={"require": ["exp", "iat", "sub", "sid"], "verify_exp": False, "verify_iat": False},
            )
            expires_at = datetime.fromtimestamp(payload["exp"], UTC)
            # Expiry checked against the injected clock (deterministic tests), with the same leeway.
            if (now - expires_at).total_seconds() > self.LEEWAY_SECONDS or payload.get("typ") != "access":
                raise Unauthenticated()
            return AccessClaims(user_id=int(payload["sub"]), session_id=str(payload["sid"]), expires_at=expires_at)
        except (jwt.PyJWTError, ValueError, KeyError) as error:
            raise Unauthenticated() from error


class TokenSecrets:
    """Ids, opaque refresh tokens and their SHA-256 digests (only the digest is stored)."""

    def new_id(self) -> str:
        return str(uuid.uuid4())

    def new_token(self) -> str:
        return secrets.token_urlsafe(48)

    def digest(self, token: str) -> str:
        return hashlib.sha256(token.encode("utf-8")).hexdigest()


class SystemClock:
    def now(self) -> datetime:
        return datetime.now(UTC)
