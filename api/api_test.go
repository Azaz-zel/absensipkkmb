package main

// Tes integrasi + keamanan. Hanya jalan terhadap database yang namanya berakhiran
// "_test" supaya tidak pernah menyentuh data asli.

import (
	"bytes"
	"context"
	"encoding/json"
	"io"
	"mime/multipart"
	"net/http"
	"net/http/cookiejar"
	"net/http/httptest"
	"net/url"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/xuri/excelize/v2"
)

var (
	testApp   *App
	testSrv   *httptest.Server
	superPool *pgxpool.Pool
)

func envOr(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}

func TestMain(m *testing.M) {
	ctx := context.Background()
	appDSN := envOr("TEST_DATABASE_URL", "postgresql://app_api:app_api_local_dev@127.0.0.1:54322/absensi_test")
	superDSN := envOr("TEST_SUPER_DATABASE_URL", "postgresql://postgres:postgres@127.0.0.1:54322/absensi_test")
	for _, d := range []string{appDSN, superDSN} {
		if !strings.Contains(strings.SplitN(d, "?", 2)[0], "_test") {
			panic("tes hanya boleh memakai database *_test")
		}
	}
	var err error
	if superPool, err = pgxpool.New(ctx, superDSN); err != nil {
		panic(err)
	}
	pool, err := pgxpool.New(ctx, appDSN)
	if err != nil {
		panic(err)
	}
	testApp = &App{db: pool, secureCookie: false, origins: map[string]bool{"http://localhost:5173": true},
		limiter: newLoginLimiter(5, 15*time.Minute), ipLimiter: newLoginLimiter(1000, 15*time.Minute)}
	mux := http.NewServeMux()
	testApp.routes(mux)
	testSrv = httptest.NewServer(securityHeaders(mux))
	code := m.Run()
	testSrv.Close()
	os.Exit(code)
}

// seed mengosongkan database tes lalu mengisi data kecil yang dikenal.
func seed(t *testing.T) {
	t.Helper()
	ctx := context.Background()
	testApp.limiter = newLoginLimiter(5, 15*time.Minute)
	_, err := superPool.Exec(ctx, `truncate users, kelas, kelas_user, mahasiswa, sesi_absensi, sesi_kelas, absensi,
		pelanggaran, riwayat_perubahan, sesi_login, import_tertunda restart identity cascade`)
	must(t, err)
	h, _ := hashPassword("rahasia123")
	_, err = superPool.Exec(ctx, `
		insert into users (id, name, email, username, password, role, status) overriding system value values
		  (1,'Admin','admin@x.id','admin',$1,'admin','aktif'),
		  (2,'PJ A','pja@x.id','pja',$1,'pj','aktif'),
		  (3,'PJ B','pjb@x.id','pjb',$1,'pj','aktif'),
		  (4,'Manajer','mj@x.id','mj',$1,'manajer','aktif'),
		  (5,'Nonaktif','off@x.id','off',$1,'pj','nonaktif')`, h)
	must(t, err)
	_, err = superPool.Exec(ctx, `
		select setval(pg_get_serial_sequence('users','id'), 10);
		insert into kelas (id, nama_kelas, kategori) overriding system value values (1,'A','pagi'),(2,'B','pagi'),(3,'AA','malam');
		select setval(pg_get_serial_sequence('kelas','id'), 10);
		insert into kelas_user values (2,1),(3,2);
		insert into mahasiswa (id, nama, nim, jurusan, kelas_id) overriding system value values
		  (1,'Ani','001','SI',1),(2,'Budi','002','SI',1),(3,'Cici','003','TI',2),(4,'Dodi','004','TI',3);
		select setval(pg_get_serial_sequence('mahasiswa','id'), 10);
		insert into sesi_absensi (id, nama_sesi, tanggal) overriding system value values (1,'Sesi 1','2026-10-20');
		select setval(pg_get_serial_sequence('sesi_absensi','id'), 10);
		insert into sesi_kelas (sesi_absensi_id, kelas_id, status) values (1,1,'dibuka'),(1,2,'dibuka'),(1,3,'belum_dibuka');`)
	must(t, err)
}

