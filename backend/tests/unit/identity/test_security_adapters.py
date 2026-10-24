"""bcrypt (Laravel compatibility) and JWT adapters. Pure computation, no services needed."""

from datetime import UTC, datetime, timedelta

import bcrypt
import pytest

from soesis.modules.identity.domain.session import AccessClaims
from soesis.modules.identity.infrastructure.security import BcryptPasswordHasher, JwtAccessTokenCodec
from soesis.shared.kernel import Unauthenticated
from tests.conftest import LARAVEL_HASH, LARAVEL_PASSWORD

SECRET = "unit-test-secret-that-is-long-enough-for-hs256"
NOW = datetime(2026, 10, 24, 9, 0, tzinfo=UTC)


@pytest.fixture(scope="module")
def hasher() -> BcryptPasswordHasher:
    return BcryptPasswordHasher(rounds=4)  # fast; production uses 12 like Laravel


def test_verifies_a_real_laravel_hash():
    assert BcryptPasswordHasher(rounds=4).verify(LARAVEL_PASSWORD, LARAVEL_HASH)
    assert not BcryptPasswordHasher(rounds=4).verify("wrong", LARAVEL_HASH)


def test_writes_the_2y_prefix_laravel_requires(hasher):
    hashed = hasher.hash("Some-Password-1")

    # Laravel's Hash::check throws on "$2b$" (verified against the Laravel container).
    assert hashed.startswith("$2y$04$")
    assert hasher.verify("Some-Password-1", hashed)


def test_default_cost_matches_laravel():
    assert BcryptPasswordHasher().hash("x" * 8).startswith("$2y$12$")


def test_only_the_first_72_bytes_count_like_php(hasher):
    long_password = "a" * 72
    hashed = hasher.hash(long_password + "ignored-by-bcrypt")

    assert hasher.verify(long_password, hashed)
    # Compatible with a plain bcrypt hash of the truncated input.
    assert bcrypt.checkpw(long_password.encode(), hashed.encode())


def test_missing_or_foreign_hash_never_verifies(hasher):
    assert not hasher.verify("anything", None)
    assert not hasher.verify("anything", "not-a-bcrypt-hash")


def test_jwt_round_trip_and_expiry():
    codec = JwtAccessTokenCodec(SECRET)
    token = codec.encode(AccessClaims(5, "session-1", NOW + timedelta(minutes=15)), issued_at=NOW)

    claims = codec.decode(token, NOW + timedelta(minutes=14))
    assert (claims.user_id, claims.session_id) == (5, "session-1")

    with pytest.raises(Unauthenticated):
        codec.decode(token, NOW + timedelta(minutes=16))


def test_jwt_rejects_tampering_and_other_secrets():
    token = JwtAccessTokenCodec(SECRET).encode(AccessClaims(5, "s", NOW + timedelta(minutes=15)), issued_at=NOW)

    with pytest.raises(Unauthenticated):
        JwtAccessTokenCodec("another-secret-that-is-also-long-enough-xx").decode(token, NOW)
    with pytest.raises(Unauthenticated):
        JwtAccessTokenCodec(SECRET).decode(token[:-2] + "xx", NOW)


def test_short_secret_is_refused():
    with pytest.raises(ValueError):
        JwtAccessTokenCodec("too-short")
