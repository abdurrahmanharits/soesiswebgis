"""Framework-free building blocks shared by every module's domain and application layers.

Errors raised here are mapped to HTTP responses in exactly one place
(soesis.shared.errors), so use cases never know about status codes.
"""

from datetime import datetime
from typing import Protocol


class ApplicationError(Exception):
    """Base class: a use case refused a request for a reason the caller can be told."""

    def __init__(self, message: str) -> None:
        super().__init__(message)
        self.message = message


class Unauthenticated(ApplicationError):
    def __init__(self, message: str = "Unauthenticated.") -> None:
        super().__init__(message)


class Forbidden(ApplicationError):
    def __init__(self, message: str = "Forbidden.") -> None:
        super().__init__(message)


class NotFound(ApplicationError):
    pass


class ValidationFailed(ApplicationError):
    """Field-level problems, e.g. {"email": ["The email has already been taken."]}."""

    def __init__(self, errors: dict[str, list[str]], message: str = "The given data was invalid.") -> None:
        super().__init__(message)
        self.errors = errors


class TooManyRequests(ApplicationError):
    def __init__(self, message: str, retry_after: int) -> None:
        super().__init__(message)
        self.retry_after = retry_after


class Clock(Protocol):
    """Current time (UTC, timezone-aware). Injected so expiry logic is deterministic in tests."""

    def now(self) -> datetime: ...
