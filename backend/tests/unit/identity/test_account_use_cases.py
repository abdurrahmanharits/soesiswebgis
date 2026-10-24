"""User/organization administration, initial admin and password reset — no database."""

import pytest

from soesis.modules.identity.application.accounts import UserInput
from soesis.modules.identity.domain.model import Role
from soesis.shared.kernel import ApplicationError, Forbidden, NotFound, Unauthenticated, ValidationFailed
from tests.unit.identity.fakes import Identity


@pytest.fixture
def identity() -> Identity:
    return Identity()


def data(**overrides) -> UserInput:
    return UserInput(
        **{"name": "Rina", "username": "Rina.P", "email": "Rina@Example.org", "role": Role.CURATOR, **overrides}
    )


def test_admin_creates_user_with_normalised_identifiers(identity):
    admin = identity.add_user("admin", Role.ADMIN)

    user = identity.users.create(admin, data(), "long-enough")

    assert (user.username, user.email, user.role) == ("rina.p", "rina@example.org", Role.CURATOR)
    assert user.password_hash == "hashed:long-enough"


def test_validation_messages(identity):
    admin = identity.add_user("admin", Role.ADMIN, email="taken@example.org")

    with pytest.raises(ValidationFailed) as invalid:
        identity.users.create(
            admin, data(username="Bad Name!", email="TAKEN@example.org", organization_id=999), "short"
        )
    assert set(invalid.value.errors) == {"password"}

    with pytest.raises(ValidationFailed) as invalid:
        identity.users.create(
            admin, data(username="Bad Name!", email="TAKEN@example.org", organization_id=999), "long-enough"
        )
    assert invalid.value.errors == {
        "username": ["Use 3–30 lowercase letters, digits, dots or underscores."],
        "email": ["The email has already been taken."],
        "organization_id": ["The selected organization is invalid."],
    }


def test_non_admins_cannot_manage_accounts(identity):
    curator = identity.add_user("cur", Role.CURATOR)

    for call in (
        lambda: identity.users.list(curator),
        lambda: identity.users.create(curator, data(), "long-enough"),
        lambda: identity.organizations.create(curator, "WWF"),
    ):
        with pytest.raises(Forbidden):
            call()


def test_admin_cannot_demote_self(identity):
    admin = identity.add_user("admin", Role.ADMIN)

    with pytest.raises(ValidationFailed):
        identity.users.update(
            admin, admin.id, data(name="Admin", username="admin", email="admin@example.org", role=Role.CURATOR)
        )
    assert identity.store.users[admin.id].role is Role.ADMIN


def test_deactivation_ends_the_users_sessions(identity):
    admin = identity.add_user("admin", Role.ADMIN)
    rina = identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.users.update(
        admin,
        rina.id,
        data(name="Rina", username="rina", email="rina@example.org", role=Role.UPLOADER, is_active=False),
    )

    with pytest.raises(Unauthenticated):
        identity.refresh.execute(tokens.refresh_token)


def test_password_reset_signs_the_user_out(identity):
    admin = identity.add_user("admin", Role.ADMIN)
    rina = identity.add_user("rina")
    tokens = identity.login.execute("rina", "secret-pass", "ip").tokens

    identity.users.reset_password(admin, rina.id, "brand-new-pass")

    with pytest.raises(Unauthenticated):
        identity.authenticate.execute(tokens.access_token)
    assert identity.login.execute("rina", "brand-new-pass", "ip")


def test_unknown_user_is_not_found(identity):
    admin = identity.add_user("admin", Role.ADMIN)
    with pytest.raises(NotFound):
        identity.users.reset_password(admin, 999, "long-enough")


def test_organizations_are_unique_case_insensitively(identity):
    admin = identity.add_user("admin", Role.ADMIN)
    wwf = identity.organizations.create(admin, " WWF-Indonesia ")

    assert wwf.name == "WWF-Indonesia"
    with pytest.raises(ValidationFailed):
        identity.organizations.create(admin, "wwf-indonesia")
    assert identity.organizations.rename(admin, wwf.id, "WWF Indonesia").name == "WWF Indonesia"


def test_initial_admin_only_once(identity):
    admin = identity.bootstrap.create_initial_admin("Admin", "Admin", "Admin@Example.org", "long-enough")
    assert admin.role is Role.ADMIN and admin.username == "admin"

    with pytest.raises(ApplicationError, match="already exists"):
        identity.bootstrap.create_initial_admin("Second", "second", "second@example.org", "long-enough")


def test_cli_password_reset_by_identifier(identity):
    identity.add_user("rina")

    identity.bootstrap.reset_password("RINA@example.org", "brand-new-pass")

    assert identity.login.execute("rina", "brand-new-pass", "ip")
    with pytest.raises(NotFound):
        identity.bootstrap.reset_password("nobody", "brand-new-pass")
