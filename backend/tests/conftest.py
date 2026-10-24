"""Test fixtures. Integration tests run only against the separate soesis_pytest database."""

import os
from collections.abc import Iterator
from urllib.parse import urlparse

import pytest
from alembic import command
from alembic.config import Config
from fastapi.testclient import TestClient
from sqlalchemy import Engine, create_engine

from soesis.main import create_app
from soesis.shared.config import Settings

BACKEND_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEST_DATABASE = "soesis_pytest"


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
    )


@pytest.fixture(scope="session")
def app_engine(migrated_database: str) -> Iterator[Engine]:
    """Connection as the runtime role soesis_app."""
    engine = create_engine(migrated_database)
    yield engine
    engine.dispose()


@pytest.fixture
def client(settings: Settings) -> Iterator[TestClient]:
    with TestClient(create_app(settings)) as test_client:
        yield test_client
