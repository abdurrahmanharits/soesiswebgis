"""Readiness of the backend as a set of component checks. Pure Python."""

from dataclasses import dataclass, field


@dataclass(frozen=True, slots=True)
class ComponentStatus:
    name: str
    healthy: bool
    detail: dict[str, str] = field(default_factory=dict)

    @classmethod
    def ok(cls, name: str, **detail: str) -> "ComponentStatus":
        return cls(name=name, healthy=True, detail=detail)

    @classmethod
    def failed(cls, name: str, reason: str) -> "ComponentStatus":
        return cls(name=name, healthy=False, detail={"error": reason})


@dataclass(frozen=True, slots=True)
class ReadinessReport:
    components: tuple[ComponentStatus, ...]

    @property
    def ready(self) -> bool:
        """Ready only when every required component is healthy (and at least one was checked)."""
        return bool(self.components) and all(component.healthy for component in self.components)
