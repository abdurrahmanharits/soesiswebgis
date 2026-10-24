"""Baseline: the application schema as created by the Laravel migrations (up to 2026_10_24_120300).

On the existing development database this revision is only *stamped*
(`alembic stamp 0001_baseline`) because the tables already exist; on a fresh
database (e.g. soesis_pytest) it creates them. The DDL mirrors
`pg_dump --schema-only` of the live tables, so both stay identical.

Revision ID: 0001_baseline
Revises:
Create Date: 2026-10-24
"""

from collections.abc import Sequence

from alembic import op

revision: str = "0001_baseline"
down_revision: str | None = None
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None

STATEMENTS = [
    # PostGIS itself is installed by scripts/db/bootstrap_roles.sql (needs a superuser).
    "CREATE SCHEMA IF NOT EXISTS ingest",
    """
    CREATE TABLE public.organizations (
        id bigserial PRIMARY KEY,
        name character varying(255) NOT NULL CONSTRAINT organizations_name_unique UNIQUE,
        created_at timestamp(0) without time zone,
        updated_at timestamp(0) without time zone
    )
    """,
    """
    CREATE TABLE public.users (
        id bigserial PRIMARY KEY,
        name character varying(255) NOT NULL,
        email character varying(255) NOT NULL CONSTRAINT users_email_unique UNIQUE,
        email_verified_at timestamp(0) without time zone,
        password character varying(255) NOT NULL,
        remember_token character varying(100),
        created_at timestamp(0) without time zone,
        updated_at timestamp(0) without time zone,
        username character varying(30) NOT NULL CONSTRAINT users_username_unique UNIQUE,
        role character varying(255) DEFAULT 'uploader' NOT NULL,
        is_active boolean DEFAULT true NOT NULL,
        organization_id bigint CONSTRAINT users_organization_id_foreign
            REFERENCES public.organizations(id) ON DELETE SET NULL
    )
    """,
    "CREATE INDEX users_role_index ON public.users USING btree (role)",
    """
    CREATE TABLE public.datasets (
        id uuid PRIMARY KEY,
        title character varying(255),
        original_filename character varying(255) NOT NULL,
        format character varying(255) NOT NULL,
        status character varying(255) DEFAULT 'draft' NOT NULL,
        original_path text,
        processed_path text,
        postgis_schema character varying(255),
        postgis_table character varying(255),
        crs character varying(255),
        srid integer,
        geometry_type character varying(255),
        feature_count bigint,
        bbox jsonb,
        attribute_schema jsonb,
        raster_width integer,
        raster_height integer,
        band_count integer,
        pixel_size_x double precision,
        pixel_size_y double precision,
        nodata jsonb,
        metadata jsonb,
        error_message text,
        created_at timestamp(0) without time zone,
        updated_at timestamp(0) without time zone,
        curation_status character varying(255) DEFAULT 'pending' NOT NULL,
        description text,
        keywords jsonb,
        topic_category character varying(255),
        source text,
        source_organization character varying(255),
        license character varying(255),
        license_other character varying(255),
        usage_constraints text,
        lineage text,
        temporal_start date,
        temporal_end date,
        geographic_description character varying(255),
        contact_name character varying(255),
        contact_email character varying(255),
        uploaded_by bigint CONSTRAINT datasets_uploaded_by_foreign
            REFERENCES public.users(id) ON DELETE SET NULL,
        organization_id bigint CONSTRAINT datasets_organization_id_foreign
            REFERENCES public.organizations(id) ON DELETE SET NULL
    )
    """,
    "CREATE INDEX datasets_curation_status_index ON public.datasets USING btree (curation_status)",
    "CREATE INDEX datasets_format_index ON public.datasets USING btree (format)",
    "CREATE INDEX datasets_status_index ON public.datasets USING btree (status)",
    """
    CREATE TABLE public.dataset_curation_events (
        id bigserial PRIMARY KEY,
        dataset_id uuid NOT NULL CONSTRAINT dataset_curation_events_dataset_id_foreign
            REFERENCES public.datasets(id) ON DELETE CASCADE,
        action character varying(255) NOT NULL,
        reviewer_name character varying(255) NOT NULL,
        note text,
        from_status character varying(255),
        to_status character varying(255),
        changes jsonb,
        created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
        user_id bigint CONSTRAINT dataset_curation_events_user_id_foreign
            REFERENCES public.users(id) ON DELETE SET NULL
    )
    """,
    """
    CREATE INDEX dataset_curation_events_dataset_id_created_at_index
        ON public.dataset_curation_events USING btree (dataset_id, created_at)
    """,
]


def upgrade() -> None:
    for statement in STATEMENTS:
        op.execute(statement)


def downgrade() -> None:
    # Never against the shared development database: it would drop Laravel's live data.
    database = op.get_bind().exec_driver_sql("select current_database()").scalar()
    if database == "soesis":
        raise RuntimeError("Refusing to downgrade the baseline on the shared development database 'soesis'.")

    for table in ("dataset_curation_events", "datasets", "users", "organizations"):
        op.execute(f"DROP TABLE IF EXISTS public.{table}")