func must(t *testing.T, err error) {
	t.Helper()
	if err != nil {
		t.Fatal(err)
	}
}

type client struct {
	t *testing.T
	h *http.Client
}

func newClient(t *testing.T) *client {
	jar, _ := cookiejar.New(nil)
	return &client{t, &http.Client{Jar: jar}}
}

func (c *client) do(method, path string, body any, hdr map[string]string) (int, M) {
	c.t.Helper()
	var rd io.Reader
	if s, ok := body.(string); ok {
		rd = strings.NewReader(s)
	} else if body != nil {
		b, _ := json.Marshal(body)
		rd = bytes.NewReader(b)
	}
	req, _ := http.NewRequest(method, testSrv.URL+path, rd)
	req.Header.Set("X-Requested-With", "absensi")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	for k, v := range hdr {
		if v == "" {
			req.Header.Del(k)
		} else {
			req.Header.Set(k, v)
		}
	}
	res, err := c.h.Do(req)
	must(c.t, err)
	defer res.Body.Close()
	out := M{}
	_ = json.NewDecoder(res.Body).Decode(&out)
	return res.StatusCode, out
}

func (c *client) get(path string) (int, M)            { return c.do("GET", path, nil, nil) }
func (c *client) send(m, path string, b any) (int, M) { return c.do(m, path, b, nil) }

func login(t *testing.T, user string) *client {
	c := newClient(t)
	if code, out := c.send("POST", "/api/login", M{"login": user, "password": "rahasia123"}); code != 200 {
		t.Fatalf("login %s: %d %v", user, code, out)
	}
	return c
}

func expect(t *testing.T, name string, got, want int) {
	t.Helper()
	if got != want {
		t.Errorf("%s: status %d, harusnya %d", name, got, want)
	}
}

// ---------------- Autentikasi ----------------

func TestLoginDanSesi(t *testing.T) {
	seed(t)
	c := newClient(t)
	code, _ := c.send("POST", "/api/login", M{"login": "admin", "password": "salah"})
	expect(t, "sandi salah", code, 422)
	code, wrongUser := c.send("POST", "/api/login", M{"login": "tidakada", "password": "salah"})
	expect(t, "user tidak ada", code, 422)
	_, wrongPass := c.send("POST", "/api/login", M{"login": "admin", "password": "salah"})
	if wrongUser["error"] != wrongPass["error"] {
		t.Error("pesan galat login membocorkan apakah akun ada")
	}
	code, _ = c.send("POST", "/api/login", M{"login": "off", "password": "rahasia123"})
	expect(t, "akun nonaktif", code, 403)

	// Email tidak peka huruf besar, username juga.
	c = newClient(t)
	code, _ = c.send("POST", "/api/login", M{"login": "ADMIN@X.ID", "password": "rahasia123"})
	expect(t, "login email kapital", code, 200)
	code, me := c.get("/api/me")
	expect(t, "me", code, 200)
	if me["user"].(map[string]any)["role"] != "admin" {
		t.Error("me salah")
	}
	if _, ok := me["user"].(map[string]any)["password"]; ok {
		t.Error("hash password bocor di /api/me")
	}

	// Token disimpan sebagai hash, bukan mentah.
	var raw string
	must(t, superPool.QueryRow(context.Background(), `select encode(token_hash,'hex') from sesi_login limit 1`).Scan(&raw))
	u, _ := http.NewRequest("GET", testSrv.URL, nil)
	for _, ck := range c.h.Jar.Cookies(u.URL) {
		if strings.Contains(raw, ck.Value) {
			t.Error("token sesi tersimpan mentah di database")
		}
	}

	code, _ = c.send("POST", "/api/logout", nil)
	expect(t, "logout", code, 204)
	code, _ = c.get("/api/me")
	expect(t, "me setelah logout", code, 401)
}

