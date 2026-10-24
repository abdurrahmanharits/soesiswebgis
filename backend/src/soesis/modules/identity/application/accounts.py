"""Use cases: user and organization management (admin), initial admin and password reset (CLI)."""

from dataclasses import dataclass

from soesis.modules.identity.application.ports import IdentityUnitOfWork, PasswordHasher, UnitOfWorkFactory
from soesis.modules.identity.domain.model import (
    EMAIL_PATTERN,
    MIN_PASSWORD_LENGTH,
    USERNAME_PATTERN,
    LoginIdentifier,
    Organization,
    Role,
    User,
    normalize_identifier,
)
from soesis.modules.identity.domain.policy import can_manage_users, ensure_admin_keeps_access
from soesis.shared.kernel import ApplicationError, Clock, Forbidden, NotFound, ValidationFailed

USERNAME_RULE = "Use 3–30 lowercase letters, digits, dots or underscores."


@dataclass(frozen=True, slots=True)
class UserInput:
    name: str
    username: str
    email: str
    role: Role
    organization_id: int | None = None
    is_active: bool = True


def _password_errors(password: str | None, required: bool) -> list[str]:
    if not password:
        return ["The password field is required."] if required else []
    if len(password) < MIN_PASSWORD_LENGTH:
        return [f"The password field must be at least {MIN_PASSWORD_LENGTH} characters."]
    return []


def _validate(
    uow: IdentityUnitOfWork, data: UserInput, exclude_id: int | None
) -> tuple[UserInput, Organization | None]:
    data = UserInput(
        name=data.name.strip(),
        username=normalize_identifier(data.username),
        email=normalize_identifier(data.email),
        role=data.role,
        organization_id=data.organization_id,
        is_active=data.is_active,
    )
    errors: dict[str, list[str]] = {}

    if not data.name:
        errors["name"] = ["The name field is required."]
    if not USERNAME_PATTERN.match(data.username):
        errors["username"] = [USERNAME_RULE]
    elif uow.users.is_taken("username", data.username, exclude_id):
        errors["username"] = ["The username has already been taken."]
    if not EMAIL_PATTERN.match(data.email):
        errors["email"] = ["The email field must be a valid email address."]
    elif uow.users.is_taken("email", data.email, exclude_id):
        errors["email"] = ["The email has already been taken."]

    organization = None
    if data.organization_id is not None:
        organization = uow.organizations.get(data.organization_id)
        if organization is None:
            errors["organization_id"] = ["The selected organization is invalid."]

    if errors:
        raise ValidationFailed(errors)

    return data, organization


def _require_admin(actor: User) -> None:
    if not can_manage_users(actor):
        raise Forbidden()


class UserAdministration:
    """Admin-only: list, create and edit accounts. Users are deactivated, never deleted."""

    def __init__(self, uow: UnitOfWorkFactory, hasher: PasswordHasher, clock: Clock) -> None:
        self._uow = uow
        self._hasher = hasher
        self._clock = clock

    def list(self, actor: User) -> list[User]:
        _require_admin(actor)
        with self._uow() as uow:
            return uow.users.list()

    def create(self, actor: User, data: UserInput, password: str) -> User:
        _require_admin(actor)
        with self._uow() as uow:
            return _create_user(uow, self._hasher, data, password)

    def update(self, actor: User, user_id: int, data: UserInput) -> User:
        _require_admin(actor)
        with self._uow() as uow:
            target = uow.users.get(user_id)
            if target is None:
                raise NotFound("User not found.")

            data, organization = _validate(uow, data, exclude_id=user_id)
            ensure_admin_keeps_access(actor, target, data.role, data.is_active)

            updated = uow.users.update(
                target.with_changes(
                    name=data.name,
                    username=data.username,
                    email=data.email,
                    role=data.role,
                    is_active=data.is_active,
                    organization=organization,
                )
            )
            if target.is_active and not data.is_active:
                # Deactivation also ends every session (it is checked per request as well).
                uow.refresh_tokens.revoke_user(user_id, self._clock.now())
            uow.commit()
            return updated

    def reset_password(self, actor: User, user_id: int, password: str) -> None:
        _require_admin(actor)
        with self._uow() as uow:
            target = uow.users.get(user_id)
            if target is None:
                raise NotFound("User not found.")
            _set_password(uow, self._hasher, self._clock, target, password)


