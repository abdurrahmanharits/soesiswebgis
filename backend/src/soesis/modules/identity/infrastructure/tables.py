"""SQLAlchemy Core tables of the identity module.

users / organizations mirror the Laravel schema (Alembic 0001_baseline);
auth_refresh_tokens is new (0002) and unknown to Laravel.
"""

from sqlalchemy import BigInteger, Boolean, Column, DateTime, ForeignKey, MetaData, String, Table, Uuid

metadata = MetaData()

organizations = Table(
    "organizations",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("name", String(255), nullable=False, unique=True),
    Column("created_at", DateTime),
    Column("updated_at", DateTime),
)

users = Table(
    "users",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("name", String(255), nullable=False),
    Column("email", String(255), nullable=False, unique=True),
    Column("username", String(30), nullable=False, unique=True),
    Column("password", String(255), nullable=False),
    Column("role", String(255), nullable=False),
    Column("is_active", Boolean, nullable=False),
    Column("organization_id", BigInteger, ForeignKey("organizations.id")),
    Column("created_at", DateTime),
    Column("updated_at", DateTime),
)

refresh_tokens = Table(
    "auth_refresh_tokens",
    metadata,
    Column("id", Uuid, primary_key=True),
    Column("user_id", BigInteger, ForeignKey("users.id"), nullable=False),
    Column("session_id", Uuid, nullable=False),
    Column("token_hash", String(64), nullable=False, unique=True),
    Column("created_at", DateTime(timezone=True), nullable=False),
    Column("expires_at", DateTime(timezone=True), nullable=False),
    Column("used_at", DateTime(timezone=True)),
    Column("revoked_at", DateTime(timezone=True)),
)
