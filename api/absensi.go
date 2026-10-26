package main

import (
	"net/http"
	"strconv"
	"time"
)

// absensiIndex: daftar sesi + kelas yang bisa diisi. PJ hanya melihat kelasnya.
func (a *App) absensiIndex(w http.ResponseWriter, r *http.Request, u *User) {
	sesi, err := a.daftarSesi(r)
	if err != nil {
		serverError(w, err)
		return
	}
	var userID *int64
	if u.Role != "admin" {
		userID = &u.ID
	}
	pilih := qInt(r, "sesi")
	if pilih == 0 && len(sesi) > 0 {
		pilih = sesi[0].ID
	}
	kelas := []KelasSesi{}
	if pilih != 0 {
		if kelas, err = a.kelasDalamSesi(r, pilih, userID); err != nil {
			serverError(w, err)
			return
		}
	}
	writeJSON(w, 200, M{"sesi": sesi, "sesi_id": pilih, "kelas": kelas})
}

type MabaAbsen struct {
	ID      int64   `json:"id"`
	Nama    string  `json:"nama"`
	NIM     string  `json:"nim"`
	Jurusan string  `json:"jurusan"`
	Status  *string `json:"status"`
}

func (a *App) sesiKelasParam(w http.ResponseWriter, r *http.Request, u *User) (sesiID, kelasID int64, ok bool) {
	if sesiID, ok = pathID(w, r, "sesi"); !ok {
		return
	}
	if kelasID, ok = pathID(w, r, "kelas"); !ok {
		return
	}
	izin, err := a.kelasDiizinkan(r.Context(), u, kelasID)
	if err != nil {
		serverError(w, err)
		return 0, 0, false
	}
	if !izin {
		fail(w, 403, "Kamu tidak memegang kelas ini.")
		return 0, 0, false
	}
	return sesiID, kelasID, true
}

