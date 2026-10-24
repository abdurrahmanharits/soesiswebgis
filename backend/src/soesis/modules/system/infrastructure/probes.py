"""Adapters for the ComponentProbe port: PostgreSQL/PostGIS and Redis."""

from redis import Redis
from sqlalchemy import Engine, text

from soesis.modules.system.domain.health import ComponentStatus


class PostgisProbe:
    """Database reachable and PostGIS installed (the backend is useless without it)."""

    name = "database"

    def __init__(self, engine: Engine) -> None:
        self._engine = engine

    def check(self) -> ComponentStatus:
        with self._engine.connect() as connection:
            row = connection.execute(
                text("select current_database() as database, postgis_lib_version() as postgis")
            ).one()

        return ComponentStatus.ok(self.name, database=row.database, postgis=row.postgis)


class RedisProbe:
    """Celery broker reachable."""

    name = "redis"

    def __init__(self, client: Redis) -> None:
        self._client = client

    def check(self) -> ComponentStatus:
        self._client.ping()
        return ComponentStatus.ok(self.name)
