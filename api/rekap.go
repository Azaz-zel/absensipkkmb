package main

import (
	"net/http"
	"time"
)

const perHalaman = 40

// rekapFilter menyusun CTE maba + poin sesuai filter di query string.
// Hasilnya tabel "f" (c + kolom total) yang siap di-select.
func rekapFilter(r *http.Request, u *User) (string, []any) {
	sesi := qInt(r, "sesi")
	q := where{args: []any{sesi}}
	if u.Role != "admin" {
		q.add("m.kelas_id in (select kelas_id from kelas_user where user_id = ?)", u.ID)
	}
	if s := qStr(r, "q"); s != "" {
		q.add("(m.nama ilike ? or m.nim ilike ?)", likePattern(s), likePattern(s))
	}
	if v := qInt(r, "kelas_id"); v > 0 {
		q.add("m.kelas_id = ?", v)
	}
	if v := qStr(r, "jurusan"); v != "" {
		q.add("m.jurusan = ?", v)
	}
	if v := qStr(r, "kategori"); oneOf(v, "pagi", "malam") {
		q.add("k.kategori = ?", v)
	}
	switch qStr(r, "kehadiran") {
	case "hadir":
		q.add("exists (select 1 from absensi a2 where a2.mahasiswa_id = m.id and ($1 = 0 or a2.sesi_absensi_id = $1) and a2.status = 'hadir')")
	case "tidak_hadir":
		q.add("exists (select 1 from absensi a2 where a2.mahasiswa_id = m.id and ($1 = 0 or a2.sesi_absensi_id = $1) and a2.status <> 'hadir')")
	}
	poin := "true"
	switch qStr(r, "poin") {
	case "kena":
		poin = "total > 0"
	case "aman":
		poin = "total = 0"
	case "berat":
		poin = "total >= 10"
	case "merah":
		poin = "total >= 20"
	}
	return replaceWhere(mabaPoinCTE, q.sql()) + `, f as (select *, ` + totalPoinSQL + ` as total from c) `, append(q.args, poin)
}

func rekapOrder(r *http.Request) string {
	switch qStr(r, "sort") {
	case "persen_desc":
		return "hadir*1.0/nullif(hadir+izin+sakit+alpa,0) desc nulls last, nama"
	case "persen_asc":
		return "hadir*1.0/nullif(hadir+izin+sakit+alpa,0) asc nulls last, nama"
	case "poin_desc":
		return "total desc, nama"
	case "poin_asc":
		return "total asc, nama"
	}
	return "nama"
}

func (a *App) rekapMahasiswa(w http.ResponseWriter, r *http.Request, u *User) {
	base, args := rekapFilter(r, u)
	poin := args[len(args)-1].(string)
	args = args[:len(args)-1]
	p := page(r)
	rows, err := a.db.Query(r.Context(), base+`select `+mabaPoinCols+`,
		count(*) over()::int, sum(hadir) over()::int, sum(izin) over()::int, sum(sakit) over()::int, sum(alpa) over()::int
		from f where `+poin+` order by `+rekapOrder(r)+sprintf(` limit %d offset %d`, perHalaman, (p-1)*perHalaman), args...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	data := []MabaPoin{}
	var total, th, ti, ts, ta int
	for rows.Next() {
		m, err := scanMabaPoin(rows, &total, &th, &ti, &ts, &ta)
		if err != nil {
			serverError(w, err)
			return
		}
		data = append(data, m)
	}
	if err := rows.Err(); err != nil {
		serverError(w, err)
		return
	}
	if len(data) == 0 && p > 1 {
		// Halaman di luar jangkauan: total tetap dihitung supaya paginasi benar.
		_ = a.db.QueryRow(r.Context(), base+`select count(*)::int from f where `+poin, args...).Scan(&total)
	}
	jumlahSesi, err := a.jumlahSesi(r)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{
		"data": data, "total": total, "page": p, "per_page": perHalaman,
		"jumlah":      M{"hadir": th, "izin": ti, "sakit": ts, "alpa": ta},
		"jumlah_sesi": jumlahSesi,
	})
}

// jumlahSesi yang dihitung untuk "seharusnya diabsen": 1 kalau difilter satu sesi.
func (a *App) jumlahSesi(r *http.Request) (int, error) {
	if qInt(r, "sesi") > 0 {
		return 1, nil
	}
	var n int
	err := a.db.QueryRow(r.Context(), `select count(*)::int from sesi_absensi`).Scan(&n)
	return n, err
}

type RekapKelas struct {
	ID       int64    `json:"id"`
	Nama     string   `json:"nama"`
	Kategori string   `json:"kategori"`
	Maba     int      `json:"maba"`
	PJ       []string `json:"pj"`
	Hadir    int      `json:"hadir"`
	Izin     int      `json:"izin"`
	Sakit    int      `json:"sakit"`
	Alpa     int      `json:"alpa"`
	Belum    int      `json:"belum"`
	Persen   float64  `json:"persen"`
}

