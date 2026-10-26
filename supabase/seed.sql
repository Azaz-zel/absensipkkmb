-- Hanya untuk Supabase lokal (`supabase db reset`). Di cloud, set password app_api
-- lewat SQL editor dengan nilai rahasia dan simpan di DATABASE_URL server API.
alter role app_api password 'app_api_local_dev';
