"""Refresh tokens for JWT sessions (R0.5). Additive: Laravel never reads this table.

One row per issued refresh token; tokens of one login share a session_id.
Only the SHA-256 digest of a token is stored.

Revision ID: 0002_auth_refresh_tokens
Revises: 0001_baseline
Create Date: 2026-10-24
"""

from collections.abc import Sequence

from alembic import op

revision: str = "0002_auth_refresh_tokens"
down_revision: str | None = "0001_baseline"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    op.execute("""
        CREATE TABLE public.auth_refresh_tokens (
            id uuid PRIMARY KEY,
            user_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
            session_id uuid NOT NULL,
            token_hash character(64) NOT NULL UNIQUE,
            created_at timestamp with time zone NOT NULL,
            expires_at timestamp with time zone NOT NULL,
            used_at timestamp with time zone,
            revoked_at timestamp with time zone
        )
    """)
    op.execute("CREATE INDEX auth_refresh_tokens_session_id_index ON public.auth_refresh_tokens (session_id)")
    op.execute("CREATE INDEX auth_refresh_tokens_user_id_index ON public.auth_refresh_tokens (user_id)")


def downgrade() -> None:
    op.execute("DROP TABLE IF EXISTS public.auth_refresh_tokens")
