"""The identity use cases as one bundle, assembled by the composition root (soesis.composition)."""

from dataclasses import dataclass

from soesis.modules.identity.application.accounts import (
    AccountBootstrap,
    OrganizationAdministration,
    UserAdministration,
)
from soesis.modules.identity.application.auth import Authenticate, Login, Logout, RefreshSession, TokenLifetimes
from soesis.modules.identity.application.demo_seed import DemoAccounts


@dataclass(frozen=True, slots=True)
class IdentityServices:
    login: Login
    refresh: RefreshSession
    logout: Logout
    authenticate: Authenticate
    users: UserAdministration
    organizations: OrganizationAdministration
    bootstrap: AccountBootstrap
    demo: DemoAccounts
    lifetimes: TokenLifetimes
