"""Ports: what the use cases need from the outside world, without saying how."""

from typing import Protocol

from soesis.modules.system.domain.health import ComponentStatus


class ComponentProbe(Protocol):
    """Checks one dependency (database, broker, ...) and reports its status."""

    name: str

    def check(self) -> ComponentStatus: ...