def _create_user(uow: IdentityUnitOfWork, hasher: PasswordHasher, data: UserInput, password: str) -> User:
    if errors := _password_errors(password, required=True):
        raise ValidationFailed({"password": errors})

    data, organization = _validate(uow, data, exclude_id=None)
    user = uow.users.add(
        User(
            id=None,
            name=data.name,
            username=data.username,
            email=data.email,
            role=data.role,
            is_active=data.is_active,
            organization=organization,
            password_hash=hasher.hash(password),
        )
    )
    uow.commit()
    return user


def _set_password(uow: IdentityUnitOfWork, hasher: PasswordHasher, clock: Clock, user: User, password: str) -> None:
    if errors := _password_errors(password, required=True):
        raise ValidationFailed({"password": errors})

    assert user.id is not None
    uow.users.update(user.with_changes(password_hash=hasher.hash(password)))
    # A new password signs the account out everywhere.
    uow.refresh_tokens.revoke_user(user.id, clock.now())
    uow.commit()


class OrganizationAdministration:
    """Admin-only list of organizations (provenance of users and their uploads)."""

    def __init__(self, uow: UnitOfWorkFactory) -> None:
        self._uow = uow

    def list(self, actor: User) -> list[tuple[Organization, int]]:
        _require_admin(actor)
        with self._uow() as uow:
            return uow.organizations.list_with_user_counts()

    def create(self, actor: User, name: str) -> Organization:
        _require_admin(actor)
        with self._uow() as uow:
            name = self._valid_name(uow, name, exclude_id=None)
            organization = uow.organizations.add(Organization(id=None, name=name))
            uow.commit()
            return organization

    def rename(self, actor: User, organization_id: int, name: str) -> Organization:
        _require_admin(actor)
        with self._uow() as uow:
            organization = uow.organizations.get(organization_id)
            if organization is None:
                raise NotFound("Organization not found.")
            name = self._valid_name(uow, name, exclude_id=organization_id)
            renamed = uow.organizations.update(Organization(id=organization_id, name=name))
            uow.commit()
            return renamed

    @staticmethod
    def _valid_name(uow: IdentityUnitOfWork, name: str, exclude_id: int | None) -> str:
        name = name.strip()
        if not name:
            raise ValidationFailed({"name": ["The name field is required."]})
        if uow.organizations.is_taken(name, exclude_id):
            raise ValidationFailed({"name": ["The name has already been taken."]})
        return name


class AccountBootstrap:
    """CLI use cases: the first admin (once) and a password reset when no admin can sign in."""

    def __init__(self, uow: UnitOfWorkFactory, hasher: PasswordHasher, clock: Clock) -> None:
        self._uow = uow
        self._hasher = hasher
        self._clock = clock

    def create_initial_admin(self, name: str, username: str, email: str, password: str) -> User:
        with self._uow() as uow:
            if uow.users.has_admin():
                raise ApplicationError(
                    "An admin account already exists. Create further users in the admin UI (/admin/users)."
                )
            data = UserInput(name=name, username=username, email=email, role=Role.ADMIN)
            return _create_user(uow, self._hasher, data, password)

    def reset_password(self, login: str, password: str) -> User:
        with self._uow() as uow:
            user = uow.users.find_by_identifier(LoginIdentifier.parse(login))
            if user is None:
                raise NotFound("No user with that e-mail or username.")
            _set_password(uow, self._hasher, self._clock, user, password)
            return user
