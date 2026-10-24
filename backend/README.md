# SoESIS WebGIS — Backend (FastAPI)

Proyek Python mandiri: API (FastAPI) + worker (Celery) dalam satu image berbasis
**GDAL 3.10.3 resmi (OSGeo), Python 3.12**. Frontend hanya berhubungan lewat HTTP dan
kontrak OpenAPI di `../contracts/openapi.json`.

Status: **R0 — fondasi** (healthcheck, PostGIS, Redis, Alembic, arsitektur, test).

## Arsitektur (clean, per modul)

```text
src/soesis/
  main.py            composition root API: bangun adapter, rakit use case, pasang router
  worker.py          composition root Celery (queue "spatial")
  contract.py        ekspor OpenAPI → ../contracts/openapi.json
  shared/            kernel: config, engine DB + unit of work, format error
  spatial/           algoritma spasial murni (tanpa DB/web), diisi mulai R1
  modules/<modul>/
    domain/          entity & aturan bisnis — Python murni
    application/     use case + port (interface)
    infrastructure/  adapter: SQLAlchemy, GDAL CLI, storage, Celery, Redis
    api/             router FastAPI + DTO Pydantic
```

Aturan yang dicek otomatis (`lint-imports`, juga dijalankan pytest):

- `api | infrastructure → application → domain`; `api` tidak meng-import `infrastructure`.
- `domain`, `application`, `spatial` tidak boleh meng-import FastAPI, Pydantic, SQLAlchemy,
  psycopg, Celery, Redis, GDAL, atau `soesis.shared.db`.
- `shared` dan `spatial` tidak bergantung pada modul.

## Database

- Database dev `soesis` dipakai bersama Laravel selama rewrite; skema dimiliki **Alembic**
  sejak revisi `0001_baseline` (DDL identik dengan `pg_dump` skema Laravel).
  Di `soesis` revisi itu hanya di-*stamp*; di database baru (mis. `soesis_pytest`) ia membuat tabel.
- Dua role:
  - `soesis_migrator` — DDL, hanya untuk Alembic (`SOESIS_MIGRATOR_DATABASE_URL`).
  - `soesis_app` — runtime API/worker: baca/tulis data, `CREATE` hanya di schema `ingest`
    (tidak bisa membuat/mengubah/menghapus tabel di `public`).
- Test hanya memakai database **`soesis_pytest`** (fixture menolak nama lain).

## Setup pertama kali

Dari root repo (Docker sudah menjalankan `db` dan `redis`):

```bash
# 1. Environment: salin, lalu isi dua password acak (dipakai juga di langkah 3)
cp backend/.env.example backend/.env

# 2. Database test
docker compose exec db psql -U soesis -d soesis -c "create database soesis_pytest"

# 3. Role database (sekali per database; ganti <...> dengan password dari backend/.env)
for DB in soesis soesis_pytest; do
  docker compose exec -T db psql -U soesis -d $DB \
    -v migrator_password='<password migrator>' -v app_password='<password app>' \
    -f - < backend/scripts/db/bootstrap_roles.sql
done

# 4. Build & jalankan API + worker
docker compose up -d --build backend backend-worker

# 5. Tandai database dev sebagai baseline (tabel sudah ada, tidak dibuat ulang)
docker compose exec backend alembic stamp 0001_baseline
```

## Perintah sehari-hari

```bash
docker compose up -d backend backend-worker     # jalankan (reload otomatis saat src/ berubah)
docker compose logs -f backend                  # log API
docker compose exec backend alembic current     # revisi skema
docker compose exec backend alembic upgrade head
docker compose exec backend pytest              # unit + integrasi + arsitektur
docker compose exec backend lint-imports        # aturan clean architecture saja
docker compose exec backend ruff check src tests
docker compose exec backend python -m soesis.contract   # perbarui contracts/openapi.json
docker compose exec backend-worker celery --app=soesis.worker inspect ping
docker compose stop backend backend-worker
```

Setelah mengubah endpoint: jalankan `python -m soesis.contract`, lalu di `frontend/`
`npm run api:generate`. Test backend gagal jika kontrak tertinggal.

Menambah dependency: `uv add <paket>` (di host atau container), lalu
`docker compose build backend` agar image memakai `uv.lock` terbaru.

## Endpoint

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/health` | Proses hidup → `{"status":"ok"}` |
| GET | `/api/health/ready` | PostGIS + Redis; `503` jika ada yang gagal |
| GET | `/api/openapi.json`, `/api/docs` | Kontrak & dokumentasi interaktif |

Port: `127.0.0.1:8001` (Laravel tetap di `:8000` sebagai acuan perilaku).
