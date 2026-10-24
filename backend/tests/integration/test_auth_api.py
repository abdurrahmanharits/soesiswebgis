"""/api/auth and /api/admin over HTTP, against soesis_pytest + Redis (real adapters)."""

from datetime import UTC, datetime

import pytest
from sqlalchemy import text

from tests.conftest import LARAVEL_HASH, LARAVEL_PASSWORD, ORIGIN

pytestmark = [pytest.mark.integration, pytest.mark.usefixtures("clean_identity")]


def add_user(engine, username, role="uploader", password_hash=LARAVEL_HASH, active=True, name=None) -> int:
    """Inserted exactly like Laravel stores accounts (lowercase, $2y$12$ hash, naive timestamps)."""
    now = datetime.now(UTC).replace(tzinfo=None)
    with engine.begin() as connection:
        return connection.execute(
            text(
                "insert into users (name, username, email, password, role, is_active, created_at, updated_at) "
                "values (:name, :username, :email, :password, :role, :active, :now, :now) returning id"
            ),
            {
                "name": name or username.title(),
                "username": username,
                "email": f"{username}@example.org",
                "password": password_hash,
                "role": role,
                "active": active,
                "now": now,
            },
        ).scalar_one()


def login(client, login_name, password=LARAVEL_PASSWORD):
    return client.post("/api/auth/login", json={"login": login_name, "password": password})


# ---- Login -------------------------------------------------------------------


def test_laravel_account_signs_in_with_email_or_username(client, app_engine):
    add_user(app_engine, "rina.pratiwi", role="curator", name="Rina Pratiwi")

    for name in ("rina.pratiwi@example.org", "RINA.PRATIWI", "Rina.Pratiwi@Example.org"):
        response = login(client, name)
        assert response.status_code == 200, response.text
        body = response.json()
        assert body["user"]["username"] == "rina.pratiwi"
        assert body["user"]["role"] == "curator"
        assert "password" not in body["user"]


def test_cookies_are_httponly_lax_and_scoped(client, app_engine):
    add_user(app_engine, "rina")

    response = login(client, "rina")
    cookies = {c.split("=", 1)[0]: c for c in response.headers.get_list("set-cookie")}

    assert set(cookies) == {"soesis_access", "soesis_refresh", "soesis_session"}
    for name in ("soesis_access", "soesis_refresh"):
        assert "HttpOnly" in cookies[name] and "SameSite=lax" in cookies[name]
    assert "Path=/api/auth" in cookies["soesis_refresh"]
    assert "HttpOnly" not in cookies["soesis_session"]
    assert "Max-Age=900" in cookies["soesis_access"]


def test_failures_are_generic_401(client, app_engine):
    add_user(app_engine, "rina")
    add_user(app_engine, "off", active=False)

    for name, password in (("rina", "wrong"), ("nobody", LARAVEL_PASSWORD), ("off", LARAVEL_PASSWORD)):
        response = login(client, name, password)
        assert response.status_code == 401
        assert response.json() == {"message": "The provided credentials are invalid."}


def test_login_is_throttled_after_five_failures(client, app_engine):
    add_user(app_engine, "rina")
    for _ in range(5):
        assert login(client, "rina", "wrong").status_code == 401

    response = login(client, "rina")

    assert response.status_code == 429
    assert "Too many login attempts" in response.json()["message"]
    assert int(response.headers["retry-after"]) > 0


def test_validation_errors_use_the_uniform_shape(client):
    response = client.post("/api/auth/login", json={"login": ""})

    assert response.status_code == 422
    assert set(response.json()) == {"message", "errors"}


# ---- Session -----------------------------------------------------------------


def test_me_requires_a_session(client, app_engine):
    assert client.get("/api/auth/me").json() == {"message": "Unauthenticated."}
    assert client.get("/api/auth/me").status_code == 401

    add_user(app_engine, "rina")
    login(client, "rina")

    me = client.get("/api/auth/me")
    assert me.status_code == 200 and me.json()["username"] == "rina"


def test_refresh_rotates_and_replay_ends_the_session(client, app_engine):
    add_user(app_engine, "rina")
    login(client, "rina")
    old_refresh = client.cookies.get("soesis_refresh")

    assert client.post("/api/auth/refresh").status_code == 200
    assert client.cookies.get("soesis_refresh") != old_refresh

    # Pretend the rotation happened a minute ago, then replay the old (stolen) token.
    with app_engine.begin() as connection:
        connection.execute(
            text("update auth_refresh_tokens set used_at = used_at - interval '1 minute' where used_at is not null")
        )
    current = client.cookies.get("soesis_refresh")
    client.cookies.set("soesis_refresh", old_refresh, path="/api/auth")
    assert client.post("/api/auth/refresh").status_code == 401

    # The legitimate (newest) tokens are dead as well.
    client.cookies.set("soesis_refresh", current, path="/api/auth")
    assert client.post("/api/auth/refresh").status_code == 401
    assert client.get("/api/auth/me").status_code == 401


