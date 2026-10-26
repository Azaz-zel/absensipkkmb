// API absensi PKKMB: JSON di /api, dan (di produksi) file build React dari WEB_DIST.
package main

import (
	"context"
	"errors"
	"io/fs"
	"log"
	"net/http"
	"os"
	"os/signal"
	"path"
	"strings"
	"syscall"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
)

type App struct {
	db           *pgxpool.Pool
	secureCookie bool
	origins      map[string]bool
	limiter      *loginLimiter
	ipLimiter    *loginLimiter
}

func env(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func main() {
	dsn := os.Getenv("DATABASE_URL")
	if dsn == "" {
		log.Fatal("DATABASE_URL wajib diisi (lihat .env.example)")
	}
	ctx := context.Background()
	pool, err := pgxpool.New(ctx, dsn)
	if err != nil {
		log.Fatal(err)
	}
	defer pool.Close()
	if err := pool.Ping(ctx); err != nil {
		log.Fatal("tidak bisa terhubung ke database: ", err)
	}

	app := &App{
		db:           pool,
		secureCookie: env("COOKIE_SECURE", "true") != "false",
		origins:      map[string]bool{},
		limiter:      newLoginLimiter(5, 15*time.Minute),
		ipLimiter:    newLoginLimiter(50, 15*time.Minute),
	}
	for _, o := range strings.Split(env("ALLOWED_ORIGINS", "http://localhost:5173"), ",") {
		app.origins[strings.TrimSpace(o)] = true
	}

	mux := http.NewServeMux()
	app.routes(mux)
	if dist := os.Getenv("WEB_DIST"); dist != "" {
		mux.Handle("/", spa(dist))
	}

	srv := &http.Server{
		Addr:              env("ADDR", "127.0.0.1:8080"),
		Handler:           securityHeaders(mux),
		ReadHeaderTimeout: 10 * time.Second,
		ReadTimeout:       30 * time.Second,
		WriteTimeout:      60 * time.Second,
		IdleTimeout:       120 * time.Second,
		MaxHeaderBytes:    1 << 16,
	}

	go app.cleanupLoop(ctx)
	go func() {
		log.Printf("API jalan di http://%s", srv.Addr)
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatal(err)
		}
	}()
	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop
	shutdownCtx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(shutdownCtx)
}

// Hapus sesi login dan pratinjau import yang sudah kedaluwarsa.
func (a *App) cleanupLoop(ctx context.Context) {
	t := time.NewTicker(10 * time.Minute)
	defer t.Stop()
	for {
		_, _ = a.db.Exec(ctx, `delete from sesi_login where expires_at < now() or last_active_at < now() - $1::interval`, idleTimeout.String())
		_, _ = a.db.Exec(ctx, `delete from import_tertunda where expires_at < now()`)
		select {
		case <-ctx.Done():
			return
		case <-t.C:
		}
	}
}

