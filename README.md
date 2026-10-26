# Absensi PKKMB INSTIKI 2026

- `api/` — backend Go (net/http + pgx). Auth sesi cookie, semua aturan hak akses ada di sini.
- `web/` — frontend React + Vite.
- `supabase/` — skema database (migrasi) untuk Supabase/Postgres.

## Jalankan lokal

Butuh Docker, Go, dan Node.

```sh
# 1. Database (Supabase lokal)
.tools/supabase/supabase.exe start -x realtime,storage-api,imgproxy,edge-runtime,logflare,vector,supavisor,mailpit

# 2. API  (salin api/.env.example lalu isi; set variabelnya di shell)
cd api
DATABASE_URL=postgresql://app_api:app_api_local_dev@127.0.0.1:54322/postgres COOKIE_SECURE=false go run .

# 3. Frontend (proxy /api ke :8080)
cd web && npm install && npm run dev   # http://localhost:5173
```

Supabase CLI tidak ikut di repo (`.tools/` diabaikan git). Unduh binarinya dari rilis `supabase/cli`.

## Pindah data dari MySQL Laravel lama

Database tujuan harus kosong. Sumber hanya dibaca.

```sh
cd api
go run ./cmd/migrasi -mysql "root:@tcp(127.0.0.1:3306)/absensipkkmb" -pg "postgresql://postgres:postgres@127.0.0.1:54322/postgres"
```

Password lama (bcrypt Laravel) tetap bisa dipakai login.

## Database demo lokal

`absensi_demo` adalah salinan data hasil migrasi untuk mencoba UI. Semua akunnya diberi kata sandi lokal `demo12345` (khusus database ini, bukan password asli). Jalankan API dengan `DATABASE_URL=postgresql://app_api:app_api_local_dev@127.0.0.1:54322/absensi_demo`.

## Tes

Tes integrasi + keamanan jalan di database terpisah `absensi_test` (tes menolak database lain).

```sh
docker exec supabase_db_absensipkkmb psql -U postgres -c "create database absensi_test"
docker exec -i supabase_db_absensipkkmb psql -U postgres -d absensi_test < supabase/migrations/20261026000001_schema.sql
docker exec supabase_db_absensipkkmb psql -U postgres -d absensi_test -c "grant connect on database absensi_test to app_api"
cd api && go test ./...
```

## Produksi

- `npm run build` di `web/`, lalu jalankan API dengan `WEB_DIST=../web/dist` supaya satu origin.
- Jalankan di belakang HTTPS dan biarkan `COOKIE_SECURE=true` (cookie jadi `__Host-sid`).
- Di Supabase cloud: terapkan migrasi, set password role `app_api` lewat SQL editor, pakai connection string pooler dengan user `app_api.<project-ref>`.
- Semua tabel memakai RLS dan tertutup untuk `anon`/`authenticated`, jadi anon key Supabase tidak bisa membaca data.
