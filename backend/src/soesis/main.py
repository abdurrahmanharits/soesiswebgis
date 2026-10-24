"""Composition root: builds adapters, wires them into use cases and mounts module routers.

The only place that knows about every layer. Modules never construct their own
infrastructure; they receive it from here (identity via soesis.composition).
"""

from contextlib import asynccontextmanager

from fastapi import FastAPI
from redis import Redis

from soesis.composition import build_identity_services
from soesis.modules.identity.api.routers import admin_router, auth_router
from soesis.modules.system.api.router import router as system_router
from soesis.modules.system.application.check_readiness import CheckReadiness
from soesis.modules.system.infrastructure.probes import PostgisProbe, RedisProbe
from soesis.shared.config import Settings, get_settings
from soesis.shared.db import build_engine, build_session_factory
from soesis.shared.errors import register_error_handlers
from soesis.shared.http import OriginCheckMiddleware


def create_app(settings: Settings | None = None) -> FastAPI:
    settings = settings or get_settings()

    engine = build_engine(settings.database_url)
    redis = Redis.from_url(settings.redis_url, socket_connect_timeout=2, socket_timeout=2)
    rate_limit_redis = Redis.from_url(settings.rate_limit_redis_url, socket_connect_timeout=2, socket_timeout=2)

    @asynccontextmanager
    async def lifespan(_: FastAPI):
        yield
        engine.dispose()
        redis.close()
        rate_limit_redis.close()

    app = FastAPI(
        title="SoESIS WebGIS API",
        version="0.1.0",
        openapi_url="/api/openapi.json",
        docs_url="/api/docs",
        redoc_url=None,
        lifespan=lifespan,
    )

    app.state.settings = settings
    app.state.engine = engine
    app.state.session_factory = build_session_factory(engine)
    app.state.check_readiness = CheckReadiness([PostgisProbe(engine), RedisProbe(redis)])
    app.state.identity = build_identity_services(settings, engine, rate_limit_redis)

    app.add_middleware(OriginCheckMiddleware, allowed_origins=settings.allowed_origins)
    register_error_handlers(app)

    app.include_router(system_router)
    app.include_router(auth_router)
    app.include_router(admin_router)

    return app


def __getattr__(name: str):
    # `uvicorn soesis.main:app` builds the app lazily, so importing this module
    # (tests, OpenAPI export) never needs a configured environment.
    if name == "app":
        return create_app()
    raise AttributeError(name)
