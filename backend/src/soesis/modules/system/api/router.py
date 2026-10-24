"""GET /api/health (liveness) and GET /api/health/ready (dependencies)."""

from typing import Annotated

from fastapi import APIRouter, Depends, Request, Response, status

from soesis.modules.system.api.schemas import LivenessResponse, ReadinessResponse
from soesis.modules.system.application.check_readiness import CheckReadiness

router = APIRouter(prefix="/api/health", tags=["system"])


def get_check_readiness(request: Request) -> CheckReadiness:
    # Built once in the composition root (soesis.main) and stored on app.state.
    return request.app.state.check_readiness


@router.get("", response_model=LivenessResponse, summary="Process is alive")
def liveness() -> LivenessResponse:
    return LivenessResponse(status="ok")


@router.get(
    "/ready",
    response_model=ReadinessResponse,
    summary="Database (PostGIS) and Redis are reachable",
    responses={503: {"model": ReadinessResponse, "description": "A dependency is unavailable"}},
)
def readiness(
    response: Response,
    check_readiness: Annotated[CheckReadiness, Depends(get_check_readiness)],
) -> ReadinessResponse:
    report = check_readiness.execute()
    if not report.ready:
        response.status_code = status.HTTP_503_SERVICE_UNAVAILABLE
    return ReadinessResponse.from_report(report)
