package main

import (
	"net/http"
	"time"
)

// Aturan poin: alpa 5, izin/sakit 3 per sesi, ditambah poin pelanggaran.
func poinAbsensi(izin, sakit, alpa int) int { return alpa*5 + (izin+sakit)*3 }

// Ekspresi SQL yang sama dengan poinAbsensi + pelanggaran, untuk ORDER BY / filter.
const totalPoinSQL = `(alpa*5 + (izin+sakit)*3 + pel)`

type Sanksi struct {
	Level string `json:"level"`
	Label string `json:"label"`
}

func sanksiUntuk(poin int) Sanksi {
	switch {
	case poin >= 20:
		return Sanksi{"merah", "Cap merah · mengulang PKKMB"}
	case poin >= 10:
		return Sanksi{"berat", "Video Problem and Solution"}
	case poin >= 6:
		return Sanksi{"sedang", "Video Edukasi Digitalisasi"}
	case poin >= 1:
		return Sanksi{"ringan", "Video PKKMB Journey"}
	}
	return Sanksi{"bersih", "Tidak ada sanksi"}
}

// mabaPoinCTE menghitung kehadiran dan poin per maba. $1 = id sesi (0 = semua sesi).
// Filter tambahan masuk lewat {{where}}.
const mabaPoinCTE = `with c as (
  select m.id, m.nama, m.nim, m.jurusan, m.kelas_id, k.nama_kelas, k.kategori::text as kategori,
    count(a.id) filter (where a.status = 'hadir')::int as hadir,
    count(a.id) filter (where a.status = 'izin')::int as izin,
    count(a.id) filter (where a.status = 'sakit')::int as sakit,
    count(a.id) filter (where a.status = 'alpa')::int as alpa,
    coalesce((select sum(p.poin) from pelanggaran p where p.mahasiswa_id = m.id), 0)::int as pel
  from mahasiswa m
  join kelas k on k.id = m.kelas_id
  left join absensi a on a.mahasiswa_id = m.id and ($1::bigint = 0 or a.sesi_absensi_id = $1)
  where m.deleted_at is null and {{where}}
  group by m.id, k.id
)`

type MabaPoin struct {
	ID        int64  `json:"id"`
	Nama      string `json:"nama"`
	NIM       string `json:"nim"`
	Jurusan   string `json:"jurusan"`
	KelasID   int64  `json:"kelas_id"`
	Kelas     string `json:"kelas"`
	Kategori  string `json:"kategori"`
	Hadir     int    `json:"hadir"`
	Izin      int    `json:"izin"`
	Sakit     int    `json:"sakit"`
	Alpa      int    `json:"alpa"`
	PoinAbsen int    `json:"poin_absensi"`
	PoinPel   int    `json:"poin_pelanggaran"`
	Total     int    `json:"total_poin"`
	Sanksi    Sanksi `json:"sanksi"`
}

const mabaPoinCols = `id, nama, nim, jurusan, kelas_id, nama_kelas, kategori, hadir, izin, sakit, alpa, pel`

func scanMabaPoin(s interface{ Scan(...any) error }, extra ...any) (MabaPoin, error) {
	var m MabaPoin
	err := s.Scan(append([]any{&m.ID, &m.Nama, &m.NIM, &m.Jurusan, &m.KelasID, &m.Kelas, &m.Kategori,
		&m.Hadir, &m.Izin, &m.Sakit, &m.Alpa, &m.PoinPel}, extra...)...)
	m.PoinAbsen = poinAbsensi(m.Izin, m.Sakit, m.Alpa)
	m.Total = m.PoinAbsen + m.PoinPel
	m.Sanksi = sanksiUntuk(m.Total)
	return m, err
}

