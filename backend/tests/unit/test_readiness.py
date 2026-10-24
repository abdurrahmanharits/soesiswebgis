"""Domain + use case of the system module, with fake probes: no database, no Redis."""

from soesis.modules.system.application.check_readiness import CheckReadiness
from soesis.modules.system.domain.health import ComponentStatus, ReadinessReport


class FakeProbe:
    def __init__(self, name: str, status: ComponentStatus | None = None, error: Exception | None = None) -> None:
        self.name = name
        self._status = status
        self._error = error

    def check(self) -> ComponentStatus:
        if self._error:
            raise self._error
        return self._status


def test_ready_when_every_component_is_healthy():
    report = CheckReadiness(
        [
            FakeProbe("database", ComponentStatus.ok("database", postgis="3.4.3")),
            FakeProbe("redis", ComponentStatus.ok("redis")),
        ]
    ).execute()

    assert report.ready
    assert [c.name for c in report.components] == ["database", "redis"]


def test_not_ready_when_one_component_fails():
    report = CheckReadiness(
        [
            FakeProbe("database", ComponentStatus.ok("database")),
            FakeProbe("redis", ComponentStatus.failed("redis", "connection refused")),
        ]
    ).execute()

    assert not report.ready


def test_a_raising_probe_becomes_a_failed_component():
    report = CheckReadiness([FakeProbe("database", error=ConnectionError("db down"))]).execute()

    assert not report.ready
    assert report.components[0].detail == {"error": "ConnectionError: db down"}


def test_nothing_checked_is_not_ready():
    assert not ReadinessReport(components=()).ready