func TestCookieAman(t *testing.T) {
	seed(t)
	req, _ := http.NewRequest("POST", testSrv.URL+"/api/login", strings.NewReader(`{"login":"admin","password":"rahasia123"}`))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Requested-With", "absensi")
	res, err := http.DefaultClient.Do(req)
	must(t, err)
	res.Body.Close()
	sc := res.Header.Get("Set-Cookie")
	for _, want := range []string{"HttpOnly", "SameSite=Lax", "Path=/"} {
		if !strings.Contains(sc, want) {
			t.Errorf("cookie tanpa %s: %s", want, sc)
		}
	}
	if res.Header.Get("X-Frame-Options") != "DENY" || res.Header.Get("X-Content-Type-Options") != "nosniff" || res.Header.Get("Cache-Control") != "no-store" {
		t.Error("header keamanan tidak lengkap")
	}
}

func TestBatasPercobaanLogin(t *testing.T) {
	seed(t)
	c := newClient(t)
	for i := 0; i < 5; i++ {
		c.send("POST", "/api/login", M{"login": "pja", "password": "salah"})
	}
	code, _ := c.send("POST", "/api/login", M{"login": "pja", "password": "rahasia123"})
	expect(t, "terkunci setelah 5 gagal", code, 429)
	code, _ = c.send("POST", "/api/login", M{"login": "pjb", "password": "rahasia123"})
	expect(t, "akun lain tetap bisa", code, 200)
}

func TestSesiKedaluwarsaDanNonaktif(t *testing.T) {
	seed(t)
	c := login(t, "pja")
	_, err := superPool.Exec(context.Background(), `update sesi_login set last_active_at = now() - interval '3 hours'`)
	must(t, err)
	code, _ := c.get("/api/me")
	expect(t, "idle > 2 jam", code, 401)

	c = login(t, "pja")
	admin := login(t, "admin")
	code, _ = admin.send("PATCH", "/api/users/2/status", M{})
	expect(t, "nonaktifkan", code, 200)
	code, _ = c.get("/api/me")
	expect(t, "sesi PJ yang dinonaktifkan", code, 401)
}

func TestCSRF(t *testing.T) {
	seed(t)
	c := login(t, "admin")
	code, _ := c.do("POST", "/api/sesi", M{"nama": "X", "tanggal": "2026-10-27"}, map[string]string{"X-Requested-With": ""})
	expect(t, "tanpa header X-Requested-With", code, 403)
	code, _ = c.do("POST", "/api/sesi", M{"nama": "X", "tanggal": "2026-10-27"}, map[string]string{"Origin": "https://jahat.example"})
	expect(t, "origin asing", code, 403)
	code, _ = c.do("POST", "/api/sesi", "nama=X", map[string]string{"Content-Type": "application/x-www-form-urlencoded"})
	expect(t, "form biasa (bukan JSON)", code, 415)
	code, _ = c.do("POST", "/api/sesi", M{"nama": "X", "tanggal": "2026-10-27"}, map[string]string{"Origin": "http://localhost:5173"})
	expect(t, "origin sendiri", code, 201)
}

// ---------------- Otorisasi ----------------