// poinCari: pencarian cepat maba untuk dicatat pelanggarannya (admin & manajer).
func (a *App) poinCari(w http.ResponseWriter, r *http.Request, u *User) {
	term := qStr(r, "q")
	out := []MabaPoin{}
	if term == "" {
		writeJSON(w, 200, M{"data": out})
		return
	}
	q := where{args: []any{int64(0)}}
	q.add("(m.nama ilike ? or m.nim ilike ?)", likePattern(term), likePattern(term))
	rows, err := a.db.Query(r.Context(), replaceWhere(mabaPoinCTE, q.sql())+` select `+mabaPoinCols+` from c order by nama limit 25`, q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	for rows.Next() {
		m, err := scanMabaPoin(rows)
		if err != nil {
			serverError(w, err)
			return
		}
		out = append(out, m)
	}
	writeJSON(w, 200, M{"data": out})
}

type Pelanggaran struct {
	ID        int64     `json:"id"`
	Jenis     string    `json:"jenis"`
	Poin      int       `json:"poin"`
	Pencatat  *string   `json:"pencatat"`
	PeranPen  *string   `json:"pencatat_role"`
	CreatedAt time.Time `json:"created_at"`
}

// mahasiswaDetail: data diri, rincian poin, catatan pelanggaran, dan kehadiran per sesi.
func (a *App) mahasiswaDetail(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	q := where{args: []any{int64(0)}}
	q.add("m.id = ?", id)
	m, err := scanMabaPoin(a.db.QueryRow(r.Context(), replaceWhere(mabaPoinCTE, q.sql())+` select `+mabaPoinCols+` from c`, q.args...))
	if notFound(err) {
		fail(w, 404, "Data mahasiswa tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if u.Role == "pj" {
		if ok, err := a.kelasDiizinkan(r.Context(), u, m.KelasID); err != nil || !ok {
			fail(w, 403, "Maba ini bukan dari kelasmu.")
			return
		}
	}

	pel := []Pelanggaran{}
	rows, err := a.db.Query(r.Context(), `select p.id, p.jenis, p.poin, us.name, us.role::text, p.created_at
		from pelanggaran p left join users us on us.id = p.dicatat_oleh
		where p.mahasiswa_id = $1 order by p.created_at desc, p.id desc`, id)
	if err != nil {
		serverError(w, err)
		return
	}
	for rows.Next() {
		var p Pelanggaran
		if err := rows.Scan(&p.ID, &p.Jenis, &p.Poin, &p.Pencatat, &p.PeranPen, &p.CreatedAt); err != nil {
			serverError(w, err)
			return
		}
		pel = append(pel, p)
	}

	hadir := []M{}
	if u.Role != "manajer" {
		rows, err = a.db.Query(r.Context(), `select ab.id, s.nama_sesi, s.tanggal, ab.status::text
			from absensi ab join sesi_absensi s on s.id = ab.sesi_absensi_id
			where ab.mahasiswa_id = $1 order by s.tanggal desc, s.id desc`, id)
		if err != nil {
			serverError(w, err)
			return
		}
		for rows.Next() {
			var rid int64
			var nama, st string
			var tgl time.Time
			if err := rows.Scan(&rid, &nama, &tgl, &st); err != nil {
				serverError(w, err)
				return
			}
			hadir = append(hadir, M{"id": rid, "sesi": nama, "tanggal": tgl.Format("2006-01-02"), "status": st})
		}
	}
	writeJSON(w, 200, M{"mahasiswa": m, "pelanggaran": pel, "kehadiran": hadir})
}

type pelanggaranIn struct {
	Jenis string `json:"jenis"`
	Poin  int    `json:"poin"`
}

func (p *pelanggaranIn) validate() Errs {
	e := Errs{}
	if !textLen(p.Jenis, 1, 255) {
		e["jenis"] = "Isi jenis pelanggaran (maksimal 255 karakter)."
	}
	if p.Poin < 1 || p.Poin > 20 {
		e["poin"] = "Poin antara 1 sampai 20."
	}
	return e
}

func (a *App) pelanggaranCatat(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in pelanggaranIn
	if !readJSON(w, r, &in) {
		return
	}
	if in.validate().send(w) {
		return
	}
	var nama, nim string
	if err := a.db.QueryRow(r.Context(), `select nama, nim from mahasiswa where id=$1 and deleted_at is null`, id).Scan(&nama, &nim); err != nil {
		if notFound(err) {
			fail(w, 404, "Data mahasiswa tidak ditemukan.")
			return
		}
		serverError(w, err)
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		if _, err := tx.Exec(r.Context(), `insert into pelanggaran (mahasiswa_id, jenis, poin, dicatat_oleh) values ($1,$2,$3,$4)`,
			id, in.Jenis, in.Poin, u.ID); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "pelanggaran.catat", sprintf("%s (%s) +%d poin: %s", nama, nim, in.Poin, in.Jenis))
	})
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 201, M{"message": sprintf("Pelanggaran %s dicatat (+%d poin).", nama, in.Poin)})
}

func (a *App) pelanggaranUbah(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in pelanggaranIn
	if !readJSON(w, r, &in) {
		return
	}
	if in.validate().send(w) {
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var nama, nim, jenisLama string
		var poinLama int
		if err := tx.QueryRow(r.Context(), `select m.nama, m.nim, p.jenis, p.poin from pelanggaran p
			join mahasiswa m on m.id = p.mahasiswa_id where p.id = $1 for update of p`, id).Scan(&nama, &nim, &jenisLama, &poinLama); err != nil {
			return err
		}
		if _, err := tx.Exec(r.Context(), `update pelanggaran set jenis=$2, poin=$3, updated_at=now() where id=$1`, id, in.Jenis, in.Poin); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "pelanggaran.ubah",
			sprintf("%s (%s): \"%s\" %d poin menjadi \"%s\" %d poin", nama, nim, jenisLama, poinLama, in.Jenis, in.Poin))
	})
	if notFound(err) {
		fail(w, 404, "Catatan pelanggaran tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Catatan pelanggaran diperbarui."})
}

func (a *App) pelanggaranHapus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var nama, nim, jenis string
		var poin int
		if err := tx.QueryRow(r.Context(), `delete from pelanggaran p using mahasiswa m
			where p.id = $1 and m.id = p.mahasiswa_id returning m.nama, m.nim, p.jenis, p.poin`, id).Scan(&nama, &nim, &jenis, &poin); err != nil {
			return err
		}
		return catat(r.Context(), tx, u, "pelanggaran.hapus", sprintf("%s (%s) -%d poin: %s", nama, nim, poin, jenis))
	})
	if notFound(err) {
		fail(w, 404, "Catatan pelanggaran tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Catatan pelanggaran dihapus."})
}
