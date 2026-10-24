"""Accounts, roles and organizations (mirrors the Laravel users/organizations tables)."""

import re
from dataclasses import dataclass, replace
from enum import StrEnum


class Role(StrEnum):
    """One role per user. Guests (no account) only see the public area."""

    UPLOADER = "uploader"
    CURATOR = "curator"
    ADMIN = "admin"

    @property
    def label(self) -> str:
        return self.value.capitalize()


USERNAME_PATTERN = re.compile(r"^[a-z0-9._]{3,30}$")
EMAIL_PATTERN = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")
MIN_PASSWORD_LENGTH = 8


def normalize_identifier(value: str) -> str:
    """Usernames and e-mail addresses are stored and compared lowercase."""
    return value.strip().lower()


@dataclass(frozen=True, slots=True)
class Organization:
    id: int | None
    name: str


@dataclass(frozen=True, slots=True)
class User:
    id: int | None
    name: str
    username: str
    email: str
    role: Role
    is_active: bool = True
    organization: Organization | None = None
    password_hash: str = ""

    @property
    def is_admin(self) -> bool:
        return self.role is Role.ADMIN

    def with_changes(self, **changes: object) -> "User":
        return replace(self, **changes)


@dataclass(frozen=True, slots=True)
class LoginIdentifier:
    """What the user typed in "E-mail or username", normalised."""

    field: str  # "email" | "username"
    value: str

    @classmethod
    def parse(cls, raw: str) -> "LoginIdentifier":
        value = normalize_identifier(raw)
        return cls(field="email" if "@" in value else "username", value=value)
