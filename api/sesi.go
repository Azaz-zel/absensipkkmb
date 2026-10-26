package main

import (
	"net/http"
	"strings"
	"time"
)

type SesiRingkas struct {
	ID         int64  `json:"id"`
	Nama       string `json:"nama"`
	Tanggal    string `json:"tanggal"`
	Dibuka     int    `json:"dibuka"`
	Ditutup    int    `json:"ditutup"`
	BelumBuka  int    `json:"belum_dibuka"`
	StatusSesi string `json:"status"`
}

func statusSesi(dibuka, ditutup int) string {
	switch {
	case dibuka > 0:
		return "dibuka"
	case ditutup > 0:
		return "ditutup"
	}
	return "belum_dibuka"
}

const sesiRingkasSQL = `select s.id, s.nama_sesi, s.tanggal,
  count(*) filter (where sk.status = 'dibuka')::int,
  count(*) filter (where sk.status = 'ditutup')::int,
  count(*) filter (where sk.status = 'belum_dibuka')::int
from sesi_absensi s left join sesi_kelas sk on sk.sesi_absensi_id = s.id
group by s.id order by s.tanggal desc, s.id desc`

func (a *App) daftarSesi(r *http.Request) ([]SesiRingkas, error) {
	rows, err := a.db.Query(r.Context(), sesiRingkasSQL)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []SesiRingkas{}
	for rows.Next() {
		var s SesiRingkas
		var tgl time.Time
		if err := rows.Scan(&s.ID, &s.Nama, &tgl, &s.Dibuka, &s.Ditutup, &s.BelumBuka); err != nil {
			return nil, err
		}
		s.Tanggal = tgl.Format("2006-01-02")
		s.StatusSesi = statusSesi(s.Dibuka, s.Ditutup)
		out = append(out, s)
	}
	return out, rows.Err()
}

func (a *App) sesiIndex(w http.ResponseWriter, r *http.Request, u *User) {
	list, err := a.daftarSesi(r)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"data": list})
}

type sesiIn struct {
	Nama    string `json:"nama"`
	Tanggal string `json:"tanggal"`
}

func (s *sesiIn) validate() Errs {
	e := Errs{}
	s.Nama = strings.TrimSpace(s.Nama)
	if !textLen(s.Nama, 1, 255) {
		e["nama"] = "Isi nama sesi."
	}
	if _, ok := parseDate(s.Tanggal); !ok {
		e["tanggal"] = "Pilih tanggal sesi."
	}
	return e
}

func (a *App) sesiBuat(w http.ResponseWriter, r *http.Request, u *User) {
	var in sesiIn
	if !readJSON(w, r, &in) || in.validate().send(w) {
		return
	}
	var id int64
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		if err := tx.QueryRow(r.Context(), `insert into sesi_absensi (nama_sesi, tanggal, created_by) values ($1,$2,$3) returning id`,
			in.Nama, in.Tanggal, u.ID).Scan(&id); err != nil {
			return err
		}
		_, err := tx.Exec(r.Context(), `insert into sesi_kelas (sesi_absensi_id, kelas_id) select $1, id from kelas`, id)
		return err
	})
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 201, M{"id": id, "message": "Sesi " + in.Nama + " dibuat."})
}

func (a *App) sesiUbah(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in sesiIn
	if !readJSON(w, r, &in) || in.validate().send(w) {
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var lama string
		if err := tx.QueryRow(r.Context(), `select nama_sesi from sesi_absensi where id=$1 for update`, id).Scan(&lama); err != nil {
			return err
		}
		if _, err := tx.Exec(r.Context(), `update sesi_absensi set nama_sesi=$2, tanggal=$3, updated_at=now() where id=$1`, id, in.Nama, in.Tanggal); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "sesi.ubah", sprintf("%s menjadi %s (%s)", lama, in.Nama, in.Tanggal))
	})
	if notFound(err) {
		fail(w, 404, "Sesi tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Sesi diperbarui."})
}

func (a *App) sesiHapus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var nama string
		var tgl time.Time
		var jumlah int
		if err := tx.QueryRow(r.Context(), `select nama_sesi, tanggal, (select count(*) from absensi where sesi_absensi_id=$1)::int
			from sesi_absensi where id=$1`, id).Scan(&nama, &tgl, &jumlah); err != nil {
			return err
		}
		if _, err := tx.Exec(r.Context(), `delete from sesi_absensi where id=$1`, id); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "sesi.hapus", sprintf("%s (%s) beserta %d data absensi", nama, tgl.Format("2006-01-02"), jumlah))
	})
	if notFound(err) {
		fail(w, 404, "Sesi tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Sesi dihapus."})
}

// sesiShow: semua kelas dalam satu sesi beserta status, PJ, dan jumlah yang sudah diabsen.
func (a *App) sesiShow(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var s SesiRingkas
	var tgl time.Time
	if err := a.db.QueryRow(r.Context(), `select id, nama_sesi, tanggal from sesi_absensi where id=$1`, id).Scan(&s.ID, &s.Nama, &tgl); err != nil {
		if notFound(err) {
			fail(w, 404, "Sesi tidak ditemukan.")
			return
		}
		serverError(w, err)
		return
	}
	s.Tanggal = tgl.Format("2006-01-02")
	kelas, err := a.kelasDalamSesi(r, id, nil)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"sesi": s, "kelas": kelas})
}

