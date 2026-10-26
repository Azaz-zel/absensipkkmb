// migrasi menyalin data dari database MySQL Laravel lama ke Postgres (Supabase).
// Sumber hanya dibaca. Tujuan harus kosong; tabel diisi dalam satu transaksi.
//
//	go run ./cmd/migrasi -mysql "root:@tcp(127.0.0.1:3306)/absensipkkmb" -pg "postgresql://postgres:postgres@127.0.0.1:54322/postgres"
package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"log"
	"net/url"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/jackc/pgx/v5"
)

type tabel struct {
	nama  string
	kolom []string
	sql   string // query sumber (MySQL), kolom harus sama urutannya
}

var tabelList = []tabel{
	{"users", []string{"id", "name", "email", "username", "password", "role", "status", "created_at", "updated_at"},
		`select id, name, lower(email), username, password, role, status, coalesce(created_at, now()), coalesce(updated_at, now()) from users`},
	{"kelas", []string{"id", "nama_kelas", "kategori", "kapasitas", "created_at", "updated_at"},
		`select id, upper(nama_kelas), kategori, kapasitas, coalesce(created_at, now()), coalesce(updated_at, now()) from kelas`},
	{"kelas_user", []string{"user_id", "kelas_id"}, `select distinct user_id, kelas_id from kelas_user`},
	{"mahasiswa", []string{"id", "nama", "nim", "jurusan", "kelas_id", "created_at", "updated_at", "deleted_at"},
		`select id, nama, nim, jurusan, kelas_id, coalesce(created_at, now()), coalesce(updated_at, now()), deleted_at from mahasiswa`},
	{"sesi_absensi", []string{"id", "nama_sesi", "tanggal", "created_by", "created_at", "updated_at"},
		`select id, nama_sesi, tanggal, created_by, coalesce(created_at, now()), coalesce(updated_at, now()) from sesi_absensi`},
	{"sesi_kelas", []string{"id", "sesi_absensi_id", "kelas_id", "status", "opened_at", "closed_at", "opened_by", "closed_by", "created_at", "updated_at"},
		`select id, sesi_absensi_id, kelas_id, status, opened_at, closed_at, opened_by, closed_by, coalesce(created_at, now()), coalesce(updated_at, now()) from sesi_kelas`},
	{"absensi", []string{"id", "mahasiswa_id", "kelas_id", "sesi_absensi_id", "status", "recorded_by", "created_at", "updated_at"},
		`select id, mahasiswa_id, kelas_id, sesi_absensi_id, status, recorded_by, coalesce(created_at, now()), coalesce(updated_at, now()) from absensi`},
	{"pelanggaran", []string{"id", "mahasiswa_id", "jenis", "poin", "dicatat_oleh", "created_at", "updated_at"},
		`select id, mahasiswa_id, jenis, poin, dicatat_oleh, coalesce(created_at, now()), coalesce(updated_at, now()) from pelanggaran`},
	{"riwayat_perubahan", []string{"id", "user_id", "nama_pelaku", "aksi", "keterangan", "created_at"},
		`select id, user_id, nama_pelaku, aksi, keterangan, coalesce(created_at, now()) from riwayat_perubahan`},
}

func main() {
	myDSN := flag.String("mysql", "root:@tcp(127.0.0.1:3306)/absensipkkmb", "DSN MySQL sumber")
	pgDSN := flag.String("pg", "", "URL Postgres tujuan (pakai user postgres agar bisa set sequence)")
	tz := flag.String("tz", "Asia/Makassar", "zona waktu yang dipakai Laravel (APP_TIMEZONE)")
	flag.Parse()
	if *pgDSN == "" {
		log.Fatal("-pg wajib diisi")
	}
	loc, err := time.LoadLocation(*tz)
	if err != nil {
		log.Fatal(err)
	}
	ctx := context.Background()
	my, err := sql.Open("mysql", *myDSN+"?parseTime=true&loc="+url.QueryEscape(loc.String()))
	if err != nil {
		log.Fatal(err)
	}
	defer my.Close()
	pg, err := pgx.Connect(ctx, *pgDSN)
	if err != nil {
		log.Fatal(err)
	}
	defer pg.Close(ctx)

	var ada int
	if err := pg.QueryRow(ctx, `select (select count(*) from users) + (select count(*) from mahasiswa)`).Scan(&ada); err != nil {
		log.Fatal(err)
	}
	if ada > 0 {
		log.Fatal("database tujuan sudah berisi data; migrasi dibatalkan supaya tidak menimpa apa pun")
	}

	tx, err := pg.Begin(ctx)
	if err != nil {
		log.Fatal(err)
	}
	defer tx.Rollback(ctx)
	for _, t := range tabelList {
		rows, err := my.QueryContext(ctx, t.sql)
		if err != nil {
			log.Fatalf("%s: %v", t.nama, err)
		}
		var data [][]any
		for rows.Next() {
			vals := make([]any, len(t.kolom))
			ptr := make([]any, len(vals))
			for i := range vals {
				ptr[i] = &vals[i]
			}
			if err := rows.Scan(ptr...); err != nil {
				log.Fatalf("%s: %v", t.nama, err)
			}
			for i, v := range vals {
				if b, ok := v.([]byte); ok { // MySQL mengembalikan teks sebagai []byte
					vals[i] = string(b)
				}
			}
			data = append(data, vals)
		}
		rows.Close()
		n, err := tx.CopyFrom(ctx, pgx.Identifier{t.nama}, t.kolom, pgx.CopyFromRows(data))
		if err != nil {
			log.Fatalf("%s: %v", t.nama, err)
		}
		fmt.Printf("%-18s %6d baris\n", t.nama, n)
		if t.kolom[0] == "id" {
			if _, err := tx.Exec(ctx, fmt.Sprintf(`select setval(pg_get_serial_sequence('%s','id'), coalesce((select max(id) from %s), 0) + 1, false)`, t.nama, t.nama)); err != nil {
				log.Fatalf("%s sequence: %v", t.nama, err)
			}
		}
	}
	// Kelas yang belum punya baris sesi_kelas di suatu sesi dilengkapi sebagai belum_dibuka.
	if _, err := tx.Exec(ctx, `insert into sesi_kelas (sesi_absensi_id, kelas_id) select s.id, k.id from sesi_absensi s cross join kelas k on conflict do nothing`); err != nil {
		log.Fatal(err)
	}
	if err := tx.Commit(ctx); err != nil {
		log.Fatal(err)
	}
	fmt.Println("selesai")
}
