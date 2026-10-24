"""Use case: demo accounts for local development and demos (one per role, plus edge cases).

All demo usernames start with "demo." so they are easy to recognise and remove.
The password is supplied by the caller (environment variable or hidden prompt),
never stored in the repository. Refused in production.
"""

from dataclasses import dataclass

from soesis.modules.identity.application.ports import PasswordHasher, UnitOfWorkFactory
from soesis.modules.identity.domain.model import MIN_PASSWORD_LENGTH, LoginIdentifier, Organization, Role, User
from soesis.shared.kernel import ApplicationError, Clock, ValidationFailed

DEMO_PREFIX = "demo."
DEMO_ORGANIZATION = "Demo Organization"


@dataclass(frozen=True, slots=True)
class DemoAccount:
    username: str
    name: str
    role: Role
    is_active: bool = True
    purpose: str = ""

    @property
    def email(self) -> str:
        return f"{self.username}@example.invalid"  # reserved TLD: never a real mailbox


DEMO_ACCOUNTS = (
    DemoAccount("demo.uploader", "Demo Uploader", Role.UPLOADER, purpose="uploads; edits only own metadata"),
    DemoAccount("demo.curator", "Demo Curator", Role.CURATOR, purpose="approves, rejects, reopens"),
    DemoAccount("demo.curator2", "Demo Curator Two", Role.CURATOR, purpose="approves uploads of demo.curator"),
    DemoAccount("demo.admin", "Demo Admin", Role.ADMIN, purpose="manages users and organizations"),
    DemoAccount("demo.inactive", "Demo Inactive", Role.UPLOADER, is_active=False, purpose="cannot sign in"),
)


@dataclass(frozen=True, slots=True)
class SeedReport:
    created: tuple[str, ...]
    updated: tuple[str, ...]
    skipped: tuple[str, ...]


class DemoAccounts:
    def __init__(self, uow: UnitOfWorkFactory, hasher: PasswordHasher, clock: Clock) -> None:
        self._uow = uow
        self._hasher = hasher
        self._clock = clock

    def seed(self, password: str, environment: str, reset_passwords: bool = False) -> SeedReport:
        """Creates missing demo accounts; existing ones are left alone unless reset_passwords."""
        _ensure_not_production(environment)
        if len(password) < MIN_PASSWORD_LENGTH:
            raise ValidationFailed(
                {"password": [f"The password field must be at least {MIN_PASSWORD_LENGTH} characters."]}
            )

        created, updated, skipped = [], [], []

        with self._uow() as uow:
            organization = next(
                (o for o, _ in uow.organizations.list_with_user_counts() if o.name == DEMO_ORGANIZATION), None
            ) or uow.organizations.add(Organization(id=None, name=DEMO_ORGANIZATION))

            for account in DEMO_ACCOUNTS:
                existing = uow.users.find_by_identifier(LoginIdentifier("username", account.username))
                if existing is None:
                    if uow.users.is_taken("email", account.email):
                        raise ApplicationError(f"The e-mail {account.email} is already used by another account.")
                    uow.users.add(
                        User(
                            id=None,
                            name=account.name,
                            username=account.username,
                            email=account.email,
                            role=account.role,
                            is_active=account.is_active,
                            organization=organization,
                            password_hash=self._hasher.hash(password),
                        )
                    )
                    created.append(account.username)
                elif reset_passwords:
                    uow.users.update(existing.with_changes(password_hash=self._hasher.hash(password)))
                    uow.refresh_tokens.revoke_user(existing.id, self._clock.now())
                    updated.append(account.username)
                else:
                    skipped.append(account.username)

            uow.commit()

        return SeedReport(tuple(created), tuple(updated), tuple(skipped))

    def remove(self, environment: str) -> tuple[str, ...]:
        """Deletes the demo accounts (and their sessions). Their curation history keeps the name snapshot."""
        _ensure_not_production(environment)
        with self._uow() as uow:
            removed = uow.users.delete_with_prefix(DEMO_PREFIX)
            uow.commit()
        return removed


def _ensure_not_production(environment: str) -> None:
    if environment.lower() in {"production", "prod"}:
        raise ApplicationError("Demo accounts are not available in production.")
