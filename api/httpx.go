package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/mail"
	"strconv"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgconn"
)

type M = map[string]any

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func fail(w http.ResponseWriter, status int, msg string) {
	writeJSON(w, status, M{"error": msg})
}

// serverError mencatat detail ke log dan hanya mengirim pesan umum ke klien.
func serverError(w http.ResponseWriter, err error) {
	log.Printf("error: %v", err)
	fail(w, 500, "Terjadi kesalahan di server. Coba lagi sebentar lagi.")
}

// Errs adalah kumpulan pesan validasi per field.
type Errs map[string]string

func (e Errs) send(w http.ResponseWriter) bool {
	if len(e) == 0 {
		return false
	}
	writeJSON(w, 422, M{"error": "Periksa lagi isian yang ditandai.", "fields": e})
	return true
}

func readJSON(w http.ResponseWriter, r *http.Request, dst any) bool {
	if !strings.HasPrefix(r.Header.Get("Content-Type"), "application/json") {
		fail(w, 415, "Format permintaan harus JSON.")
		return false
	}
	dec := json.NewDecoder(io.LimitReader(r.Body, 1<<20))
	dec.DisallowUnknownFields()
	if err := dec.Decode(dst); err != nil {
		fail(w, 400, "Isi permintaan tidak bisa dibaca.")
		return false
	}
	return true
}

func pathID(w http.ResponseWriter, r *http.Request, name string) (int64, bool) {
	id, err := strconv.ParseInt(r.PathValue(name), 10, 64)
	if err != nil || id <= 0 {
		fail(w, 404, "Data tidak ditemukan.")
		return 0, false
	}
	return id, true
}

// qInt membaca angka positif dari query string; selain itu dianggap kosong (0).
func qInt(r *http.Request, key string) int64 {
	n, err := strconv.ParseInt(r.URL.Query().Get(key), 10, 64)
	if err != nil || n < 0 {
		return 0
	}
	return n
}

func qStr(r *http.Request, key string) string {
	s := strings.TrimSpace(r.URL.Query().Get(key))
	if len(s) > 100 {
		s = s[:100]
	}
	return s
}

func page(r *http.Request) int64 {
	if p := qInt(r, "page"); p > 0 && p < 10000 {
		return p
	}
	return 1
}

// likePattern meloloskan karakter wildcard LIKE supaya pencarian dibaca apa adanya.
func likePattern(s string) string {
	s = strings.NewReplacer(`\`, `\\`, `%`, `\%`, `_`, `\_`).Replace(s)
	return "%" + s + "%"
}

// where menyusun klausa WHERE dengan parameter bernomor ($1, $2, ...).
type where struct {
	conds []string
	args  []any
}

// add menambah kondisi; setiap "?" diganti nomor parameter berikutnya.
func (q *where) add(cond string, vals ...any) {
	for _, v := range vals {
		q.args = append(q.args, v)
		cond = strings.Replace(cond, "?", "$"+strconv.Itoa(len(q.args)), 1)
	}
	q.conds = append(q.conds, cond)
}

func (q *where) arg(v any) string {
	q.args = append(q.args, v)
	return "$" + strconv.Itoa(len(q.args))
}

func (q *where) sql() string {
	if len(q.conds) == 0 {
		return "true"
	}
	return strings.Join(q.conds, " and ")
}

func isUnique(err error) bool {
	var pg *pgconn.PgError
	return errors.As(err, &pg) && pg.Code == "23505"
}

func notFound(err error) bool { return errors.Is(err, pgx.ErrNoRows) }

type execer interface {
	Exec(ctx context.Context, sql string, args ...any) (pgconn.CommandTag, error)
}

// catat menulis jejak ke riwayat_perubahan. Nama pelaku disimpan sebagai teks
// supaya tetap terbaca walau akunnya nanti dihapus.
func catat(ctx context.Context, db execer, u *User, aksi, ket string) error {
	_, err := db.Exec(ctx, `insert into riwayat_perubahan (user_id, nama_pelaku, aksi, keterangan) values ($1,$2,$3,$4)`,
		u.ID, u.Name, aksi, ket)
	return err
}

func inTx(ctx context.Context, a *App, fn func(pgxTx) error) error {
	tx, err := a.db.Begin(ctx)
	if err != nil {
		return err
	}
	defer tx.Rollback(ctx)
	if err := fn(tx); err != nil {
		return err
	}
	return tx.Commit(ctx)
}

func validEmail(s string) bool {
	a, err := mail.ParseAddress(s)
	return err == nil && a.Address == s && len(s) <= 255
}

func textLen(s string, min, max int) bool {
	n := len([]rune(s))
	return n >= min && n <= max
}

func parseDate(s string) (time.Time, bool) {
	t, err := time.Parse("2006-01-02", s)
	return t, err == nil
}

func oneOf(s string, opts ...string) bool {
	for _, o := range opts {
		if s == o {
			return true
		}
	}
	return false
}

func tglID(t time.Time) string {
	bulan := []string{"Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"}
	return fmt.Sprintf("%d %s %d", t.Day(), bulan[t.Month()-1], t.Year())
}

type pgxTx = pgx.Tx

var sprintf = fmt.Sprintf

func replaceWhere(sql, cond string) string { return strings.Replace(sql, "{{where}}", cond, 1) }

var errNotFound = errors.New("not found")
