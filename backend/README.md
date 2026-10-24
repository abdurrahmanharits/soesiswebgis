# SoESIS WebGIS — Backend (FastAPI)

Proyek Python mandiri: API (FastAPI) + worker (Celery) dalam satu image berbasis
**GDAL 3.10.3 resmi (OSGeo), Python 3.12**. Frontend hanya berhubungan lewat HTTP dan
kontrak OpenAPI di `../contracts/openapi.json`.

Status: **R0 — fondasi ✓**, **R0.5 — autentikasi & role (JWT) ✓**.

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

# 5. Tandai database dev sebagai baseline (tabel sudah ada, tidak dibuat ulang), lalu migrasi baru
docker compose exec backend alembic stamp 0001_baseline
docker compose exec backend alembic upgrade head
```

`backend/.env` juga memerlukan `SOESIS_JWT_SECRET` (min. 32 karakter acak) — lihat `.env.example`.

## Menjalankan di Windows tanpa container backend (uv)

Aplikasi berjalan langsung di host; sementara ini PostgreSQL (`127.0.0.1:5433`) dan Redis
(`127.0.0.1:6379`) masih container `db`/`redis`. `backend/.env.local` (tidak di-commit) menimpa
`backend/.env` dengan alamat `127.0.0.1` dan path Windows.

```powershell
cd C:\Users\USER\Downloads\riset\wwf\webgis_poc\backend
uv sync                                    # sekali / setelah dependency berubah (Python 3.12, paket editable)
uv run uvicorn soesis.main:app --host 127.0.0.1 --port 8001 --reload --reload-dir src --proxy-headers --forwarded-allow-ips=127.0.0.1
# worker (terminal lain; Windows butuh pool "solo"):
uv run celery --app=soesis.worker worker --queues=spatial --pool=solo --loglevel=INFO
# CLI
uv run python -m soesis.cli seed-demo-users
```

Jangan jalankan container `backend` bersamaan (port 8001 sama). GDAL belum tersedia di Windows;
baru dibutuhkan mulai R1 (ingest).

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

## Autentikasi (R0.5, modul `identity`)

- **JWT HS256** di cookie httpOnly pada origin Next.js (browser tidak pernah memegang token):

  | Cookie | Isi | Path | Umur |
  |---|---|---|---|
  | `soesis_access` | access token (JWT: `sub`, `sid`, `exp`) | `/` | 15 menit |
  | `soesis_refresh` | refresh token acak (hanya hash SHA-256 yang disimpan) | `/api/auth` | 7 hari |
  | `soesis_session` | `1` — penanda untuk `proxy.ts`, tanpa otoritas | `/` | 7 hari |

- Setiap request terproteksi membaca user **dan sesinya** dari database: user nonaktif, ganti role,
  logout, atau reset password berlaku **di request berikutnya**.
- Refresh token dirotasi; token lama yang dipakai ulang (lewat 10 detik setelah rotasi) mencabut
  seluruh sesi itu (tabel `auth_refresh_tokens`, migrasi `0002`).
- CSRF: cookie `SameSite=Lax` + cek `Origin` untuk POST/PUT/PATCH/DELETE (`SOESIS_ALLOWED_ORIGINS`).
- Login email/username (tidak peka huruf besar), pesan gagal generik, maks. 5 gagal/menit per
  login+IP (Redis DB 3).
- Password: bcrypt cost 12 dengan prefix **`$2y$`** — akun Laravel tetap bisa login, dan hash yang
  ditulis FastAPI tetap terbaca Laravel (Laravel menolak `$2b$` bawaan library Python).

| Aksi | Uploader | Curator | Admin |
|---|:-:|:-:|:-:|
| Area internal, upload | ✓ | ✓ | ✓ |
| Edit metadata dataset | milik sendiri | ✓ | ✓ |
| Approve (bukan upload sendiri), reject, reopen | – | ✓ | ✓ |
| Kelola user & organisasi | – | – | ✓ |

Aturan ini ada di `modules/identity/domain/policy.py` dan dipakai modul dataset/kurasi (R1, R3).

```bash
# Admin pertama (sekali; password diketik tersembunyi, konfirmasi diulang bila tidak cocok)
docker compose exec backend python -m soesis.cli create-admin
# Reset password bila tidak ada admin yang bisa login
docker compose exec backend python -m soesis.cli reset-password <email-atau-username>
```

Akun yang dibuat lewat Laravel (`php artisan soesis:create-admin`) juga langsung bisa dipakai.

### Akun demo (development)

```bash
docker compose exec backend python -m soesis.cli seed-demo-users          # password diketik tersembunyi
docker compose exec backend python -m soesis.cli seed-demo-users --reset-passwords
docker compose exec backend python -m soesis.cli remove-demo-users        # hapus semua demo.*
```

| Username | Role | Untuk mencoba |
|---|---|---|
| `demo.uploader` | Uploader | upload; edit metadata dataset sendiri saja |
| `demo.curator` | Curator | approve / reject / reopen |
| `demo.curator2` | Curator | meng-approve upload `demo.curator` (larangan approve upload sendiri) |
| `demo.admin` | Admin | kelola user & organisasi |
| `demo.inactive` | Uploader, nonaktif | login ditolak dengan pesan generik |

Semua masuk organisasi **Demo Organization**, e-mail `<username>@example.invalid`, dan memakai
satu password yang Anda pilih (atau `SOESIS_SEED_PASSWORD`). Tidak ada password di repo; perintah
ditolak bila `SOESIS_ENV=production`. Akun demo juga bisa login di Laravel (hash `$2y$` yang sama).

## Endpoint

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/health` | Proses hidup → `{"status":"ok"}` |
| GET | `/api/health/ready` | PostGIS + Redis; `503` jika ada yang gagal |
| POST | `/api/auth/login` | `{"login","password"}` → user + cookie; `401` generik, `429` throttle |
| POST | `/api/auth/refresh` | Rotasi refresh token, access token baru |
| POST | `/api/auth/logout` | Akhiri sesi, hapus cookie (`204`) |
| GET | `/api/auth/me` | Akun yang login (`401` tanpa sesi) |
| GET/POST | `/api/admin/users` | Daftar / buat user (admin) |
| PATCH | `/api/admin/users/{id}` | Ubah profil, role, organisasi, aktif |
| POST | `/api/admin/users/{id}/reset-password` | Password baru, keluarkan user dari semua sesi |
| GET/POST/PATCH | `/api/admin/organizations[/{id}]` | Daftar / buat / ganti nama organisasi |
| GET | `/api/openapi.json`, `/api/docs` | Kontrak & dokumentasi interaktif |

Error selalu `{"message": ...}` (+ `"errors"` untuk 422): `401` belum login, `403` tanpa hak.

Port: `127.0.0.1:8001` (Laravel tetap di `:8000` sebagai acuan perilaku).
