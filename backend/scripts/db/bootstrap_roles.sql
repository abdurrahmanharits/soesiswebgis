-- Database roles for the FastAPI backend. Idempotent; run as the superuser (soesis)
-- once per database (soesis and soesis_pytest), see backend/README.md:
--
--   psql -U soesis -d <database> -v migrator_password=... -v app_password=... -f bootstrap_roles.sql
--
--   soesis_migrator  schema owner role, used only by Alembic (DDL).
--   soesis_app       runtime role for API and worker: read/write data, and CREATE only in
--                    schema "ingest" (ogr2ogr creates one table per uploaded Shapefile there).
--                    It cannot create, alter or drop tables in "public".

\set ON_ERROR_STOP on

SELECT 'CREATE ROLE soesis_migrator LOGIN' WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'soesis_migrator')
\gexec
SELECT 'CREATE ROLE soesis_app LOGIN' WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'soesis_app')
\gexec

ALTER ROLE soesis_migrator WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD :'migrator_password';
ALTER ROLE soesis_app WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD :'app_password';

-- Tables created by Laravel are owned by "soesis"; membership lets Alembic alter them
-- until ownership moves to soesis_migrator when Laravel is removed (R7).
GRANT soesis TO soesis_migrator;

CREATE EXTENSION IF NOT EXISTS postgis;
CREATE SCHEMA IF NOT EXISTS ingest;

SELECT format('GRANT CONNECT ON DATABASE %I TO soesis_migrator, soesis_app', current_database())
\gexec

-- public: migrator owns the schema objects; app only reads and writes rows.
GRANT USAGE, CREATE ON SCHEMA public TO soesis_migrator;
GRANT USAGE ON SCHEMA public TO soesis_app;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE CREATE ON SCHEMA public FROM soesis_app;

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO soesis_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO soesis_app;

-- ingest: one table per Shapefile, created at runtime by the worker.
GRANT USAGE, CREATE ON SCHEMA ingest TO soesis_migrator, soesis_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA ingest TO soesis_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA ingest TO soesis_app;

-- Future objects: tables from Alembic (migrator) and from Laravel (soesis) stay usable by the app.
ALTER DEFAULT PRIVILEGES FOR ROLE soesis_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO soesis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE soesis_migrator IN SCHEMA public
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO soesis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE soesis IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO soesis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE soesis IN SCHEMA public
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO soesis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE soesis IN SCHEMA ingest
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO soesis_app;
