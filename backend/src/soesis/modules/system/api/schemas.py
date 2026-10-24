"""Response DTOs (the public contract, exported to OpenAPI)."""

from typing import Literal

from pydantic import BaseModel

from soesis.modules.system.domain.health import ReadinessReport


class LivenessResponse(BaseModel):
    status: Literal["ok"]


class ComponentResponse(BaseModel):
    status: Literal["ok", "failed"]
    detail: dict[str, str]


class ReadinessResponse(BaseModel):
    status: Literal["ok", "unavailable"]
    components: dict[str, ComponentResponse]

    @classmethod
    def from_report(cls, report: ReadinessReport) -> "ReadinessResponse":
        return cls(
            status="ok" if report.ready else "unavailable",
            components={
                c.name: ComponentResponse(status="ok" if c.healthy else "failed", detail=c.detail)
                for c in report.components
            },
        )
