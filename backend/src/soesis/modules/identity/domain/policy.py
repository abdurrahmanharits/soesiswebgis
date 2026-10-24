"""Who may do what. Same rules as the Laravel version; reused by later modules (datasets, curation).

Datasets are referenced only by their uploader id here, so this module never
depends on the dataset module.
"""

from soesis.modules.identity.domain.model import Role, User
from soesis.shared.kernel import ValidationFailed

DECIDERS = frozenset({Role.CURATOR, Role.ADMIN})


def can_use_internal_area(user: User) -> bool:
    """Every active account sees the internal area (datasets, preview, map, history) and may upload."""
    return user.is_active


def can_manage_users(user: User) -> bool:
    return user.is_active and user.is_admin


def can_decide_on_datasets(user: User) -> bool:
    """Approve, reject and reopen are for curators and admins."""
    return user.is_active and user.role in DECIDERS


def is_uploader_of(user: User, uploaded_by: int | None) -> bool:
    # Legacy datasets (uploaded before sign-in existed) have no uploader.
    return uploaded_by is not None and user.id is not None and uploaded_by == user.id


def can_edit_dataset_metadata(user: User, uploaded_by: int | None) -> bool:
    """Curators/admins edit any dataset; uploaders only the datasets they uploaded."""
    return can_decide_on_datasets(user) or (user.is_active and is_uploader_of(user, uploaded_by))


def can_approve_dataset(user: User, uploaded_by: int | None) -> bool:
    """Separation of duties: nobody approves their own upload."""
    return can_decide_on_datasets(user) and not is_uploader_of(user, uploaded_by)


def ensure_admin_keeps_access(actor: User, target: User, new_role: Role, new_active: bool) -> None:
    """An admin cannot remove their own admin access (role change or deactivation).

    Because only an active admin can call this, demoting *another* admin always
    leaves at least the actor, so the system can never lose its last admin.
    """
    loses_admin = target.is_admin and target.is_active and (new_role is not Role.ADMIN or not new_active)

    if loses_admin and actor.id == target.id:
        raise ValidationFailed({"role": ["You cannot remove your own admin access."]})