func (a *App) absensiShow(w http.ResponseWriter, r *http.Request, u *User) {
	sesiID, kelasID, ok := a.sesiKelasParam(w, r, u)
	if !ok {
		return
	}
	var sesiNama, kelasNama, kategori, status string
	var tgl time.Time
	var pj []string
	err := a.db.QueryRow(r.Context(), `select s.nama_sesi, s.tanggal, k.nama_kelas, k.kategori::text, coalesce(sk.status::text,'belum_dibuka'),
		coalesce((select array_agg(us.name order by us.name) from kelas_user ku join users us on us.id=ku.user_id
		   where ku.kelas_id=k.id and us.role='pj'), '{}')
		from sesi_absensi s cross join kelas k
		left join sesi_kelas sk on sk.sesi_absensi_id = s.id and sk.kelas_id = k.id
		where s.id=$1 and k.id=$2`, sesiID, kelasID).Scan(&sesiNama, &tgl, &kelasNama, &kategori, &status, &pj)
	if notFound(err) {
		fail(w, 404, "Sesi atau kelas tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	rows, err := a.db.Query(r.Context(), `select m.id, m.nama, m.nim, m.jurusan, ab.status::text from mahasiswa m
		left join absensi ab on ab.mahasiswa_id = m.id and ab.sesi_absensi_id = $1
		where m.kelas_id = $2 and m.deleted_at is null order by m.nama`, sesiID, kelasID)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	maba := []MabaAbsen{}
	for rows.Next() {
		var m MabaAbsen
		if err := rows.Scan(&m.ID, &m.Nama, &m.NIM, &m.Jurusan, &m.Status); err != nil {
			serverError(w, err)
			return
		}
		maba = append(maba, m)
	}
	writeJSON(w, 200, M{
		"sesi":     M{"id": sesiID, "nama": sesiNama, "tanggal": tgl.Format("2006-01-02")},
		"kelas":    M{"id": kelasID, "nama": kelasNama, "kategori": kategori, "pj": pj},
		"status":   status,
		"bisa_isi": status == "dibuka" || u.Role == "admin",
		"maba":     maba,
	})
}

// absensiSimpan menyimpan status kehadiran. PJ hanya bisa saat kelas dibuka;
// admin bisa kapan pun. Id maba di luar kelas ini diabaikan.
func (a *App) absensiSimpan(w http.ResponseWriter, r *http.Request, u *User) {
	sesiID, kelasID, ok := a.sesiKelasParam(w, r, u)
	if !ok {
		return
	}
	var in struct {
		Absensi map[string]string `json:"absensi"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	if len(in.Absensi) == 0 || len(in.Absensi) > 1000 {
		fail(w, 422, "Belum ada kehadiran yang diisi.")
		return
	}
	var status string
	err := a.db.QueryRow(r.Context(), `select coalesce((select status::text from sesi_kelas where sesi_absensi_id=$1 and kelas_id=$2), 'belum_dibuka')
		where exists (select 1 from sesi_absensi where id=$1)`, sesiID, kelasID).Scan(&status)
	if notFound(err) {
		fail(w, 404, "Sesi tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if status != "dibuka" && u.Role != "admin" {
		fail(w, 403, "Absensi kelas ini sedang tidak dibuka.")
		return
	}
	var ids []int64
	var stats []string
	for k, v := range in.Absensi {
		id, err := strconv.ParseInt(k, 10, 64)
		if err != nil || !oneOf(v, "hadir", "izin", "sakit", "alpa") {
			continue
		}
		ids = append(ids, id)
		stats = append(stats, v)
	}
	tag, err := a.db.Exec(r.Context(), `insert into absensi (mahasiswa_id, kelas_id, sesi_absensi_id, status, recorded_by)
		select m.id, m.kelas_id, $1, x.st::status_hadir, $4
		from unnest($2::bigint[], $3::text[]) as x(id, st)
		join mahasiswa m on m.id = x.id and m.kelas_id = $5 and m.deleted_at is null
		on conflict (mahasiswa_id, sesi_absensi_id) do update
		  set status = excluded.status, kelas_id = excluded.kelas_id, recorded_by = excluded.recorded_by, updated_at = now()`,
		sesiID, ids, stats, u.ID, kelasID)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": sprintf("Absensi %d maba tersimpan.", tag.RowsAffected())})
}

func (a *App) absensiRecordUbah(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in struct {
		Status string `json:"status"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	if !oneOf(in.Status, "hadir", "izin", "sakit", "alpa") {
		fail(w, 422, "Status kehadiran tidak dikenal.")
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var nama, nim, sesi, lama string
		if err := tx.QueryRow(r.Context(), `select m.nama, m.nim, s.nama_sesi, ab.status::text from absensi ab
			join mahasiswa m on m.id = ab.mahasiswa_id join sesi_absensi s on s.id = ab.sesi_absensi_id
			where ab.id = $1 for update of ab`, id).Scan(&nama, &nim, &sesi, &lama); err != nil {
			return err
		}
		if _, err := tx.Exec(r.Context(), `update absensi set status=$2, recorded_by=$3, updated_at=now() where id=$1`, id, in.Status, u.ID); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "absensi.ubah", sprintf("%s (%s) pada %s: %s menjadi %s", nama, nim, sesi, lama, in.Status))
	})
	if notFound(err) {
		fail(w, 404, "Data kehadiran tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Kehadiran diubah."})
}

func (a *App) absensiRecordHapus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var nama, nim, sesi, lama string
		if err := tx.QueryRow(r.Context(), `delete from absensi ab using mahasiswa m, sesi_absensi s
			where ab.id = $1 and m.id = ab.mahasiswa_id and s.id = ab.sesi_absensi_id
			returning m.nama, m.nim, s.nama_sesi, ab.status::text`, id).Scan(&nama, &nim, &sesi, &lama); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "absensi.hapus", sprintf("%s (%s) pada %s, sebelumnya %s", nama, nim, sesi, lama))
	})
	if notFound(err) {
		fail(w, 404, "Data kehadiran tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Kehadiran dihapus."})
}
