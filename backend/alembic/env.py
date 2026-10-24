"""Alembic environment. Connects as the migrator role (schema owner).

Laravel-only tables (sessions, cache, jobs, migrations, ...) are legacy and not
managed here; they are dropped when Laravel is removed (R7).
"""

import os

from alembic import context
from sqlalchemy import create_engine, pool

config = context.config

# ORM metadata arrives with the first module models (R0.5/R1); until then migrations are explicit.
target_metadata = None


def _url() -> str:
    url = context.get_x_argument(as_dictionary=True).get("url") or os.environ.get("SOESIS_MIGRATOR_DATABASE_URL")
    if not url:
        raise RuntimeError("Set SOESIS_MIGRATOR_DATABASE_URL (migrator role) to run migrations.")
    return url


def run_migrations_offline() -> None:
    context.configure(url=_url(), target_metadata=target_metadata, literal_binds=True)
    with context.begin_transaction():
        context.run_migrations()


def run_migrations_online() -> None:
    engine = create_engine(_url(), poolclass=pool.NullPool)
    with engine.connect() as connection:
        context.configure(connection=connection, target_metadata=target_metadata)
        with context.begin_transaction():
            context.run_migrations()
    engine.dispose()


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
