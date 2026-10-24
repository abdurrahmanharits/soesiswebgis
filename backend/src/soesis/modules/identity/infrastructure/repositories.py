"""SQLAlchemy adapters for the identity repositories and unit of work."""

import uuid
from datetime import UTC, datetime
from typing import Self

from sqlalchemy import Row, exists, func, select, update
from sqlalchemy.orm import Session, sessionmaker

from soesis.modules.identity.domain.model import LoginIdentifier, Organization, Role, User
from soesis.modules.identity.domain.session import RefreshToken
from soesis.modules.identity.infrastructure.tables import organizations, refresh_tokens, users


def _laravel_now() -> datetime:
    # Laravel stores naive UTC timestamps (timestamp without time zone).
    return datetime.now(UTC).replace(tzinfo=None)


def _user_columns():
    return select(users, organizations.c.name.label("organization_name")).outerjoin(
        organizations, organizations.c.id == users.c.organization_id
    )


def _to_user(row: Row) -> User:
    organization = (
        Organization(id=row.organization_id, name=row.organization_name) if row.organization_id is not None else None
    )
    return User(
        id=row.id,
        name=row.name,
        username=row.username,
        email=row.email,
        role=Role(row.role),
        is_active=row.is_active,
        organization=organization,
        password_hash=row.password,
    )


class SqlUserRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    def get(self, user_id: int) -> User | None:
        row = self._session.execute(_user_columns().where(users.c.id == user_id)).first()
        return _to_user(row) if row else None

    def find_by_identifier(self, identifier: LoginIdentifier) -> User | None:
        column = users.c.email if identifier.field == "email" else users.c.username
        row = self._session.execute(_user_columns().where(func.lower(column) == identifier.value)).first()
        return _to_user(row) if row else None

    def list(self) -> list[User]:
        return [_to_user(row) for row in self._session.execute(_user_columns().order_by(users.c.name, users.c.id))]

    def is_taken(self, field: str, value: str, exclude_id: int | None = None) -> bool:
        conditions = [func.lower(users.c[field]) == value.lower()]
        if exclude_id is not None:
            conditions.append(users.c.id != exclude_id)
        return bool(self._session.execute(select(exists().where(*conditions))).scalar())

    def has_admin(self) -> bool:
        return bool(self._session.execute(select(exists().where(users.c.role == Role.ADMIN.value))).scalar())

    def add(self, user: User) -> User:
        now = _laravel_now()
        new_id = self._session.execute(
            users.insert()
            .values(
                name=user.name,
                username=user.username,
                email=user.email,
                password=user.password_hash,
                role=user.role.value,
                is_active=user.is_active,
                organization_id=user.organization.id if user.organization else None,
                created_at=now,
                updated_at=now,
            )
            .returning(users.c.id)
        ).scalar_one()
        return user.with_changes(id=new_id)

    def update(self, user: User) -> User:
        self._session.execute(
            users.update()
            .where(users.c.id == user.id)
            .values(
                name=user.name,
                username=user.username,
                email=user.email,
                password=user.password_hash,
                role=user.role.value,
                is_active=user.is_active,
                organization_id=user.organization.id if user.organization else None,
                updated_at=_laravel_now(),
            )
        )
        return user

    def delete_with_prefix(self, username_prefix: str) -> tuple[str, ...]:
        # Sessions go with the account (ON DELETE CASCADE); history/uploads keep a NULL reference.
        rows = self._session.execute(
            users.delete()
            .where(users.c.username.startswith(username_prefix, autoescape=True))
            .returning(users.c.username)
        )
        return tuple(sorted(row.username for row in rows))


