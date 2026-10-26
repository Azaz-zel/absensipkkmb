package main

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"net"
	"net/http"
	"strings"
	"sync"
	"time"

	"golang.org/x/crypto/bcrypt"
)

const (
	idleTimeout     = 2 * time.Hour  // sama dengan SESSION_LIFETIME Laravel (120 menit)
	absoluteTimeout = 12 * time.Hour // sesi login paling lama satu hari kerja
	bcryptCost      = 12
)

type User struct {
	ID       int64   `json:"id"`
	Name     string  `json:"name"`
	Email    string  `json:"email"`
	Username *string `json:"username"`
	Role     string  `json:"role"`
	Status   string  `json:"status"`
}

func (a *App) cookieName() string {
	if a.secureCookie {
		return "__Host-sid"
	}
	return "sid"
}

// Hash pembanding supaya waktu respons login sama walau akunnya tidak ada.
var dummyHash, _ = bcrypt.GenerateFromPassword([]byte("bukan-password-siapa-pun"), bcryptCost)

func hashPassword(p string) (string, error) {
	h, err := bcrypt.GenerateFromPassword([]byte(p), bcryptCost)
	return string(h), err
}

func hashToken(tok string) []byte {
	s := sha256.Sum256([]byte(tok))
	return s[:]
}

func clientIP(r *http.Request) string {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return host
}

