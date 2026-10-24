"""Access policy (same rules as Laravel), pure domain."""

import pytest

from soesis.modules.identity.domain import policy
from soesis.modules.identity.domain.model import LoginIdentifier, Role, User
from soesis.shared.kernel import ValidationFailed


def user(role: Role, user_id: int = 1, active: bool = True) -> User:
    return User(id=user_id, name="U", username="u", email="u@example.org", role=role, is_active=active)


def test_every_active_role_uses_the_internal_area():
    assert all(policy.can_use_internal_area(user(role)) for role in Role)
    assert not policy.can_use_internal_area(user(Role.ADMIN, active=False))


def test_only_admins_manage_users():
    assert policy.can_manage_users(user(Role.ADMIN))
    assert not policy.can_manage_users(user(Role.CURATOR))
    assert not policy.can_manage_users(user(Role.UPLOADER))


def test_decisions_are_for_curators_and_admins():
    assert policy.can_decide_on_datasets(user(Role.CURATOR))
    assert policy.can_decide_on_datasets(user(Role.ADMIN))
    assert not policy.can_decide_on_datasets(user(Role.UPLOADER))


def test_uploader_edits_only_own_dataset_metadata():
    uploader = user(Role.UPLOADER, user_id=7)
    assert policy.can_edit_dataset_metadata(uploader, uploaded_by=7)
    assert not policy.can_edit_dataset_metadata(uploader, uploaded_by=8)
    assert not policy.can_edit_dataset_metadata(uploader, uploaded_by=None)  # legacy dataset
    assert policy.can_edit_dataset_metadata(user(Role.CURATOR), uploaded_by=None)


def test_nobody_approves_their_own_upload():
    curator = user(Role.CURATOR, user_id=3)
    assert not policy.can_approve_dataset(curator, uploaded_by=3)
    assert policy.can_approve_dataset(curator, uploaded_by=4)
    assert policy.can_approve_dataset(curator, uploaded_by=None)  # legacy datasets have no uploader
    assert not policy.can_approve_dataset(user(Role.UPLOADER, user_id=5), uploaded_by=None)


def test_admin_cannot_remove_own_admin_access():
    admin = user(Role.ADMIN, user_id=1)

    with pytest.raises(ValidationFailed) as demote:
        policy.ensure_admin_keeps_access(admin, admin, Role.CURATOR, True)
    with pytest.raises(ValidationFailed):
        policy.ensure_admin_keeps_access(admin, admin, Role.ADMIN, False)

    assert demote.value.errors == {"role": ["You cannot remove your own admin access."]}
    # Another admin may be demoted; the actor remains admin.
    policy.ensure_admin_keeps_access(admin, user(Role.ADMIN, user_id=2), Role.CURATOR, True)


def test_login_identifier_is_normalised():
    assert LoginIdentifier.parse("  Rina@Example.ORG ") == LoginIdentifier("email", "rina@example.org")
    assert LoginIdentifier.parse("Rina.Pratiwi") == LoginIdentifier("username", "rina.pratiwi")