def test_logout_ends_access_and_clears_cookies(client, app_engine):
    add_user(app_engine, "rina")
    login(client, "rina")
    access = client.cookies.get("soesis_access")

    response = client.post("/api/auth/logout")

    assert response.status_code == 204
    assert all("Max-Age=0" in c or "expires=" in c.lower() for c in response.headers.get_list("set-cookie"))
    client.cookies.set("soesis_access", access)  # even a copied access token no longer works
    assert client.get("/api/auth/me").status_code == 401


def test_deactivated_user_loses_access_on_next_request(client, app_engine):
    user_id = add_user(app_engine, "rina")
    login(client, "rina")

    with app_engine.begin() as connection:
        connection.execute(text("update users set is_active = false where id = :id"), {"id": user_id})

    assert client.get("/api/auth/me").status_code == 401


def test_cross_site_writes_are_rejected(client, app_engine):
    add_user(app_engine, "rina")
    login(client, "rina")

    evil = client.post("/api/auth/logout", headers={"Origin": "https://evil.example"})
    assert evil.status_code == 403
    assert evil.json() == {"message": "Cross-site request rejected."}

    # No Origin/Referer but a session cookie: rejected too.
    client.headers.pop("Origin")
    assert client.post("/api/auth/logout").status_code == 403
    client.headers["Origin"] = ORIGIN
    assert client.get("/api/auth/me").status_code == 200  # reads are not affected


# ---- Admin -------------------------------------------------------------------


def sign_in_admin(client, app_engine) -> int:
    admin_id = add_user(app_engine, "admin", role="admin")
    login(client, "admin")
    return admin_id


def test_admin_creates_and_lists_users_with_laravel_compatible_hash(client, app_engine):
    sign_in_admin(client, app_engine)
    org = client.post("/api/admin/organizations", json={"name": "WWF-Indonesia"}).json()

    created = client.post(
        "/api/admin/users",
        json={
            "name": "Budi",
            "username": "Budi.S",
            "email": "Budi@Example.org",
            "role": "curator",
            "organization_id": org["id"],
            "password": "long-enough-1",
        },
    )

    assert created.status_code == 201, created.text
    assert created.json()["organization"] == {"id": org["id"], "name": "WWF-Indonesia"}
    with app_engine.connect() as connection:
        stored = connection.execute(text("select username, email, password from users where username = 'budi.s'")).one()
    assert stored.email == "budi@example.org"
    assert stored.password.startswith("$2y$12$")
    assert [u["username"] for u in client.get("/api/admin/users").json()] == ["admin", "budi.s"]
    assert client.get("/api/admin/organizations").json()[0]["users_count"] == 1


def test_admin_validation_and_self_protection(client, app_engine):
    admin_id = sign_in_admin(client, app_engine)

    duplicate = client.post(
        "/api/admin/users",
        json={
            "name": "X",
            "username": "ADMIN",
            "email": "x@example.org",
            "role": "uploader",
            "password": "long-enough-1",
        },
    )
    assert duplicate.status_code == 422
    assert duplicate.json()["errors"]["username"] == ["The username has already been taken."]

    demote = client.patch(
        f"/api/admin/users/{admin_id}",
        json={
            "name": "Admin",
            "username": "admin",
            "email": "admin@example.org",
            "role": "curator",
            "is_active": True,
        },
    )
    assert demote.status_code == 422
    assert demote.json()["errors"]["role"] == ["You cannot remove your own admin access."]


def test_password_reset_signs_the_user_out(client, app_engine, settings):
    from fastapi.testclient import TestClient

    from soesis.main import create_app

    user_id = add_user(app_engine, "rina")
    with TestClient(create_app(settings), headers={"Origin": ORIGIN}) as rina:
        login(rina, "rina")
        sign_in_admin(client, app_engine)

        assert (
            client.post(f"/api/admin/users/{user_id}/reset-password", json={"password": "brand-new-1"}).status_code
            == 204
        )
        assert rina.get("/api/auth/me").status_code == 401
        assert login(rina, "rina", "brand-new-1").status_code == 200


def test_non_admins_get_403(client, app_engine):
    add_user(app_engine, "cur", role="curator")
    login(client, "cur")

    for method, url in (
        ("GET", "/api/admin/users"),
        ("POST", "/api/admin/organizations"),
        ("GET", "/api/admin/organizations"),
    ):
        response = client.request(method, url, json={"name": "X"})
        assert response.status_code == 403
        assert response.json() == {"message": "Forbidden."}


def test_admin_endpoints_need_a_session(client):
    assert client.get("/api/admin/users").status_code == 401


def test_health_stays_public(client):
    assert client.get("/api/health").status_code == 200
