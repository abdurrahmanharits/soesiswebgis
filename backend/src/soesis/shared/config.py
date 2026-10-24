"""Typed settings from environment variables (prefix SOESIS_), see backend/.env.example."""

from functools import lru_cache
from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    # .env.local (not committed) overrides .env, e.g. 127.0.0.1 hosts when running with `uv` outside Docker.
    model_config = SettingsConfigDict(env_prefix="SOESIS_", env_file=(".env", ".env.local"), extra="ignore")

    env: str = "development"

    # Runtime role (data access only); the migrator role is used by Alembic alone.
    database_url: str = Field(description="SQLAlchemy URL for the soesis_app role")
    migrator_database_url: str | None = None

    redis_url: str = "redis://redis:6379/2"
    storage_root: Path = Path("/data/storage")

    # ---- Authentication (R0.5) ----
    jwt_secret: str = Field(default="", description="HS256 signing secret, at least 32 characters")
    access_token_minutes: int = 15
    refresh_token_days: int = 7
    # Secure cookies need HTTPS; off for local http://127.0.0.1 development.
    cookie_secure: bool = False
    # Login throttling lives in its own Redis DB (the broker uses 2).
    rate_limit_redis_url: str = "redis://redis:6379/3"
    # Browser origins allowed to send state-changing requests (CSRF protection, see shared.http).
    allowed_origins: list[str] = ["http://127.0.0.1:3000", "http://localhost:3000"]


@lru_cache
def get_settings() -> Settings:
    return Settings()
