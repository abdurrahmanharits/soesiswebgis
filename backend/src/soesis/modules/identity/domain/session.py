"""Sign-in sessions: one family of rotating refresh tokens per login.

The access token carries the session (family) id, so ending a session — logout,
replay detection, password reset, deactivation — takes effect on the very next
request instead of when the 15-minute access token expires.
"""

from dataclasses import dataclass
from datetime import datetime, timedelta

# A refresh token reused within this window right after its rotation is treated as a
# harmless race (two tabs refreshing at once): rejected, but the session survives.
ROTATION_GRACE = timedelta(seconds=10)


@dataclass(frozen=True, slots=True)
class RefreshToken:
    id: str
    user_id: int
    session_id: str
    expires_at: datetime
    used_at: datetime | None = None
    revoked_at: datetime | None = None

    def is_usable(self, now: datetime) -> bool:
        return self.used_at is None and self.revoked_at is None and now < self.expires_at

    def is_replay(self, now: datetime) -> bool:
        """Already rotated and presented again outside the grace window: probably stolen."""
        return self.used_at is not None and self.revoked_at is None and now - self.used_at > ROTATION_GRACE


@dataclass(frozen=True, slots=True)
class AccessClaims:
    user_id: int
    session_id: str
    expires_at: datetime


@dataclass(frozen=True, slots=True)
class IssuedTokens:
    access_token: str
    access_expires_at: datetime
    refresh_token: str
    refresh_expires_at: datetime