func TestHakAksesPeran(t *testing.T) {
	seed(t)
	anon := newClient(t)
	pj, mj := login(t, "pja"), login(t, "mj")
	adminOnly := []string{"/api/sesi", "/api/mahasiswa", "/api/kelas", "/api/users", "/api/riwayat", "/api/export/rekap", "/api/export/sanksi", "/api/import/template"}
	for _, p := range adminOnly {
		c, _ := anon.get(p)
		expect(t, "anon "+p, c, 401)
		c, _ = pj.get(p)
		expect(t, "pj "+p, c, 403)
		c, _ = mj.get(p)
		expect(t, "manajer "+p, c, 403)
	}
	c, _ := mj.get("/api/absensi")
	expect(t, "manajer absensi", c, 403)
	c, _ = mj.get("/api/rekap/mahasiswa")
	expect(t, "manajer rekap", c, 403)
	c, _ = pj.get("/api/poin/cari?q=a")
	expect(t, "pj cari poin", c, 403)
	c, _ = pj.send("POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": "x", "poin": 1})
	expect(t, "pj catat pelanggaran", c, 403)
	c, _ = mj.send("PUT", "/api/users/4", M{"name": "Manajer", "email": "mj@x.id", "username": "mj", "role": "admin", "status": "aktif"})
	expect(t, "manajer jadikan diri admin", c, 403)
	c, _ = mj.send("DELETE", "/api/pelanggaran/1", nil)
	expect(t, "manajer hapus pelanggaran", c, 403)
}

func TestPJHanyaKelasnya(t *testing.T) {
	seed(t)
	pj := login(t, "pja") // pegang kelas 1 (A)
	c, _ := pj.get("/api/absensi/1/1")
	expect(t, "kelas sendiri", c, 200)
	c, _ = pj.get("/api/absensi/1/2")
	expect(t, "kelas orang lain", c, 403)
	c, _ = pj.send("PUT", "/api/absensi/1/2", M{"absensi": M{"3": "alpa"}})
	expect(t, "isi kelas orang lain", c, 403)
	c, _ = pj.get("/api/mahasiswa/3/detail")
	expect(t, "detail maba kelas lain", c, 403)

	// Id maba kelas lain disusupkan lewat kelas sendiri: harus diabaikan.
	c, out := pj.send("PUT", "/api/absensi/1/1", M{"absensi": M{"1": "hadir", "3": "alpa", "4": "alpa"}})
	expect(t, "simpan kelas sendiri", c, 200)
	var n int
	must(t, superPool.QueryRow(context.Background(), `select count(*) from absensi where mahasiswa_id in (3,4)`).Scan(&n))
	if n != 0 {
		t.Errorf("maba kelas lain ikut tersimpan lewat kelas PJ: %v", out)
	}

	_, rk := pj.get("/api/rekap/mahasiswa")
	for _, r := range rk["data"].([]any) {
		if r.(map[string]any)["kelas_id"].(float64) != 1 {
			t.Error("rekap PJ memuat maba dari kelas lain")
		}
	}
	_, idx := pj.get("/api/absensi")
	if len(idx["kelas"].([]any)) != 1 {
		t.Error("daftar absensi PJ memuat kelas lain")
	}
	_, f := pj.get("/api/filter")
	if len(f["kelas"].([]any)) != 1 {
		t.Error("filter PJ memuat kelas lain")
	}
}

func TestPJTidakBisaIsiKelasTertutup(t *testing.T) {
	seed(t)
	_, err := superPool.Exec(context.Background(), `update sesi_kelas set status='ditutup' where kelas_id=1`)
	must(t, err)
	pj := login(t, "pja")
	c, _ := pj.send("PUT", "/api/absensi/1/1", M{"absensi": M{"1": "hadir"}})
	expect(t, "pj isi kelas ditutup", c, 403)
	c, _ = login(t, "admin").send("PUT", "/api/absensi/1/1", M{"absensi": M{"1": "hadir"}})
	expect(t, "admin isi kelas ditutup", c, 200)
}

// ---------------- Validasi input & injeksi ----------------

