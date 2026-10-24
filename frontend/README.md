# SoESIS WebGIS — Frontend (Next.js)

Proyek Next.js 16 (App Router, TypeScript, Tailwind v4) mandiri. Dijalankan **di host**
(Node 24, npm). Tidak pernah mengakses database, Redis, atau storage: semua data lewat
backend FastAPI.

Status: **R0 — fondasi ✓**, **R0.5 — login, role, admin users/organizations ✓**.

## Arsitektur

```text
src/
  proxy.ts                UX guard: tanpa cookie penanda sesi → /login (bukan otorisasi)
  app/                    routing + layout saja
    (public)/             root layout publik — <html lang="id">: /
    (auth)/               root layout login — <html lang="en">: /login
    (internal)/           root layout internal — <html lang="en">, wajib login:
                          /status, /admin/users, /admin/users/[id], /admin/organizations
  features/<fitur>/       auth (form login), admin (users & organizations), system (status)
    ui/                   komponen (server/client)
    hooks/                state & efek di client
    services/             pemanggilan API fitur ini
  shared/
    api/                  satu-satunya akses ke backend (openapi-fetch + tipe ter-generate,
                          refresh otomatis saat 401, event "session expired")
    session/              akun yang login: provider, header (nama, role, logout), banner,
                          role gate, link "Masuk" di area publik
    ui/                   komponen dasar
    map/                  modul OpenLayers (mulai R2/R4)
```

Aturan yang dicek `npm run lint` (eslint-plugin-boundaries):

- `app → features/*/ui | shared/ui | shared/session`; `ui → hooks | services` (fitur yang sama)
  `| shared/ui | shared/session`; `hooks → services`; `services → shared/api`;
  `shared/session → shared/api | shared/ui`.
- Fitur tidak boleh meng-import fitur lain.
- `fetch` hanya di `src/shared/api`; driver database/Redis dilarang di seluruh frontend.

## Autentikasi di frontend

- Token JWT ada di cookie **httpOnly** yang diset FastAPI; JavaScript tidak pernah membaca token.
- `shared/api`: respons `401` → satu kali `POST /api/auth/refresh` (dibagi bila beberapa request
  gagal bersamaan) → request diulang. Bila refresh gagal, muncul banner
  **"Session expired. Please sign in again."** tanpa membuang isian form.
- Identitas di header berasal dari `GET /api/auth/me`, bukan dari isi JWT.
- Halaman admin disembunyikan untuk non-admin (UX); backend tetap menolak dengan `403`.

## Kontrak API

Browser memanggil `/api/*` di origin yang sama; `next.config.ts` meneruskannya ke
`BACKEND_URL` (FastAPI). Server component memanggil `BACKEND_URL` langsung (hanya data publik).
Tipe TypeScript di-generate dari `../contracts/openapi.json`:

```bash
npm run api:generate   # setelah backend mengekspor kontrak baru
npm run api:check      # gagal jika src/shared/api/schema.ts tertinggal
```

## Perintah

```bash
cp .env.example .env.local     # sekali: BACKEND_URL=http://127.0.0.1:8001
npm install
npm run dev                    # http://127.0.0.1:3000
npm run lint && npm run typecheck
npm run build && npm run start
npm run test:e2e               # Playwright (Chrome lokal); backend harus jalan
```

Test E2E memakai akun QA sementara (`qa.uploader`, `qa.curator`, `qa.admin`) dengan password
di `E2E_PASSWORD`; tanpa variabel itu test yang butuh login dilewati.

Jika port 3000 dipakai aplikasi lain: `npx next dev -H 127.0.0.1 -p 3100`, dan untuk test
`E2E_PORT=3100 npm run test:e2e` (PowerShell: `$env:E2E_PORT=3100; npm run test:e2e`).
Origin `http://127.0.0.1:3100` sudah diizinkan backend (`SOESIS_ALLOWED_ORIGINS`).
