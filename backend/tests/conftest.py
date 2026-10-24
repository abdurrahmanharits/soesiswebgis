"""Test fixtures. Integration tests run only against the separate soesis_pytest database."""

import os
from collections.abc import Iterator
from urllib.parse import urlparse

import pytest
from alembic import command
from alembic.config import Config
from fastapi.testclient import TestClient
from redis import Redis
from sqlalchemy import Engine, create_engine, text

from soesis.main import create_app
from soesis.shared.config import Settings

BACKEND_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEST_DATABASE = "soesis_pytest"
# Login throttling of the tests lives in its own Redis DB (dev uses 3).
TEST_RATE_LIMIT_REDIS = "redis://redis:6379/4"
ORIGIN = "http://127.0.0.1:3000"

# A real hash produced by Laravel (PHP password_hash, cost 12) for this password.
LARAVEL_PASSWORD = "Laravel-Compat-123"
LARAVEL_HASH = "$2y$12$5t3l.e/4z/aE29g9oPRzDukaKp7E1BmeQxJr8zi73votzX7UcG7ru"


def _required_env(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        pytest.skip(f"{name} is not set (integration tests run inside the backend container)")
    return value


def _guard(url: str) -> str:
    # Never touch the development database: refuse anything but soesis_pytest.
    database = urlparse(url.replace("+psycopg", "")).path.lstrip("/")
    if database != TEST_DATABASE:
        raise RuntimeError(f"Tests must use the {TEST_DATABASE} database, got '{database}'.")
    return url


@pytest.fixture(scope="session")
def test_database_url() -> str:
    return _guard(_required_env("SOESIS_TEST_DATABASE_URL"))


@pytest.fixture(scope="session")
def migrated_database(test_database_url: str) -> str:
    """Brings soesis_pytest to the latest Alembic revision (as the migrator role)."""
    migrator_url = _guard(_required_env("SOESIS_TEST_MIGRATOR_DATABASE_URL"))

    config = Config(os.path.join(BACKEND_ROOT, "alembic.ini"))
    config.cmd_opts = type("Opts", (), {"x": [f"url={migrator_url}"]})()
    command.upgrade(config, "head")

    return test_database_url


@pytest.fixture(scope="session")
def settings(migrated_database: str) -> Settings:
    return Settings(
        env="test",
        database_url=migrated_database,
        redis_url=os.environ.get("SOESIS_REDIS_URL", "redis://redis:6379/2"),
        rate_limit_redis_url=TEST_RATE_LIMIT_REDIS,
        jwt_secret="test-only-secret-that-is-long-enough-for-hs256",
        allowed_origins=[ORIGIN],
    )


@pytest.fixture(scope="session")
def app_engine(migrated_database: str) -> Iterator[Engine]:
    """Connection as the runtime role soesis_app."""
    engine = create_engine(migrated_database)
    yield engine
    engine.dispose()


@pytest.fixture
def clean_identity(app_engine: Engine) -> Iterator[None]:
    """Empty account tables and login throttling before and after a test (soesis_pytest only)."""

    def wipe() -> None:
        with app_engine.begin() as connection:
            for table in ("auth_refresh_tokens", "dataset_curation_events", "datasets", "users", "organizations"):
                connection.execute(text(f"delete from {table}"))
        Redis.from_url(TEST_RATE_LIMIT_REDIS).flushdb()

    wipe()
    yield
    wipe()


@pytest.fixture
def client(settings: Settings) -> Iterator[TestClient]:
    # Browsers send Origin on POST/PATCH/DELETE; the CSRF check requires it with cookies.
    with TestClient(create_app(settings), headers={"Origin": ORIGIN}) as test_client:
        yield test_client
