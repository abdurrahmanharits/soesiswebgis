"""HTTP behaviour of /api/health against the real soesis_pytest database and Redis."""

import pytest
from fastapi.testclient import TestClient

from soesis.main import create_app

pytestmark = pytest.mark.integration


def test_liveness(client):
    response = client.get("/api/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_readiness_reports_postgis_and_redis(client):
    response = client.get("/api/health/ready")
    body = response.json()

    assert response.status_code == 200, body
    assert body["status"] == "ok"
    assert body["components"]["database"]["detail"]["database"] == "soesis_pytest"
    assert body["components"]["database"]["detail"]["postgis"].startswith("3.4")
    assert body["components"]["redis"]["status"] == "ok"


def test_readiness_is_503_when_redis_is_unreachable(settings):
    broken = settings.model_copy(update={"redis_url": "redis://127.0.0.1:1/0"})

    with TestClient(create_app(broken)) as client:
        response = client.get("/api/health/ready")

    assert response.status_code == 503
    assert response.json()["status"] == "unavailable"
    assert response.json()["components"]["redis"]["status"] == "failed"
    assert response.json()["components"]["database"]["status"] == "ok"


def test_unknown_route_uses_uniform_error_shape(client):
    response = client.get("/api/does-not-exist")

    assert response.status_code == 404
    assert response.json() == {"message": "Not Found"}
