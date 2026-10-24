"""Demo account seeding — no database."""

import pytest

from soesis.modules.identity.application.demo_seed import DEMO_ACCOUNTS, DEMO_ORGANIZATION
from soesis.modules.identity.domain.model import Role
from soesis.shared.kernel import ApplicationError, Unauthenticated, ValidationFailed
from tests.unit.identity.fakes import Identity


@pytest.fixture
def identity() -> Identity:
    return Identity()


def test_seed_creates_one_account_per_role_plus_edge_cases(identity):
    report = identity.demo.seed("demo-pass-123", environment="development")

    assert report.created == tuple(a.username for a in DEMO_ACCOUNTS)
    users = {u.username: u for u in identity.store.users.values()}
    assert {users[n].role for n in ("demo.uploader", "demo.curator", "demo.admin")} == set(Role)
    assert users["demo.curator2"].role is Role.CURATOR
    assert users["demo.inactive"].is_active is False
    assert all(u.organization and u.organization.name == DEMO_ORGANIZATION for u in users.values())
    assert all(u.email.endswith("@example.invalid") for u in users.values())


def test_seeded_accounts_sign_in_except_the_inactive_one(identity):
    identity.demo.seed("demo-pass-123", environment="development")

    assert identity.login.execute("demo.curator", "demo-pass-123", "ip").user.role is Role.CURATOR
    with pytest.raises(Unauthenticated):
        identity.login.execute("demo.inactive", "demo-pass-123", "ip")


def test_seed_is_idempotent_and_can_reset_passwords(identity):
    identity.demo.seed("demo-pass-123", environment="development")

    again = identity.demo.seed("other-pass-456", environment="development")
    assert again.created == () and len(again.skipped) == len(DEMO_ACCOUNTS)
    assert len(identity.store.organizations) == 1
    with pytest.raises(Unauthenticated):
        identity.login.execute("demo.admin", "other-pass-456", "ip")

    reset = identity.demo.seed("other-pass-456", environment="development", reset_passwords=True)
    assert len(reset.updated) == len(DEMO_ACCOUNTS)
    assert identity.login.execute("demo.admin", "other-pass-456", "ip")


def test_refused_in_production_and_with_a_short_password(identity):
    with pytest.raises(ApplicationError, match="production"):
        identity.demo.seed("demo-pass-123", environment="production")
    with pytest.raises(ValidationFailed):
        identity.demo.seed("short", environment="development")
    assert identity.store.users == {}


def test_remove_deletes_only_demo_accounts(identity):
    identity.add_user("admin", Role.ADMIN)
    identity.demo.seed("demo-pass-123", environment="development")

    removed = identity.demo.remove(environment="development")

    assert removed == tuple(sorted(a.username for a in DEMO_ACCOUNTS))
    assert [u.username for u in identity.store.users.values()] == ["admin"]
