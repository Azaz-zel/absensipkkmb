package main

import (
	"net/http"
	"time"
)

func (a *App) dashboard(w http.ResponseWriter, r *http.Request, u *User) {
	switch u.Role {
	case "admin":
		a.dashboardAdmin(w, r)
	case "manajer":
		a.dashboardManajer(w, r)
	default:
		a.dashboardPJ(w, r, u)
	}
}

// sesiTerbaru: sesi dengan tanggal paling akhir, atau 0 kalau belum ada.
func (a *App) sesiTerbaru(r *http.Request) (SesiRingkas, error) {
	var s SesiRingkas
	var tgl time.Time
	err := a.db.QueryRow(r.Context(), `select id, nama_sesi, tanggal from sesi_absensi order by tanggal desc, id desc limit 1`).Scan(&s.ID, &s.Nama, &tgl)
	if notFound(err) {
		return s, nil
	}
	s.Tanggal = tgl.Format("2006-01-02")
	return s, err
}

func (a *App) dashboardAdmin(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	out := M{}
	var totalMaba int
	if err := a.db.QueryRow(ctx, `select count(*)::int from mahasiswa where deleted_at is null`).Scan(&totalMaba); err != nil {
		serverError(w, err)
		return
	}
	out["total_maba"] = totalMaba

	s, err := a.sesiTerbaru(r)
	if err != nil {
		serverError(w, err)
		return
	}
	if s.ID != 0 {
		var h, i, sk, al int
		if err := a.db.QueryRow(ctx, `select count(*) filter (where ab.status='hadir')::int, count(*) filter (where ab.status='izin')::int,
			count(*) filter (where ab.status='sakit')::int, count(*) filter (where ab.status='alpa')::int
			from absensi ab join mahasiswa m on m.id = ab.mahasiswa_id and m.deleted_at is null
			where ab.sesi_absensi_id=$1`, s.ID).Scan(&h, &i, &sk, &al); err != nil {
			serverError(w, err)
			return
		}
		kelas, err := a.kelasDalamSesi(r, s.ID, nil)
		if err != nil {
			serverError(w, err)
			return
		}
		for _, k := range kelas {
			switch k.Status {
			case "dibuka":
				s.Dibuka++
			case "ditutup":
				s.Ditutup++
			default:
				s.BelumBuka++
			}
		}
		s.StatusSesi = statusSesi(s.Dibuka, s.Ditutup)
		out["sesi"] = s
		out["progres"] = M{"hadir": h, "izin": i, "sakit": sk, "alpa": al}
		out["kelas"] = kelas
	}

	// Maba dengan poin tertinggi yang sudah kena sanksi.
	q := where{args: []any{int64(0)}}
	rows, err := a.db.Query(ctx, replaceWhere(mabaPoinCTE, q.sql())+`select `+mabaPoinCols+` from c
		where `+totalPoinSQL+` > 0 order by `+totalPoinSQL+` desc, nama limit 6`, q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	top := []MabaPoin{}
	for rows.Next() {
		m, err := scanMabaPoin(rows)
		if err != nil {
			serverError(w, err)
			return
		}
		top = append(top, m)
	}
	out["perlu_sanksi"] = top

	// Persentase hadir per sesi yang semua kelasnya sudah tidak dibuka.
	rows, err = a.db.Query(ctx, `select s.nama_sesi, s.tanggal,
		round(100.0 * count(ab.id) filter (where ab.status='hadir') / nullif(count(ab.id),0), 1)::float8,
		round(100.0 * count(ab.id) filter (where ab.status='hadir' and k.kategori='pagi') / nullif(count(ab.id) filter (where k.kategori='pagi'),0), 1)::float8,
		round(100.0 * count(ab.id) filter (where ab.status='hadir' and k.kategori='malam') / nullif(count(ab.id) filter (where k.kategori='malam'),0), 1)::float8,
		count(ab.id) filter (where ab.status='alpa')::int
		from sesi_absensi s
		join absensi ab on ab.sesi_absensi_id = s.id
		join kelas k on k.id = ab.kelas_id
		where not exists (select 1 from sesi_kelas sk where sk.sesi_absensi_id = s.id and sk.status = 'dibuka')
		group by s.id order by s.tanggal, s.id`)
	if err != nil {
		serverError(w, err)
		return
	}
	tren := []M{}
	for rows.Next() {
		var nama string
		var tgl time.Time
		var semua, pagi, malam *float64
		var alpa int
		if err := rows.Scan(&nama, &tgl, &semua, &pagi, &malam, &alpa); err != nil {
			serverError(w, err)
			return
		}
		tren = append(tren, M{"nama": nama, "tanggal": tgl.Format("2006-01-02"), "semua": semua, "pagi": pagi, "malam": malam, "alpa": alpa})
	}
	out["tren"] = tren
	writeJSON(w, 200, out)
}

func (a *App) dashboardManajer(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	q := where{args: []any{int64(0)}}
	sebaran := M{"bersih": 0, "ringan": 0, "sedang": 0, "berat": 0, "merah": 0}
	var total int
	rows, err := a.db.Query(ctx, replaceWhere(mabaPoinCTE, q.sql())+`select `+totalPoinSQL+` from c`, q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	for rows.Next() {
		var p int
		if err := rows.Scan(&p); err != nil {
			serverError(w, err)
			return
		}
		lv := sanksiUntuk(p).Level
		sebaran[lv] = sebaran[lv].(int) + 1
		total++
	}
	terbaru, err := a.pelanggaranTerbaru(r, 8)
	if err != nil {
		serverError(w, err)
		return
	}
	var hariIni int
	if err := a.db.QueryRow(ctx, `select count(*)::int from pelanggaran where (created_at at time zone 'Asia/Makassar')::date = (now() at time zone 'Asia/Makassar')::date`).Scan(&hariIni); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"total_maba": total, "sebaran": sebaran, "terbaru": terbaru, "hari_ini": hariIni})
}