func TestInjeksiDanValidasi(t *testing.T) {
	seed(t)
	a := login(t, "admin")
	payloads := []string{`' or 1=1 --`, `%`, `_`, `\`, `'; drop table users; --`, `") or ("1"="1`}
	for _, p := range payloads {
		code, out := a.get("/api/mahasiswa?q=" + urlq(p))
		expect(t, "cari "+p, code, 200)
		if n := out["total"].(float64); n != 0 {
			t.Errorf("pencarian %q mengembalikan %v baris (wildcard/injeksi lolos)", p, n)
		}
		code, _ = a.get("/api/rekap/mahasiswa?q=" + urlq(p) + "&sort=" + urlq(p) + "&poin=" + urlq(p) + "&kategori=" + urlq(p))
		expect(t, "rekap "+p, code, 200)
		code, _ = a.get("/api/riwayat?aksi=" + urlq(p) + "&tanggal=" + urlq(p))
		expect(t, "riwayat "+p, code, 200)
	}
	var n int
	must(t, superPool.QueryRow(context.Background(), `select count(*) from users`).Scan(&n))
	if n != 5 {
		t.Fatal("tabel users berubah setelah uji injeksi")
	}

	cases := []struct {
		m, p string
		b    any
		want int
	}{
		{"POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": "x", "poin": 21}, 422},
		{"POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": "x", "poin": 0}, 422},
		{"POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": "", "poin": 3}, 422},
		{"POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": strings.Repeat("a", 256), "poin": 3}, 422},
		{"POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": "x", "poin": 3, "dicatat_oleh": 99}, 400}, // field asing ditolak
		{"POST", "/api/mahasiswa/999/pelanggaran", M{"jenis": "x", "poin": 3}, 404},
		{"PUT", "/api/absensi/1/1", M{"absensi": M{"1": "bolos"}}, 200}, // status asing diabaikan
		{"POST", "/api/kelas", M{"nama": "ABC", "kategori": "malam"}, 422},
		{"POST", "/api/kelas", M{"nama": "A", "kategori": "siang"}, 422},
		{"POST", "/api/kelas", M{"nama": "A", "kategori": "pagi"}, 422}, // sudah ada
		{"POST", "/api/mahasiswa", M{"nama": "X", "nim": "001", "jurusan": "SI", "kelas_id": 1}, 422},
		{"POST", "/api/mahasiswa", M{"nama": "X", "nim": "999", "jurusan": "SI", "kelas_id": 999}, 422},
		{"POST", "/api/users", M{"name": "X", "email": "bukan-email", "username": "x1", "password": "12345678", "role": "pj", "status": "aktif"}, 422},
		{"POST", "/api/users", M{"name": "X", "email": "x@x.id", "username": "x1", "password": "pendek", "role": "pj", "status": "aktif"}, 422},
		{"POST", "/api/users", M{"name": "X", "email": "x@x.id", "username": "x1", "password": "12345678", "role": "superadmin", "status": "aktif"}, 422},
		{"POST", "/api/users", M{"name": "X", "email": "ADMIN@x.id", "username": "x1", "password": "12345678", "role": "pj", "status": "aktif"}, 422},
		{"PUT", "/api/users/1", M{"name": "Admin", "email": "admin@x.id", "username": "admin", "role": "pj", "status": "aktif"}, 422}, // turunkan diri sendiri
		{"DELETE", "/api/users/1", nil, 422},
		{"PATCH", "/api/users/1/status", M{}, 422},
		{"POST", "/api/sesi/1/status", M{"aksi": "hapus", "kelas_ids": []int{1}}, 422},
		{"GET", "/api/mahasiswa/abc/detail", nil, 404},
		{"GET", "/api/mahasiswa/-1/detail", nil, 404},
		{"POST", "/api/mahasiswa/hapus-massal", M{"konfirmasi": "hapus semua data"}, 422},
		{"POST", "/api/mahasiswa/hapus-massal", M{"kelas_id": 1, "konfirmasi": "B"}, 422},
		{"POST", "/api/import", M{"token": "' or 1=1 --"}, 410},
	}
	for _, c := range cases {
		code, out := a.send(c.m, c.p, c.b)
		if code != c.want {
			t.Errorf("%s %s %v: %d %v, harusnya %d", c.m, c.p, c.b, code, out, c.want)
		}
	}
	code, _ := a.do("POST", "/api/sesi", `{"nama":"`+strings.Repeat("x", 2<<20)+`"}`, nil)
	expect(t, "body > 1 MB", code, 400)
}