class SqlOrganizationRepository:
    def __init__(self, session: Session) -> None:
        self._session = session

    def get(self, organization_id: int) -> Organization | None:
        row = self._session.execute(select(organizations).where(organizations.c.id == organization_id)).first()
        return Organization(id=row.id, name=row.name) if row else None

    def list_with_user_counts(self) -> list[tuple[Organization, int]]:
        count = (
            select(func.count())
            .select_from(users)
            .where(users.c.organization_id == organizations.c.id)
            .correlate(organizations)
            .scalar_subquery()
        )
        rows = self._session.execute(
            select(organizations.c.id, organizations.c.name, count.label("users")).order_by(organizations.c.name)
        )
        return [(Organization(id=row.id, name=row.name), row.users) for row in rows]

    def is_taken(self, name: str, exclude_id: int | None = None) -> bool:
        conditions = [func.lower(organizations.c.name) == name.lower()]
        if exclude_id is not None:
            conditions.append(organizations.c.id != exclude_id)
        return bool(self._session.execute(select(exists().where(*conditions))).scalar())

    def add(self, organization: Organization) -> Organization:
        now = _laravel_now()
        new_id = self._session.execute(
            organizations.insert()
            .values(name=organization.name, created_at=now, updated_at=now)
            .returning(organizations.c.id)
        ).scalar_one()
        return Organization(id=new_id, name=organization.name)

    def update(self, organization: Organization) -> Organization:
        self._session.execute(
            organizations.update()
            .where(organizations.c.id == organization.id)
            .values(name=organization.name, updated_at=_laravel_now())
        )
        return organization


class SqlRefreshTokenStore:
    def __init__(self, session: Session) -> None:
        self._session = session

    def add(self, token: RefreshToken, token_hash: str, now: datetime) -> None:
        self._session.execute(
            refresh_tokens.insert().values(
                id=uuid.UUID(token.id),
                user_id=token.user_id,
                session_id=uuid.UUID(token.session_id),
                token_hash=token_hash,
                created_at=now,
                expires_at=token.expires_at,
            )
        )

    def find_by_hash(self, token_hash: str) -> RefreshToken | None:
        # FOR UPDATE: two concurrent refreshes with the same token cannot both rotate it.
        row = self._session.execute(
            select(refresh_tokens).where(refresh_tokens.c.token_hash == token_hash).with_for_update()
        ).first()
        if not row:
            return None
        return RefreshToken(
            id=str(row.id),
            user_id=row.user_id,
            session_id=str(row.session_id),
            expires_at=row.expires_at,
            used_at=row.used_at,
            revoked_at=row.revoked_at,
        )

    def mark_used(self, token_id: str, now: datetime) -> None:
        self._session.execute(
            update(refresh_tokens).where(refresh_tokens.c.id == uuid.UUID(token_id)).values(used_at=now)
        )

    def revoke_session(self, session_id: str, now: datetime) -> None:
        self._session.execute(
            update(refresh_tokens)
            .where(refresh_tokens.c.session_id == uuid.UUID(session_id), refresh_tokens.c.revoked_at.is_(None))
            .values(revoked_at=now)
        )

    def revoke_user(self, user_id: int, now: datetime) -> None:
        self._session.execute(
            update(refresh_tokens)
            .where(refresh_tokens.c.user_id == user_id, refresh_tokens.c.revoked_at.is_(None))
            .values(revoked_at=now)
        )

    def is_session_active(self, session_id: str, now: datetime) -> bool:
        """At least one unrevoked, unexpired token remains in the session (rotated ones count as used)."""
        return bool(
            self._session.execute(
                select(
                    exists().where(
                        refresh_tokens.c.session_id == uuid.UUID(session_id),
                        refresh_tokens.c.revoked_at.is_(None),
                        refresh_tokens.c.used_at.is_(None),
                        refresh_tokens.c.expires_at > now,
                    )
                )
            ).scalar()
        )


class SqlIdentityUnitOfWork:
    def __init__(self, session_factory: sessionmaker[Session]) -> None:
        self._session_factory = session_factory

    def __enter__(self) -> Self:
        self._session = self._session_factory()
        self.users = SqlUserRepository(self._session)
        self.organizations = SqlOrganizationRepository(self._session)
        self.refresh_tokens = SqlRefreshTokenStore(self._session)
        return self

    def __exit__(self, *exc: object) -> None:
        # Anything not committed explicitly is rolled back.
        self._session.rollback()
        self._session.close()

    def commit(self) -> None:
        self._session.commit()