func (a *App) pelanggaranTerbaru(r *http.Request, limit int) ([]M, error) {
	rows, err := a.db.Query(r.Context(), `select p.id, m.id, m.nama, k.nama_kelas, p.jenis, p.poin, p.created_at, us.name
		from pelanggaran p join mahasiswa m on m.id = p.mahasiswa_id and m.deleted_at is null
		join kelas k on k.id = m.kelas_id left join users us on us.id = p.dicatat_oleh
		order by p.created_at desc, p.id desc limit $1`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []M{}
	for rows.Next() {
		var id, mid int64
		var nama, kelas, jenis string
		var poin int
		var at time.Time
		var pencatat *string
		if err := rows.Scan(&id, &mid, &nama, &kelas, &jenis, &poin, &at, &pencatat); err != nil {
			return nil, err
		}
		out = append(out, M{"id": id, "mahasiswa_id": mid, "nama": nama, "kelas": kelas, "jenis": jenis, "poin": poin, "created_at": at, "pencatat": pencatat})
	}
	return out, rows.Err()
}

func (a *App) dashboardPJ(w http.ResponseWriter, r *http.Request, u *User) {
	s, err := a.sesiTerbaru(r)
	if err != nil {
		serverError(w, err)
		return
	}
	kelas := []KelasSesi{}
	if s.ID != 0 {
		if kelas, err = a.kelasDalamSesi(r, s.ID, &u.ID); err != nil {
			serverError(w, err)
			return
		}
	}
	rows, err := a.db.Query(r.Context(), `select s.id, s.nama_sesi, s.tanggal, k.id, k.nama_kelas, coalesce(sk.status::text,'belum_dibuka'),
		count(ab.id) filter (where ab.status='hadir')::int, count(ab.id) filter (where ab.status='izin')::int,
		count(ab.id) filter (where ab.status='sakit')::int, count(ab.id) filter (where ab.status='alpa')::int
		from sesi_absensi s
		join kelas_user ku on ku.user_id = $1
		join kelas k on k.id = ku.kelas_id
		left join sesi_kelas sk on sk.sesi_absensi_id = s.id and sk.kelas_id = k.id
		left join absensi ab on ab.sesi_absensi_id = s.id and ab.kelas_id = k.id
		where s.id <> $2
		group by s.id, k.id, sk.status order by s.tanggal desc, s.id desc, k.nama_kelas limit 60`, u.ID, s.ID)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	riwayat := []M{}
	for rows.Next() {
		var sid, kid int64
		var nama, kn, st string
		var tgl time.Time
		var h, i, sk, al int
		if err := rows.Scan(&sid, &nama, &tgl, &kid, &kn, &st, &h, &i, &sk, &al); err != nil {
			serverError(w, err)
			return
		}
		riwayat = append(riwayat, M{"sesi_id": sid, "sesi": nama, "tanggal": tgl.Format("2006-01-02"), "kelas_id": kid, "kelas": kn,
			"status": st, "hadir": h, "izin": i, "sakit": sk, "alpa": al})
	}
	var sesi any
	if s.ID != 0 {
		sesi = s
	}
	writeJSON(w, 200, M{"sesi": sesi, "kelas": kelas, "riwayat": riwayat})
}
