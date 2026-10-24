"""Request/response DTOs of the identity API (exported to contracts/openapi.json)."""

from datetime import datetime
from typing import Literal

from pydantic import BaseModel, Field

from soesis.modules.identity.domain.model import Organization, User

RoleName = Literal["uploader", "curator", "admin"]


class OrganizationResponse(BaseModel):
    id: int
    name: str

    @classmethod
    def of(cls, organization: Organization) -> "OrganizationResponse":
        assert organization.id is not None
        return cls(id=organization.id, name=organization.name)


class OrganizationWithCountResponse(OrganizationResponse):
    users_count: int


class UserResponse(BaseModel):
    """Never contains the password hash, remember token or any token."""

    id: int
    name: str
    username: str
    email: str
    role: RoleName
    role_label: str
    is_active: bool
    organization: OrganizationResponse | None

    @classmethod
    def of(cls, user: User) -> "UserResponse":
        assert user.id is not None
        return cls(
            id=user.id,
            name=user.name,
            username=user.username,
            email=user.email,
            role=user.role.value,
            role_label=user.role.label,
            is_active=user.is_active,
            organization=OrganizationResponse.of(user.organization) if user.organization else None,
        )


class LoginRequest(BaseModel):
    login: str = Field(min_length=1, max_length=255, description="E-mail or username (case-insensitive)")
    password: str = Field(min_length=1, max_length=1024)


class SessionResponse(BaseModel):
    user: UserResponse
    access_expires_at: datetime


class MessageResponse(BaseModel):
    message: str


class UserWriteRequest(BaseModel):
    name: str = Field(max_length=100)
    username: str = Field(max_length=30)
    email: str = Field(max_length=255)
    role: RoleName
    organization_id: int | None = None
    is_active: bool = True


class UserCreateRequest(UserWriteRequest):
    password: str = Field(max_length=1024)


class PasswordResetRequest(BaseModel):
    password: str = Field(max_length=1024)


class OrganizationWriteRequest(BaseModel):
    name: str = Field(max_length=255)
