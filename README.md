# SoESIS WebGIS

> **Branch `rewrite/nextjs-fastapi` — rewrite ke Next.js + FastAPI sedang berjalan.**
> Versi Laravel di bawah tetap berjalan (`api/`, port 8000) sebagai acuan perilaku sampai R7.
>
> | Bagian | Folder | Port | Dokumentasi |
> |---|---|---|---|
> | Backend FastAPI + worker Celery (GDAL 3.10, Python 3.12) | `backend/` | 127.0.0.1:8001 | [backend/README.md](backend/README.md) |
> | Frontend Next.js 16 (di host) | `frontend/` | 127.0.0.1:3000 | [frontend/README.md](frontend/README.md) |
> | Kontrak API (satu-satunya penghubung keduanya) | `contracts/openapi.json` | — | — |
>
> Milestone: **R0 fondasi ✓** → **R0.5 auth (JWT, role, admin) ✓** → R1 ingest → R2 preview → R3 kurasi → R4 peta →
> R5 katalog → R7 pembersihan Laravel. Fitur di bawah ini masih dilayani Laravel.

Platform data spasial untuk mengunggah, memproses, dan meninjau dataset **Shapefile** dan **GeoTIFF**.
Dataset diproses secara asinkron (antrean Redis), vektor diimpor ke **PostGIS**, raster dikonversi
menjadi **Cloud Optimized GeoTIFF (COG)**, lalu dapat dipratinjau di peta **OpenLayers**.

## Fitur

- Daftar dataset dengan ringkasan status, pencarian, dan filter format/status
- Upload Shapefile (`.zip`) dan GeoTIFF (`.tif`, `.tiff`) hingga 512 MB, dengan progress bar
- Pemrosesan di latar belakang dengan status otomatis: `queued` → `processing` → `ready_for_curation` / `failed`
- Halaman detail dengan metadata (CRS, geometri, atribut, ukuran raster, NoData, dll.)
- Pratinjau peta: vektor dari PostGIS (GeoJSON), raster COG via HTTP Range, basemap OSM/Imagery,
  popup atribut, kontrol visibilitas dan opacity, legenda raster
- Kurasi metadata (ISO 19115-lite) dengan approve/reject/reopen dan riwayat
- Peta multi-layer (`/map`) dengan state di URL
- **Katalog publik** (`/catalogue`, bahasa Indonesia): hanya dataset yang disetujui, dengan pencarian,
  filter, urutan, halaman, thumbnail cakupan, halaman detail metadata + atribusi, dan peta publik
  (`/catalogue/map`). Dataset yang di-reopen/ditolak langsung hilang dari katalog (404).

## Stack

| Bagian | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Database | PostgreSQL 15 + PostGIS 3.4 |
| Antrean | Redis 7 + Laravel queue worker |
| Pemrosesan spasial | GDAL/OGR 3.10 (`ogrinfo`, `ogr2ogr`, `gdalinfo`, `gdal_translate`) |
| Frontend | Blade, vanilla JavaScript, Tailwind CSS v4, Vite, OpenLayers 10 |
| Infrastruktur | Docker Compose (`app`, `worker`, `db`, `redis`) |

## Prasyarat

- Docker Desktop (atau Docker Engine + Compose v2)
- Node.js 20+ dan npm **di host** (Node tidak tersedia di dalam container)

## Setup pertama kali

Jalankan dari folder root repository.

```bash
# 1. Build image PHP (berisi GDAL)
docker compose build

# 2. Jalankan database dan Redis
docker compose up -d db redis

# 3. Install dependency PHP
docker compose run --rm --no-deps app composer install

# 4. Buat file environment dan application key
cp api/.env.example api/.env
docker compose run --rm --no-deps app php artisan key:generate

# 5. Jalankan semua service, lalu migrasi database
#    (termasuk schema PostGIS "ingest" untuk tabel hasil import Shapefile)
docker compose up -d
docker compose exec app php artisan migrate

# 6. Build aset frontend (di host)
cd api
npm install
npm run build
cd ..

# 7. Cache config/route/view Laravel (mempercepat request), lalu restart server
docker compose exec app php artisan optimize
docker compose restart app worker

# 8. Buat akun admin pertama (password diketik tersembunyi; hanya bisa sekali,
#    selama belum ada admin). User lain dibuat admin di /admin/users.
docker compose exec app php artisan soesis:create-admin
```

Buka **http://localhost:8000** (halaman depan publik). Area internal ada di
**http://localhost:8000/login** (link "Masuk" di pojok kanan atas).

Di Windows PowerShell, ganti `cp` dengan `Copy-Item`.

## Penggunaan sehari-hari

```bash
docker compose up -d        # menyalakan semua service
docker compose ps           # cek status (app harus "healthy")
docker compose down         # mematikan (data tetap tersimpan di volume)
```

Setelah mengubah file di `api/resources/js` atau `api/resources/css`:

```bash
cd api && npm run build
```

## Testing

Test berjalan di database terpisah **`soesis_test`** (PostGIS), bukan di database pengembangan.
Buat sekali:

```bash
docker compose exec db psql -U soesis -d soesis -c "create database soesis_test"
docker compose exec db psql -U soesis -d soesis_test -c "create extension if not exists postgis"
```

Lalu jalankan:

```bash
docker compose exec app php artisan test
```

Test otomatis menolak berjalan jika database bukan `soesis_test`.

## Struktur

