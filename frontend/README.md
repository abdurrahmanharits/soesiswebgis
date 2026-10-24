# SoESIS WebGIS — Frontend (Next.js)

Proyek Next.js 16 (App Router, TypeScript, Tailwind v4) mandiri. Dijalankan **di host**
(Node 24, npm). Tidak pernah mengakses database, Redis, atau storage: semua data lewat
backend FastAPI.

Status: **R0 — fondasi** (layout publik/internal, proxy API, client bertipe, test).

## Arsitektur

```text
src/
  app/                    routing + layout saja
    (public)/             root layout publik — <html lang="id">: /
    (internal)/           root layout internal — <html lang="en">: /status (login mulai R0.5)
  features/<fitur>/
    ui/                   komponen (server/client)
    hooks/                state & efek di client
    services/             pemanggilan API fitur ini
  shared/
    api/                  satu-satunya akses ke backend (openapi-fetch + tipe ter-generate)
    ui/                   komponen dasar
    map/                  modul OpenLayers (mulai R2/R4)
```

Aturan yang dicek `npm run lint` (eslint-plugin-boundaries):

- `app → features/*/ui | shared/ui`; `ui → hooks | services` (fitur yang sama);
  `hooks → services`; `services → shared/api`.
- Fitur tidak boleh meng-import fitur lain.
- `fetch` hanya di `src/shared/api`; driver database/Redis dilarang di seluruh frontend.

## Kontrak API

Browser memanggil `/api/*` di origin yang sama; `next.config.ts` meneruskannya ke
`BACKEND_URL` (FastAPI). Server component memanggil `BACKEND_URL` langsung.
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

Jika port 3000 dipakai aplikasi lain: `npx next dev -H 127.0.0.1 -p 3100`, dan untuk test
`E2E_PORT=3100 npm run test:e2e` (PowerShell: `$env:E2E_PORT=3100; npm run test:e2e`).
