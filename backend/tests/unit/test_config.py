from pathlib import Path

from soesis.shared.config import Settings


def test_settings_are_read_from_prefixed_environment(monkeypatch):
    monkeypatch.setenv("SOESIS_DATABASE_URL", "postgresql+psycopg://soesis_app:x@db:5432/soesis")
    monkeypatch.setenv("SOESIS_REDIS_URL", "redis://redis:6379/2")
    monkeypatch.setenv("SOESIS_STORAGE_ROOT", "/data/storage")

    settings = Settings(_env_file=None)

    assert settings.database_url.endswith("/soesis")
    assert settings.redis_url == "redis://redis:6379/2"
    assert settings.storage_root == Path("/data/storage")