func urlq(s string) string { return url.QueryEscape(s) }

// ---------------- Alur bisnis ----------------

func TestTutupKelasMenandaiAlpaDanRiwayat(t *testing.T) {
	seed(t)
	a := login(t, "admin")
	code, _ := a.send("PUT", "/api/absensi/1/1", M{"absensi": M{"1": "hadir"}})
	expect(t, "isi", code, 200)
	code, out := a.send("POST", "/api/sesi/1/status", M{"aksi": "tutup", "kelas_ids": []int{1}})
	expect(t, "tutup", code, 200)
	var hadir, alpa int
	must(t, superPool.QueryRow(context.Background(), `select count(*) filter (where status='hadir'), count(*) filter (where status='alpa') from absensi where kelas_id=1`).Scan(&hadir, &alpa))
	if hadir != 1 || alpa != 1 {
		t.Errorf("setelah tutup: hadir=%d alpa=%d (%v)", hadir, alpa, out)
	}
	// Menutup lagi tidak menduplikasi.
	a.send("POST", "/api/sesi/1/status", M{"aksi": "tutup", "kelas_ids": []int{1}})
	must(t, superPool.QueryRow(context.Background(), `select count(*) from absensi where kelas_id=1`).Scan(&alpa))
	if alpa != 2 {
		t.Errorf("tutup dua kali menghasilkan %d baris", alpa)
	}
	_, rw := a.get("/api/riwayat")
	if rw["total"].(float64) < 2 {
		t.Error("buka/tutup tidak tercatat di riwayat")
	}
}

func TestPoinDanSanksi(t *testing.T) {
	seed(t)
	cases := map[int]string{0: "bersih", 1: "ringan", 5: "ringan", 6: "sedang", 9: "sedang", 10: "berat", 19: "berat", 20: "merah", 40: "merah"}
	for p, lv := range cases {
		if got := sanksiUntuk(p).Level; got != lv {
			t.Errorf("sanksi %d = %s, harusnya %s", p, got, lv)
		}
	}
	if poinAbsensi(1, 1, 2) != 16 {
		t.Error("rumus poin absensi salah")
	}
	_, err := superPool.Exec(context.Background(), `insert into absensi (mahasiswa_id,kelas_id,sesi_absensi_id,status) values (1,1,1,'alpa')`)
	must(t, err)
	mj := login(t, "mj")
	code, _ := mj.send("POST", "/api/mahasiswa/1/pelanggaran", M{"jenis": "Telat", "poin": 6})
	expect(t, "manajer catat", code, 201)
	code, d := mj.get("/api/mahasiswa/1/detail")
	expect(t, "detail", code, 200)
	m := d["mahasiswa"].(map[string]any)
	if m["total_poin"].(float64) != 11 || m["sanksi"].(map[string]any)["level"] != "berat" {
		t.Errorf("total poin salah: %v", m)
	}
	if len(d["kehadiran"].([]any)) != 0 {
		t.Error("manajer tidak perlu melihat rincian kehadiran")
	}
}

func TestRiwayatTidakBisaDiubah(t *testing.T) {
	seed(t)
	ctx := context.Background()
	_, err := testApp.db.Exec(ctx, `insert into riwayat_perubahan (nama_pelaku, aksi, keterangan) values ('x','y','z')`)
	must(t, err)
	if _, err := testApp.db.Exec(ctx, `update riwayat_perubahan set keterangan='ubah'`); err == nil {
		t.Error("app_api bisa mengubah riwayat")
	}
	if _, err := testApp.db.Exec(ctx, `delete from riwayat_perubahan`); err == nil {
		t.Error("app_api bisa menghapus riwayat")
	}
}

