"""Typed settings from environment variables (prefix SOESIS_), see backend/.env.example."""

from functools import lru_cache
from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="SOESIS_", env_file=".env", extra="ignore")

    env: str = "development"

    # Runtime role (data access only); the migrator role is used by Alembic alone.
    database_url: str = Field(description="SQLAlchemy URL for the soesis_app role")
    migrator_database_url: str | None = None

    redis_url: str = "redis://redis:6379/2"
    storage_root: Path = Path("/data/storage")


@lru_cache
def get_settings() -> Settings:
    return Settings()