func (a *App) login(w http.ResponseWriter, r *http.Request) {
	if !a.sameOrigin(w, r) {
		return
	}
	var in struct {
		Login    string `json:"login"`
		Password string `json:"password"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	in.Login = strings.TrimSpace(in.Login)
	if in.Login == "" || in.Password == "" || len(in.Login) > 255 || len(in.Password) > 72 {
		fail(w, 422, "Isi email/username dan kata sandi.")
		return
	}
	// Batas per IP lebih longgar karena banyak PJ bisa masuk dari wifi kampus yang sama.
	keyIP, keyLogin := clientIP(r), strings.ToLower(in.Login)
	if !a.ipLimiter.allow(keyIP) || !a.limiter.allow(keyLogin) {
		fail(w, 429, "Terlalu banyak percobaan masuk. Tunggu 15 menit lalu coba lagi.")
		return
	}

	field := "lower(username)"
	if validEmail(in.Login) {
		field = "lower(email)"
	}
	var u User
	var hash string
	err := a.db.QueryRow(r.Context(),
		`select id, name, email, username, role, status, password from users where `+field+` = lower($1) limit 1`, in.Login).
		Scan(&u.ID, &u.Name, &u.Email, &u.Username, &u.Role, &u.Status, &hash)
	if err != nil && !notFound(err) {
		serverError(w, err)
		return
	}
	if notFound(err) {
		_ = bcrypt.CompareHashAndPassword(dummyHash, []byte(in.Password))
		a.ipLimiter.fail(keyIP)
		a.limiter.fail(keyLogin)
		fail(w, 422, "Email/username atau kata sandi salah.")
		return
	}
	if bcrypt.CompareHashAndPassword([]byte(hash), []byte(in.Password)) != nil {
		a.ipLimiter.fail(keyIP)
		a.limiter.fail(keyLogin)
		fail(w, 422, "Email/username atau kata sandi salah.")
		return
	}
	if u.Status != "aktif" {
		fail(w, 403, "Akun kamu dinonaktifkan. Hubungi admin panitia.")
		return
	}
	a.limiter.reset(keyLogin)

	buf := make([]byte, 32)
	if _, err := rand.Read(buf); err != nil {
		serverError(w, err)
		return
	}
	tok := base64.RawURLEncoding.EncodeToString(buf)
	exp := time.Now().Add(absoluteTimeout)
	if _, err := a.db.Exec(r.Context(), `insert into sesi_login (token_hash, user_id, expires_at) values ($1,$2,$3)`,
		hashToken(tok), u.ID, exp); err != nil {
		serverError(w, err)
		return
	}
	http.SetCookie(w, &http.Cookie{
		Name: a.cookieName(), Value: tok, Path: "/", Expires: exp,
		HttpOnly: true, Secure: a.secureCookie, SameSite: http.SameSiteLaxMode,
	})
	writeJSON(w, 200, u)
}

func (a *App) logout(w http.ResponseWriter, r *http.Request) {
	if !a.sameOrigin(w, r) {
		return
	}
	if c, err := r.Cookie(a.cookieName()); err == nil {
		_, _ = a.db.Exec(r.Context(), `delete from sesi_login where token_hash = $1`, hashToken(c.Value))
	}
	http.SetCookie(w, &http.Cookie{Name: a.cookieName(), Value: "", Path: "/", MaxAge: -1,
		HttpOnly: true, Secure: a.secureCookie, SameSite: http.SameSiteLaxMode})
	w.WriteHeader(204)
}

func (a *App) me(w http.ResponseWriter, r *http.Request, u *User) {
	var kelas []M
	rows, err := a.db.Query(r.Context(), `select k.id, k.nama_kelas, k.kategori from kelas k
		join kelas_user ku on ku.kelas_id = k.id where ku.user_id = $1 order by k.kategori, k.nama_kelas`, u.ID)
	if err != nil {
		serverError(w, err)
		return
	}
	for rows.Next() {
		var id int64
		var nama, kat string
		if err := rows.Scan(&id, &nama, &kat); err != nil {
			serverError(w, err)
			return
		}
		kelas = append(kelas, M{"id": id, "nama": nama, "kategori": kat})
	}
	writeJSON(w, 200, M{"user": u, "kelas": kelas})
}

// sameOrigin menolak permintaan pengubah data dari situs lain (CSRF).
// Header X-Requested-With memaksa preflight CORS yang tidak pernah kita izinkan,
// dan Origin (bila dikirim browser) harus termasuk daftar yang diizinkan.
func (a *App) sameOrigin(w http.ResponseWriter, r *http.Request) bool {
	if r.Method == http.MethodGet || r.Method == http.MethodHead {
		return true
	}
	o := r.Header.Get("Origin")
	if r.Header.Get("X-Requested-With") != "absensi" || (o != "" && !a.origins[o]) {
		fail(w, 403, "Permintaan ditolak.")
		return false
	}
	return true
}

type handler func(http.ResponseWriter, *http.Request, *User)

// auth memastikan sesi login masih berlaku, akun aktif, dan perannya diizinkan.
// roles nil berarti semua peran boleh.
func (a *App) auth(roles []string, h handler) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !a.sameOrigin(w, r) {
			return
		}
		c, err := r.Cookie(a.cookieName())
		if err != nil || c.Value == "" {
			fail(w, 401, "Silakan masuk dulu.")
			return
		}
		var u User
		err = a.db.QueryRow(r.Context(), `
			update sesi_login s set last_active_at = now()
			from users u
			where s.token_hash = $1 and u.id = s.user_id and s.expires_at > now()
			  and s.last_active_at > now() - $2::interval and u.status = 'aktif'
			returning u.id, u.name, u.email, u.username, u.role, u.status`,
			hashToken(c.Value), idleTimeout.String()).
			Scan(&u.ID, &u.Name, &u.Email, &u.Username, &u.Role, &u.Status)
		if notFound(err) {
			_, _ = a.db.Exec(r.Context(), `delete from sesi_login where token_hash = $1`, hashToken(c.Value))
			fail(w, 401, "Sesi kamu sudah berakhir. Silakan masuk lagi.")
			return
		}
		if err != nil {
			serverError(w, err)
			return
		}
		if roles != nil && !oneOf(u.Role, roles...) {
			fail(w, 403, "Kamu tidak punya akses ke halaman ini.")
			return
		}
		h(w, r, &u)
	}
}

// kelasDiizinkan: admin boleh semua kelas, PJ hanya kelas yang ditugaskan ke akunnya.
func (a *App) kelasDiizinkan(ctx context.Context, u *User, kelasID int64) (bool, error) {
	if u.Role == "admin" {
		return true, nil
	}
	if u.Role != "pj" {
		return false, nil
	}
	var ok bool
	err := a.db.QueryRow(ctx, `select exists(select 1 from kelas_user where user_id=$1 and kelas_id=$2)`, u.ID, kelasID).Scan(&ok)
	return ok, err
}

// loginLimiter membatasi percobaan login gagal per IP dan per akun.
// shortcut: disimpan di memori satu proses; pindah ke tabel/Redis kalau API dijalankan lebih dari satu instance.
type loginLimiter struct {
	mu     sync.Mutex
	max    int
	window time.Duration
	hits   map[string][]time.Time
}

func newLoginLimiter(max int, window time.Duration) *loginLimiter {
	return &loginLimiter{max: max, window: window, hits: map[string][]time.Time{}}
}

func (l *loginLimiter) recent(key string) []time.Time {
	cut := time.Now().Add(-l.window)
	h := l.hits[key]
	i := 0
	for i < len(h) && h[i].Before(cut) {
		i++
	}
	h = h[i:]
	if len(h) == 0 {
		delete(l.hits, key)
	} else {
		l.hits[key] = h
	}
	return h
}

func (l *loginLimiter) allow(key string) bool {
	l.mu.Lock()
	defer l.mu.Unlock()
	return len(l.recent(key)) < l.max
}

func (l *loginLimiter) fail(keys ...string) {
	l.mu.Lock()
	defer l.mu.Unlock()
	for _, k := range keys {
		l.hits[k] = append(l.recent(k), time.Now())
	}
}

func (l *loginLimiter) reset(key string) {
	l.mu.Lock()
	defer l.mu.Unlock()
	delete(l.hits, key)
}
