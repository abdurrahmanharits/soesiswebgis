"""Login, refresh rotation/replay, logout and per-request authentication — no database."""

import pytest

from soesis.modules.identity.application.auth import INVALID_CREDENTIALS
from soesis.modules.identity.domain.model import Role
from soesis.shared.kernel import TooManyRequests, Unauthenticated
from tests.unit.identity.fakes import Identity


@pytest.fixture
def identity() -> Identity:
    return Identity()


def test_login_with_email_or_username_any_case(identity):
    user = identity.add_user("rina.pratiwi", email="rina@example.org")

    for login in ("rina@example.org", "RINA@Example.org", "Rina.Pratiwi"):
        result = identity.login.execute(login, "secret-pass", "10.0.0.1")
        assert result.user.id == user.id
        assert identity.authenticate.execute(result.tokens.access_token).id == user.id


def test_failed_logins_share_one_generic_message(identity):
    identity.add_user("rina")
    identity.add_user("off", is_active=False)

    for login, password in (("rina", "wrong"), ("nobody", "secret-pass"), ("off", "secret-pass")):
        with pytest.raises(Unauthenticated) as failure:
            identity.login.execute(login, password, "10.0.0.1")
        assert failure.value.message == INVALID_CREDENTIALS


def test_five_failures_throttle_that_login_and_ip(identity):
    identity.add_user("rina")
    for _ in range(5):
        with pytest.raises(Unauthenticated):
            identity.login.execute("rina", "wrong", "10.0.0.1")

    with pytest.raises(TooManyRequests) as throttled:
        identity.login.execute("rina", "secret-pass", "10.0.0.1")
    assert "Too many login attempts" in throttled.value.message

    # Another IP is not affected.
    assert identity.login.execute("rina", "secret-pass", "10.0.0.2").user.username == "rina"


def test_success_clears_failure_count(identity):
    identity.add_user("rina")
    with pytest.raises(Unauthenticated):
        identity.login.execute("rina", "wrong", "10.0.0.1")

    identity.login.execute("rina", "secret-pass", "10.0.0.1")

    assert identity.limiter.failures == {}


def test_refresh_rotates_and_a_replayed_token_ends_the_session(identity):
    identity.add_user("rina")
    first = identity.login.execute("rina", "secret-pass", "ip").tokens

    second = identity.refresh.execute(first.refresh_token).tokens
    assert second.refresh_token != first.refresh_token
    assert identity.authenticate.execute(second.access_token)

    identity.clock.advance(seconds=30)  # outside the rotation grace window
    with pytest.raises(Unauthenticated):
        identity.refresh.execute(first.refresh_token)

    # The whole session is gone: the newest tokens stop working too.
    with pytest.raises(Unauthenticated):
        identity.refresh.execute(second.refresh_token)
    with pytest.raises(Unauthenticated):
        identity.authenticate.execute(second.access_token)


def test_concurrent_reuse_within_grace_keeps_the_session(identity):
    identity.add_user("rina")
    first = identity.login.execute("rina", "secret-pass", "ip").tokens
    second = identity.refresh.execute(first.refresh_token).tokens

    with pytest.raises(Unauthenticated):
        identity.refresh.execute(first.refresh_token)  # e.g. a second tab, a moment later

    assert identity.authenticate.execute(second.access_token)


def test_expired_refresh_token_is_rejected(identity):
    identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.clock.advance(days=8)

    with pytest.raises(Unauthenticated):
        identity.refresh.execute(tokens.refresh_token)


def test_access_token_expires_after_fifteen_minutes(identity):
    identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.clock.advance(minutes=16)

    with pytest.raises(Unauthenticated):
        identity.authenticate.execute(tokens.access_token)
    assert identity.refresh.execute(tokens.refresh_token)  # refresh still works


def test_logout_ends_the_session_immediately(identity):
    identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.logout.execute(tokens.refresh_token, tokens.access_token)

    with pytest.raises(Unauthenticated):
        identity.authenticate.execute(tokens.access_token)
    with pytest.raises(Unauthenticated):
        identity.refresh.execute(tokens.refresh_token)


def test_deactivation_applies_on_the_next_request(identity):
    rina = identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.store.users[rina.id] = rina.with_changes(is_active=False)

    with pytest.raises(Unauthenticated):
        identity.authenticate.execute(tokens.access_token)
    with pytest.raises(Unauthenticated):
        identity.refresh.execute(tokens.refresh_token)


def test_role_change_is_seen_on_the_next_request(identity):
    rina = identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.store.users[rina.id] = rina.with_changes(role=Role.CURATOR)

    assert identity.authenticate.execute(tokens.access_token).role is Role.CURATOR


def test_missing_or_garbage_tokens_are_unauthenticated(identity):
    for token in (None, "", "not-a-token"):
        with pytest.raises(Unauthenticated):
            identity.authenticate.execute(token)
    with pytest.raises(Unauthenticated):
        identity.refresh.execute(None)
