"""Baseline schema, PostGIS capability and the privileges of the runtime role soesis_app."""

import pytest
from sqlalchemy import text
from sqlalchemy.exc import ProgrammingError

pytestmark = pytest.mark.integration


def test_baseline_revision_is_applied(app_engine):
    with app_engine.connect() as connection:
        assert connection.execute(text("select version_num from alembic_version")).scalar() == "0001_baseline"


def test_baseline_creates_the_application_tables(app_engine):
    with app_engine.connect() as connection:
        columns = dict(connection.execute(text(
            "select table_name, count(*) from information_schema.columns where table_schema = 'public' "
            "and table_name in ('datasets', 'dataset_curation_events', 'users', 'organizations') "
            "group by table_name"
        )).all())

    # Same column counts as the Laravel schema (pg_dump of the development database).
    assert columns == {"datasets": 42, "dataset_curation_events": 10, "users": 12, "organizations": 4}


def test_postgis_reprojects_and_serialises_geojson(app_engine):
    with app_engine.connect() as connection:
        row = connection.execute(text(
            "select postgis_lib_version() as version, "
            "ST_AsGeoJSON(ST_Transform(ST_SetSRID(ST_MakePoint(96.25, 4.15), 4326), 3857), 0) as geojson, "
            "(select count(*) from spatial_ref_sys where srid in (4326, 3857, 32750)) as known"
        )).one()

    assert row.version.startswith("3.4")
    assert '"Point"' in row.geojson
    assert row.known == 3


def test_app_role_reads_and_writes_rows(app_engine):
    with app_engine.connect() as connection, connection.begin() as transaction:
        connection.execute(text("insert into organizations (name) values ('Role test')"))
        assert connection.execute(text("select count(*) from organizations where name = 'Role test'")).scalar() == 1
        transaction.rollback()


def test_app_role_may_create_tables_only_in_ingest(app_engine):
    with app_engine.connect() as connection, connection.begin() as transaction:
        connection.execute(text("create table ingest.ds_role_test (id int)"))
        transaction.rollback()

    with app_engine.connect() as connection, connection.begin():
        with pytest.raises(ProgrammingError, match="permission denied"):
            connection.execute(text("create table public.role_test (id int)"))


def test_app_role_cannot_change_application_tables(app_engine):
    for statement in ("drop table datasets", "alter table datasets add column hacked int", "truncate datasets"):
        with app_engine.connect() as connection, connection.begin():
            with pytest.raises(ProgrammingError):
                connection.execute(text(statement))