```text
docker-compose.yml          service app, worker, db, redis
docker/php/Dockerfile       image PHP 8.4 + GDAL + konfigurasi PHP/OPcache
api/                        aplikasi Laravel
  app/Jobs/ProcessSpatialDataset.php          pipeline ingestion Shapefile/GeoTIFF
  app/Http/Controllers/Api/DatasetController.php         daftar, detail, upload
  app/Http/Controllers/Api/DatasetPreviewController.php  endpoint pratinjau peta
  app/Support/CatalogueDataset.php            whitelist field publik + query dataset approved
  resources/views/datasets/                   halaman daftar & detail (internal)
  resources/views/catalogue/                  katalog publik (bahasa Indonesia, lang/id.json)
  resources/js/datasets/                      logika halaman (API, upload, polling)
  resources/js/catalogue/                     katalog publik (filter client-side, thumbnail)
  resources/js/map/                           peta OpenLayers (lazy-loaded)
  storage/app/private/                        file upload, file kerja, dan COG hasil proses
```

## API

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/datasets` | Daftar dataset |
| POST | `/api/datasets` | Upload (`multipart/form-data`: `title`, `file`) |
| GET | `/api/datasets/{id}` | Detail dataset |
| GET | `/api/datasets/{id}/preview/vector` | GeoJSON (EPSG:4326), maks. 10.000 fitur |
| GET | `/api/datasets/{id}/preview/raster` | File COG, mendukung HTTP Range |
| GET | `/api/datasets/{id}/preview/raster/info` | CRS, extent, NoData, statistik band |
| PATCH/POST | `/api/datasets/{id}/curation[/approve\|/reject\|/reopen]` | Kurasi metadata dan status |
| GET | `/api/datasets/{id}/curation/history` | Riwayat kurasi |
| GET | `/api/map/layers` | Daftar layer untuk `/map` (approved + pending) |
| GET | `/api/catalogue` | Katalog publik: semua dataset approved, field publik saja |
| GET | `/api/catalogue/{id}` | Detail publik (404 jika belum/tidak lagi approved) |
| GET | `/api/catalogue/{id}/preview/{vector,raster,raster/info}` | Pratinjau publik, khusus approved |

Endpoint internal (`/api/datasets*`, `/api/map/layers`) memakai session login + CSRF (header
`X-CSRF-TOKEN`); tanpa login → `401 {"message":"Unauthenticated."}`, tanpa hak → `403`.
Endpoint `/api/catalogue*` tetap terbuka.

## Akun dan peran

Area publik (`/`, `/catalogue`, `/catalogue/{id}`, `/catalogue/map`) terbuka tanpa login.
Area internal wajib login dengan e-mail atau username. Tidak ada registrasi mandiri.

| Aksi | Uploader | Curator | Admin |
|---|:-:|:-:|:-:|
| Lihat `/datasets`, detail, preview, `/map`, riwayat kurasi | ✓ | ✓ | ✓ |
| Upload dataset | ✓ | ✓ | ✓ |
| Edit metadata kurasi | dataset sendiri | ✓ | ✓ |
| Approve | – | ✓¹ | ✓¹ |
| Reject / Reopen | – | ✓ | ✓ |
| Kelola user & organisasi (`/admin/users`) | – | – | ✓ |

¹ Tidak ada yang boleh meng-approve dataset yang ia upload sendiri. Dataset lama (sebelum ada
login) tidak punya pemilik, jadi bisa di-approve curator/admin mana pun.

- Riwayat kurasi mencatat akun yang login (`user_id` + nama). Riwayat lama (nama yang dulu diketik)
  tetap tampil apa adanya.
- Organisasi hanya untuk provenance: dataset menyimpan uploader dan organisasinya; tidak membatasi akses.
- User dinonaktifkan (bukan dihapus) agar riwayat tetap utuh; sesi user nonaktif berakhir di request berikutnya.
- Sesi berlaku 120 menit (`SESSION_LIFETIME`). Login dibatasi 5 percobaan gagal per menit.
- Lupa password: admin mengatur ulang di `/admin/users`, atau lewat CLI:
  ```bash
  docker compose exec app php artisan soesis:reset-password <email-atau-username>
  ```

## Catatan lingkungan pengembangan

- **Cache Laravel (`php artisan optimize`).** Config, route, event, dan view di-cache agar setiap
  request jauh lebih cepat di bind mount Windows. Konsekuensinya, setelah mengubah `api/.env`,
  file di `api/config/`, atau `api/routes/`, jalankan ulang:
  ```bash
  docker compose exec app php artisan optimize
  docker compose restart app worker
  ```
  Untuk mematikan cache: `docker compose exec app php artisan optimize:clear`.
  Test tidak terpengaruh: `phpunit.xml` selalu membaca config langsung.
- **Jumlah worker server** (`PHP_CLI_SERVER_WORKERS`) diatur di `docker-compose.yml`, bukan di `.env`,
  karena `.env` tidak dibaca lagi setelah config di-cache.
- **Perubahan PHP/Blade terlambat terlihat (hingga 60 detik).** OPcache hanya memeriksa ulang file
  setiap 60 detik agar server tetap cepat di bind mount Windows. Untuk langsung: `docker compose restart app`.
- **Perubahan `api/.env`** perlu `docker compose restart app worker`.
- **Perubahan kode job/pipeline** perlu `docker compose restart worker`, karena worker adalah proses yang berjalan terus.
- **Docker Desktop macet** (perintah `docker` mengembalikan error 500): tutup paksa proses Docker Desktop,
  jalankan `wsl --shutdown`, lalu buka kembali Docker Desktop.
- File contoh berukuran besar (`sample_data/`) tidak disertakan di repository.