func (a *App) routes(m *http.ServeMux) {
	admin := []string{"admin"}
	m.HandleFunc("POST /api/login", a.login)
	m.HandleFunc("POST /api/logout", a.logout)
	m.HandleFunc("GET /api/me", a.auth(nil, a.me))
	m.HandleFunc("GET /api/dashboard", a.auth(nil, a.dashboard))

	// Absensi & rekap: admin + PJ (PJ dibatasi ke kelasnya sendiri).
	ap := []string{"admin", "pj"}
	m.HandleFunc("GET /api/absensi", a.auth(ap, a.absensiIndex))
	m.HandleFunc("GET /api/absensi/{sesi}/{kelas}", a.auth(ap, a.absensiShow))
	m.HandleFunc("PUT /api/absensi/{sesi}/{kelas}", a.auth(ap, a.absensiSimpan))
	m.HandleFunc("GET /api/rekap/mahasiswa", a.auth(ap, a.rekapMahasiswa))
	m.HandleFunc("GET /api/rekap/kelas", a.auth(ap, a.rekapKelas))
	m.HandleFunc("GET /api/filter", a.auth(nil, a.filterOptions))

	// Detail satu maba: admin & manajer semua, PJ hanya maba di kelasnya.
	m.HandleFunc("GET /api/mahasiswa/{id}/detail", a.auth(nil, a.mahasiswaDetail))

	// Poin: admin + manajer.
	am := []string{"admin", "manajer"}
	m.HandleFunc("GET /api/poin/cari", a.auth(am, a.poinCari))
	m.HandleFunc("POST /api/mahasiswa/{id}/pelanggaran", a.auth(am, a.pelanggaranCatat))

	// Admin saja.
	m.HandleFunc("PUT /api/pelanggaran/{id}", a.auth(admin, a.pelanggaranUbah))
	m.HandleFunc("DELETE /api/pelanggaran/{id}", a.auth(admin, a.pelanggaranHapus))
	m.HandleFunc("PUT /api/absensi-record/{id}", a.auth(admin, a.absensiRecordUbah))
	m.HandleFunc("DELETE /api/absensi-record/{id}", a.auth(admin, a.absensiRecordHapus))

	m.HandleFunc("GET /api/sesi", a.auth(admin, a.sesiIndex))
	m.HandleFunc("POST /api/sesi", a.auth(admin, a.sesiBuat))
	m.HandleFunc("GET /api/sesi/{id}", a.auth(admin, a.sesiShow))
	m.HandleFunc("PUT /api/sesi/{id}", a.auth(admin, a.sesiUbah))
	m.HandleFunc("DELETE /api/sesi/{id}", a.auth(admin, a.sesiHapus))
	m.HandleFunc("POST /api/sesi/{id}/status", a.auth(admin, a.sesiStatus))

	m.HandleFunc("GET /api/mahasiswa", a.auth(admin, a.mahasiswaIndex))
	m.HandleFunc("POST /api/mahasiswa", a.auth(admin, a.mahasiswaBuat))
	m.HandleFunc("PUT /api/mahasiswa/{id}", a.auth(admin, a.mahasiswaUbah))
	m.HandleFunc("DELETE /api/mahasiswa/{id}", a.auth(admin, a.mahasiswaHapus))
	m.HandleFunc("POST /api/mahasiswa/hapus-massal", a.auth(admin, a.mahasiswaHapusMassal))

	m.HandleFunc("GET /api/import/template", a.auth(admin, a.importTemplate))
	m.HandleFunc("POST /api/import/preview", a.auth(admin, a.importPreview))
	m.HandleFunc("POST /api/import", a.auth(admin, a.importSimpan))

	m.HandleFunc("GET /api/kelas", a.auth(admin, a.kelasIndex))
	m.HandleFunc("POST /api/kelas", a.auth(admin, a.kelasBuat))
	m.HandleFunc("PUT /api/kelas/{id}", a.auth(admin, a.kelasUbah))
	m.HandleFunc("DELETE /api/kelas/{id}", a.auth(admin, a.kelasHapus))

	m.HandleFunc("GET /api/users", a.auth(admin, a.userIndex))
	m.HandleFunc("POST /api/users", a.auth(admin, a.userBuat))
	m.HandleFunc("PUT /api/users/{id}", a.auth(admin, a.userUbah))
	m.HandleFunc("DELETE /api/users/{id}", a.auth(admin, a.userHapus))
	m.HandleFunc("PATCH /api/users/{id}/status", a.auth(admin, a.userToggle))

	m.HandleFunc("GET /api/riwayat", a.auth(admin, a.riwayatIndex))

	m.HandleFunc("GET /api/export/rekap", a.auth(admin, a.exportRekap))
	m.HandleFunc("GET /api/export/sanksi", a.auth(admin, a.exportSanksi))
	m.HandleFunc("GET /api/export/poin", a.auth(admin, a.exportPoin))
	m.HandleFunc("GET /api/export/kelas/{id}", a.auth(admin, a.exportKelas))

	m.HandleFunc("/api/", func(w http.ResponseWriter, r *http.Request) { fail(w, 404, "Halaman tidak ditemukan.") })
}

// spa melayani file build React; rute yang tidak ada filenya jatuh ke index.html.
// os.Root mencegah path seperti "../" keluar dari folder build.
func spa(dir string) http.Handler {
	root, err := os.OpenRoot(dir)
	if err != nil {
		log.Fatal("WEB_DIST tidak bisa dibuka: ", err)
	}
	fsys := root.FS()
	files := http.FileServerFS(fsys)
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		name := strings.TrimPrefix(path.Clean("/"+r.URL.Path), "/")
		if st, err := fs.Stat(fsys, name); name == "" || err != nil || st.IsDir() {
			w.Header().Set("Cache-Control", "no-cache")
			http.ServeFileFS(w, r, fsys, "index.html")
			return
		}
		if strings.HasPrefix(name, "assets/") {
			w.Header().Set("Cache-Control", "public, max-age=31536000, immutable")
		}
		files.ServeHTTP(w, r)
	})
}

func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h := w.Header()
		h.Set("X-Content-Type-Options", "nosniff")
		h.Set("X-Frame-Options", "DENY")
		h.Set("Referrer-Policy", "same-origin")
		h.Set("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
		h.Set("Cross-Origin-Opener-Policy", "same-origin")
		if strings.HasPrefix(r.URL.Path, "/api/") {
			h.Set("Cache-Control", "no-store")
			h.Set("Content-Security-Policy", "default-src 'none'; frame-ancestors 'none'")
		} else {
			h.Set("Content-Security-Policy", "default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'")
		}
		if r.TLS != nil {
			h.Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains")
		}
		next.ServeHTTP(w, r)
	})
}