type KelasSesi struct {
	ID       int64    `json:"id"`
	Nama     string   `json:"nama"`
	Kategori string   `json:"kategori"`
	Maba     int      `json:"maba"`
	Terisi   int      `json:"terisi"`
	Status   string   `json:"status"`
	PJ       []string `json:"pj"`
}

// kelasDalamSesi; userID tidak nil = hanya kelas milik PJ itu.
func (a *App) kelasDalamSesi(r *http.Request, sesiID int64, userID *int64) ([]KelasSesi, error) {
	rows, err := a.db.Query(r.Context(), `
		select k.id, k.nama_kelas, k.kategori::text,
		  (select count(*) from mahasiswa m where m.kelas_id = k.id and m.deleted_at is null)::int,
		  (select count(*) from absensi ab join mahasiswa m on m.id = ab.mahasiswa_id and m.deleted_at is null
		     where ab.kelas_id = k.id and ab.sesi_absensi_id = $1)::int,
		  coalesce(sk.status::text, 'belum_dibuka'),
		  coalesce((select array_agg(us.name order by us.name) from kelas_user ku join users us on us.id = ku.user_id
		     where ku.kelas_id = k.id and us.role = 'pj'), '{}')
		from kelas k left join sesi_kelas sk on sk.kelas_id = k.id and sk.sesi_absensi_id = $1
		where ($2::bigint is null or k.id in (select kelas_id from kelas_user where user_id = $2))
		order by k.kategori, k.nama_kelas`, sesiID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []KelasSesi{}
	for rows.Next() {
		var k KelasSesi
		if err := rows.Scan(&k.ID, &k.Nama, &k.Kategori, &k.Maba, &k.Terisi, &k.Status, &k.PJ); err != nil {
			return nil, err
		}
		out = append(out, k)
	}
	return out, rows.Err()
}

// sesiStatus membuka/menutup absensi kelas terpilih. Menutup kelas otomatis
// menandai maba yang belum diabsen sebagai alpa.
func (a *App) sesiStatus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in struct {
		Aksi    string  `json:"aksi"`
		KelasID []int64 `json:"kelas_ids"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	if !oneOf(in.Aksi, "buka", "tutup") || len(in.KelasID) == 0 || len(in.KelasID) > 500 {
		fail(w, 422, "Pilih kelas dan aksi (buka atau tutup).")
		return
	}
	var pesan string
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var nama string
		if err := tx.QueryRow(r.Context(), `select nama_sesi from sesi_absensi where id=$1`, id).Scan(&nama); err != nil {
			return err
		}
		// Kelas yang belum punya baris sesi_kelas (dibuat sebelum sesi ini) ikut dibuatkan.
		if _, err := tx.Exec(r.Context(), `insert into sesi_kelas (sesi_absensi_id, kelas_id)
			select $1, k.id from kelas k where k.id = any($2) on conflict do nothing`, id, in.KelasID); err != nil {
			return err
		}
		var upd string
		if in.Aksi == "buka" {
			upd = `status='dibuka', opened_at=now(), opened_by=$3`
		} else {
			upd = `status='ditutup', closed_at=now(), closed_by=$3`
		}
		rows, err := tx.Query(r.Context(), `update sesi_kelas sk set `+upd+`, updated_at=now() from kelas k
			where sk.sesi_absensi_id=$1 and sk.kelas_id = any($2) and k.id = sk.kelas_id returning k.nama_kelas`, id, in.KelasID, u.ID)
		if err != nil {
			return err
		}
		var namaKelas []string
		for rows.Next() {
			var n string
			if err := rows.Scan(&n); err != nil {
				return err
			}
			namaKelas = append(namaKelas, n)
		}
		if err := rows.Err(); err != nil {
			return err
		}
		ket := nama + " - kelas: " + strings.Join(namaKelas, ", ")
		pesan = sprintf("%d kelas dibuka.", len(namaKelas))
		if in.Aksi == "tutup" {
			tag, err := tx.Exec(r.Context(), `insert into absensi (mahasiswa_id, kelas_id, sesi_absensi_id, status, recorded_by)
				select m.id, m.kelas_id, $1, 'alpa', $3 from mahasiswa m
				where m.kelas_id = any($2) and m.deleted_at is null
				on conflict (mahasiswa_id, sesi_absensi_id) do nothing`, id, in.KelasID, u.ID)
			if err != nil {
				return err
			}
			ket += sprintf(" (%d maba otomatis dialpa)", tag.RowsAffected())
			pesan = sprintf("%d kelas ditutup. %d maba yang belum diabsen ditandai alpa.", len(namaKelas), tag.RowsAffected())
		}
		return catat(r.Context(), tx, u, "sesi."+in.Aksi, ket)
	})
	if notFound(err) {
		fail(w, 404, "Sesi tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": pesan})
}