func (a *App) rekapKelas(w http.ResponseWriter, r *http.Request, u *User) {
	q := where{}
	sesi := q.arg(qInt(r, "sesi"))
	jur := q.arg(qStr(r, "jurusan"))
	if u.Role != "admin" {
		q.add("k.id in (select kelas_id from kelas_user where user_id = ?)", u.ID)
	}
	if v := qStr(r, "kategori"); oneOf(v, "pagi", "malam") {
		q.add("k.kategori = ?", v)
	}
	order := "k.kategori, k.nama_kelas"
	switch qStr(r, "sort") {
	case "persen_desc":
		order = "persen desc, k.nama_kelas"
	case "persen_asc":
		order = "persen asc, k.nama_kelas"
	}
	rows, err := a.db.Query(r.Context(), `select * from (
		select k.id, k.nama_kelas, k.kategori::text,
		  count(distinct m.id)::int as maba,
		  coalesce((select array_agg(us.name order by us.name) from kelas_user ku join users us on us.id=ku.user_id
		     where ku.kelas_id=k.id and us.role='pj'), '{}') as pj,
		  count(ab.id) filter (where ab.status='hadir')::int as hadir,
		  count(ab.id) filter (where ab.status='izin')::int as izin,
		  count(ab.id) filter (where ab.status='sakit')::int as sakit,
		  count(ab.id) filter (where ab.status='alpa')::int as alpa,
		  coalesce(round(100.0 * count(ab.id) filter (where ab.status='hadir') / nullif(count(ab.id),0), 1), 0)::float8 as persen
		from kelas k
		left join mahasiswa m on m.kelas_id = k.id and m.deleted_at is null and (`+jur+` = '' or m.jurusan = `+jur+`)
		left join absensi ab on ab.mahasiswa_id = m.id and (`+sesi+` = 0 or ab.sesi_absensi_id = `+sesi+`)
		where `+q.sql()+`
		group by k.id) k order by `+order, q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	jumlahSesi, err := a.jumlahSesi(r)
	if err != nil {
		serverError(w, err)
		return
	}
	data := []RekapKelas{}
	for rows.Next() {
		var k RekapKelas
		if err := rows.Scan(&k.ID, &k.Nama, &k.Kategori, &k.Maba, &k.PJ, &k.Hadir, &k.Izin, &k.Sakit, &k.Alpa, &k.Persen); err != nil {
			serverError(w, err)
			return
		}
		k.Belum = max(k.Maba*jumlahSesi-(k.Hadir+k.Izin+k.Sakit+k.Alpa), 0)
		data = append(data, k)
	}
	writeJSON(w, 200, M{"data": data, "jumlah_sesi": jumlahSesi})
}

// filterOptions: pilihan dropdown (kelas sesuai hak akses, sesi, jurusan).
func (a *App) filterOptions(w http.ResponseWriter, r *http.Request, u *User) {
	var userID *int64
	if u.Role == "pj" {
		userID = &u.ID
	}
	kelas := []M{}
	rows, err := a.db.Query(r.Context(), `select id, nama_kelas, kategori::text from kelas
		where ($1::bigint is null or id in (select kelas_id from kelas_user where user_id=$1)) order by kategori, nama_kelas`, userID)
	if err != nil {
		serverError(w, err)
		return
	}
	for rows.Next() {
		var id int64
		var n, k string
		if err := rows.Scan(&id, &n, &k); err != nil {
			serverError(w, err)
			return
		}
		kelas = append(kelas, M{"id": id, "nama": n, "kategori": k})
	}
	sesi := []M{}
	rows, err = a.db.Query(r.Context(), `select id, nama_sesi, tanggal from sesi_absensi order by tanggal desc, id desc`)
	if err != nil {
		serverError(w, err)
		return
	}
	for rows.Next() {
		var id int64
		var n string
		var t time.Time
		if err := rows.Scan(&id, &n, &t); err != nil {
			serverError(w, err)
			return
		}
		sesi = append(sesi, M{"id": id, "nama": n, "tanggal": t.Format("2006-01-02")})
	}
	jurusan := []string{}
	rows, err = a.db.Query(r.Context(), `select distinct jurusan from mahasiswa where deleted_at is null order by jurusan`)
	if err != nil {
		serverError(w, err)
		return
	}
	for rows.Next() {
		var j string
		if err := rows.Scan(&j); err != nil {
			serverError(w, err)
			return
		}
		jurusan = append(jurusan, j)
	}
	writeJSON(w, 200, M{"kelas": kelas, "sesi": sesi, "jurusan": jurusan})
}