func TestRoleAnonTidakBisaBacaTabel(t *testing.T) {
	ctx := context.Background()
	tx, err := superPool.Begin(ctx)
	must(t, err)
	defer tx.Rollback(ctx)
	_, err = tx.Exec(ctx, `set local role anon`)
	must(t, err)
	if _, err := tx.Exec(ctx, `select * from users`); err == nil {
		t.Error("role anon (PostgREST publik) bisa membaca tabel users")
	}
}

func TestImportExcel(t *testing.T) {
	seed(t)
	a := login(t, "admin")
	f := excelize.NewFile()
	rows := [][]any{{"nama", "nim", "jurusan", "kelas"}, {"Eka", "010", "SI", "a"}, {"Fani", "001", "SI", "A"}, {"Gita", "011", "SI", "Z"}, {"=HYPERLINK(\"http://x\")", "012", "SI", "B"}}
	for i, r := range rows {
		cell, _ := excelize.CoordinatesToCellName(1, i+1)
		must(t, f.SetSheetRow("Sheet1", cell, &r))
	}
	var buf bytes.Buffer
	must(t, f.Write(&buf))
	var body bytes.Buffer
	w := multipart.NewWriter(&body)
	fw, _ := w.CreateFormFile("file", "data.xlsx")
	fw.Write(buf.Bytes())
	w.Close()
	code, pv := a.do("POST", "/api/import/preview", body.String(), map[string]string{"Content-Type": w.FormDataContentType()})
	expect(t, "preview", code, 200)
	if pv["valid"].(float64) != 2 || pv["error"].(float64) != 2 {
		t.Fatalf("hasil preview salah: %v", pv)
	}
	// Token milik admin lain tidak bisa dipakai PJ (rute admin) maupun dipakai ulang.
	code, _ = a.send("POST", "/api/import", M{"token": pv["token"]})
	expect(t, "simpan import", code, 200)
	code, _ = a.send("POST", "/api/import", M{"token": pv["token"]})
	expect(t, "token dipakai ulang", code, 410)
	var n int
	must(t, superPool.QueryRow(context.Background(), `select count(*) from mahasiswa`).Scan(&n))
	if n != 6 {
		t.Errorf("jumlah maba setelah import %d, harusnya 6", n)
	}

	code, _ = a.do("POST", "/api/import/preview", "bukan multipart", map[string]string{"Content-Type": "text/plain"})
	expect(t, "upload bukan file", code, 422)
}

func TestHapusMassalSoftDelete(t *testing.T) {
	seed(t)
	a := login(t, "admin")
	code, _ := a.send("POST", "/api/mahasiswa/hapus-massal", M{"kelas_id": 1, "konfirmasi": "A"})
	expect(t, "hapus kelas A", code, 200)
	var aktif, semua int
	must(t, superPool.QueryRow(context.Background(), `select count(*) filter (where deleted_at is null), count(*) from mahasiswa`).Scan(&aktif, &semua))
	if aktif != 2 || semua != 4 {
		t.Errorf("aktif=%d semua=%d", aktif, semua)
	}
	// NIM yang dihapus boleh dipakai lagi.
	code, _ = a.send("POST", "/api/mahasiswa", M{"nama": "Ani baru", "nim": "001", "jurusan": "SI", "kelas_id": 2})
	expect(t, "pakai ulang NIM", code, 201)
	// Kelas yang masih punya maba (walau terhapus) tidak bisa dihapus.
	code, _ = a.send("DELETE", "/api/kelas/1", nil)
	expect(t, "hapus kelas berisi", code, 409)
}

func TestGantiSandiMengakhiriSesiLain(t *testing.T) {
	seed(t)
	pj := login(t, "pja")
	a := login(t, "admin")
	code, _ := a.send("PUT", "/api/users/2", M{"name": "PJ A", "email": "pja@x.id", "username": "pja", "password": "sandibaru99", "role": "pj", "status": "aktif", "kelas_ids": []int{1}})
	expect(t, "ganti sandi", code, 200)
	code, _ = pj.get("/api/me")
	expect(t, "sesi lama setelah ganti sandi", code, 401)
}
