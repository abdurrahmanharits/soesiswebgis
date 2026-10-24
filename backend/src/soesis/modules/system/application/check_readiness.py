"""Use case: is the backend ready to serve requests?"""

from collections.abc import Sequence

from soesis.modules.system.application.ports import ComponentProbe
from soesis.modules.system.domain.health import ComponentStatus, ReadinessReport


class CheckReadiness:
    def __init__(self, probes: Sequence[ComponentProbe]) -> None:
        self._probes = tuple(probes)

    def execute(self) -> ReadinessReport:
        return ReadinessReport(components=tuple(self._run(probe) for probe in self._probes))

    @staticmethod
    def _run(probe: ComponentProbe) -> ComponentStatus:
        # A probe that raises is a failed component, never a crashed readiness check.
        try:
            return probe.check()
        except Exception as error:  # noqa: BLE001 - reported, not swallowed silently
            return ComponentStatus.failed(probe.name, f"{type(error).__name__}: {error}")
