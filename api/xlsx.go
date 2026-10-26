package main

import (
	"encoding/json"
	"net/http"
	"strings"
	"time"

	"github.com/xuri/excelize/v2"
)

// sendXLSX menulis satu sheet. Semua nilai teks disimpan sebagai string,
// jadi isi seperti "=HYPERLINK(...)" tidak pernah dijalankan sebagai rumus.
func sendXLSX(w http.ResponseWriter, nama string, header []string, rows [][]any) {
	f := excelize.NewFile()
	defer f.Close()
	sh := "Sheet1"
	sw, err := f.NewStreamWriter(sh)
	if err != nil {
		serverError(w, err)
		return
	}
	bold, _ := f.NewStyle(&excelize.Style{Font: &excelize.Font{Bold: true}})
	head := make([]any, len(header))
	for i, h := range header {
		head[i] = excelize.Cell{StyleID: bold, Value: h}
	}
	_ = sw.SetRow("A1", head)
	for i, row := range rows {
		cell, _ := excelize.CoordinatesToCellName(1, i+2)
		if err := sw.SetRow(cell, row); err != nil {
			serverError(w, err)
			return
		}
	}
	if err := sw.Flush(); err != nil {
		serverError(w, err)
		return
	}
	w.Header().Set("Content-Type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
	w.Header().Set("Content-Disposition", `attachment; filename="`+nama+`.xlsx"`)
	_ = f.Write(w)
}

func hariIni() string { return time.Now().Format("2006-01-02") }

func (a *App) exportRekap(w http.ResponseWriter, r *http.Request, u *User) {
	base, args := rekapFilter(r, u)
	poin := args[len(args)-1].(string)
	rows, err := a.db.Query(r.Context(), base+`select `+mabaPoinCols+` from f where `+poin+` order by `+rekapOrder(r), args[:len(args)-1]...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	out := [][]any{}
	for rows.Next() {
		m, err := scanMabaPoin(rows)
		if err != nil {
			serverError(w, err)
			return
		}
		jml := m.Hadir + m.Izin + m.Sakit + m.Alpa
		persen := 0.0
		if jml > 0 {
			persen = float64(m.Hadir) * 100 / float64(jml)
		}
		out = append(out, []any{m.Nama, m.NIM, m.Jurusan, m.Kelas, m.Kategori, m.Hadir, m.Izin, m.Sakit, m.Alpa, persen, m.PoinAbsen, m.PoinPel, m.Total, m.Sanksi.Label})
	}
	sendXLSX(w, "rekap-kehadiran-"+hariIni(),
		[]string{"Nama", "NIM", "Jurusan", "Kelas", "Kategori", "Hadir", "Izin", "Sakit", "Alpa", "% Hadir", "Poin absensi", "Poin pelanggaran", "Total poin", "Sanksi"}, out)
}

func (a *App) exportSanksi(w http.ResponseWriter, r *http.Request, u *User) {
	q := where{args: []any{int64(0)}}
	rows, err := a.db.Query(r.Context(), replaceWhere(mabaPoinCTE, q.sql())+`select `+mabaPoinCols+` from c
		where `+totalPoinSQL+` > 0 order by `+totalPoinSQL+` desc, nama`, q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	out := [][]any{}
	for rows.Next() {
		m, err := scanMabaPoin(rows)
		if err != nil {
			serverError(w, err)
			return
		}
		out = append(out, []any{m.Nama, m.NIM, m.Kelas, m.Kategori, m.Total, m.Sanksi.Label})
	}
	sendXLSX(w, "daftar-sanksi-"+hariIni(), []string{"Nama", "NIM", "Kelas", "Kategori", "Total poin", "Sanksi"}, out)
}

func (a *App) exportPoin(w http.ResponseWriter, r *http.Request, u *User) {
	rows, err := a.db.Query(r.Context(), `select p.created_at, m.nama, m.nim, k.nama_kelas, p.jenis, p.poin, coalesce(us.name, '-')
		from pelanggaran p join mahasiswa m on m.id = p.mahasiswa_id and m.deleted_at is null
		join kelas k on k.id = m.kelas_id left join users us on us.id = p.dicatat_oleh order by p.created_at desc`)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	out := [][]any{}
	for rows.Next() {
		var at time.Time
		var nama, nim, kelas, jenis, pencatat string
		var poin int
		if err := rows.Scan(&at, &nama, &nim, &kelas, &jenis, &poin, &pencatat); err != nil {
			serverError(w, err)
			return
		}
		out = append(out, []any{at.In(wita).Format("2006-01-02 15:04"), nama, nim, kelas, jenis, poin, pencatat})
	}
	sendXLSX(w, "catatan-pelanggaran-"+hariIni(), []string{"Waktu", "Nama", "NIM", "Kelas", "Pelanggaran", "Poin", "Dicatat oleh"}, out)
}

// exportKelas: satu baris per maba, satu kolom per sesi.
func (a *App) exportKelas(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var nama string
	if err := a.db.QueryRow(r.Context(), `select nama_kelas from kelas where id=$1`, id).Scan(&nama); err != nil {
		fail(w, 404, "Kelas tidak ditemukan.")
		return
	}
	var sesiID []int64
	header := []string{"Nama", "NIM", "Jurusan"}
	sr, err := a.db.Query(r.Context(), `select id, nama_sesi from sesi_absensi order by tanggal, id`)
	if err != nil {
		serverError(w, err)
		return
	}
	for sr.Next() {
		var sid int64
		var sn string
		if err := sr.Scan(&sid, &sn); err != nil {
			serverError(w, err)
			return
		}
		sesiID = append(sesiID, sid)
		header = append(header, sn)
	}
	rows, err := a.db.Query(r.Context(), `select m.nama, m.nim, m.jurusan,
		coalesce((select jsonb_object_agg(ab.sesi_absensi_id, ab.status) from absensi ab where ab.mahasiswa_id = m.id), '{}')
		from mahasiswa m where m.kelas_id = $1 and m.deleted_at is null order by m.nama`, id)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	out := [][]any{}
	for rows.Next() {
		var n, nim, jur string
		var st map[string]string
		if err := rows.Scan(&n, &nim, &jur, &st); err != nil {
			serverError(w, err)
			return
		}
		row := []any{n, nim, jur}
		for _, sid := range sesiID {
			v := st[sprintf("%d", sid)]
			if v == "" {
				v = "-"
			}
			row = append(row, v)
		}
		out = append(out, row)
	}
	sendXLSX(w, "absensi-kelas-"+nama+"-"+hariIni(), header, out)
}

var wita = time.FixedZone("WITA", 8*3600)

// ---------- Import ----------

func (a *App) importTemplate(w http.ResponseWriter, r *http.Request, u *User) {
	sendXLSX(w, "template-mahasiswa", []string{"nama", "nim", "jurusan", "kelas"},
		[][]any{{"Nama Lengkap", "2615101001", "Sistem Informasi", "A"}})
}

type barisImport struct {
	Baris   int      `json:"baris"`
	Nama    string   `json:"nama"`
	NIM     string   `json:"nim"`
	Jurusan string   `json:"jurusan"`
	Kelas   string   `json:"kelas"`
	KelasID int64    `json:"kelas_id"`
	Errors  []string `json:"errors"`
}

// importPreview membaca file Excel dan memeriksa tiap baris tanpa menyimpan apa pun.
func (a *App) importPreview(w http.ResponseWriter, r *http.Request, u *User) {
	r.Body = http.MaxBytesReader(w, r.Body, 5<<20)
	file, hdr, err := r.FormFile("file")
	if err != nil {
		fail(w, 422, "Pilih file Excel (.xlsx) maksimal 5 MB.")
		return
	}
	defer file.Close()
	if !strings.HasSuffix(strings.ToLower(hdr.Filename), ".xlsx") {
		fail(w, 422, "Format file harus .xlsx.")
		return
	}
	f, err := excelize.OpenReader(file, excelize.Options{UnzipSizeLimit: 50 << 20, UnzipXMLSizeLimit: 20 << 20})
	if err != nil {
		fail(w, 422, "File tidak bisa dibaca sebagai Excel.")
		return
	}
	defer f.Close()
	sheet := f.GetSheetName(f.GetActiveSheetIndex())
	raw, err := f.GetRows(sheet)
	if err != nil {
		fail(w, 422, "Sheet pertama tidak bisa dibaca.")
		return
	}
	if len(raw) > 5001 {
		fail(w, 422, "Maksimal 5.000 baris per file.")
		return
	}

	kelasMap := map[string]int64{}
	kr, err := a.db.Query(r.Context(), `select nama_kelas, id from kelas`)
	if err != nil {
		serverError(w, err)
		return
	}
	for kr.Next() {
		var n string
		var id int64
		if err := kr.Scan(&n, &id); err != nil {
			serverError(w, err)
			return
		}
		kelasMap[n] = id
	}
	ada := map[string]bool{}
	nr, err := a.db.Query(r.Context(), `select nim from mahasiswa where deleted_at is null`)
	if err != nil {
		serverError(w, err)
		return
	}
	for nr.Next() {
		var n string
		if err := nr.Scan(&n); err != nil {
			serverError(w, err)
			return
		}
		ada[n] = true
	}

	col := func(row []string, i int) string {
		if i < len(row) {
			return strings.TrimSpace(row[i])
		}
		return ""
	}
	rows := []barisImport{}
	jumlahNIM := map[string]int{}
	for i, row := range raw {
		if i == 0 || strings.TrimSpace(strings.Join(row, "")) == "" {
			continue
		}
		b := barisImport{Baris: i + 1, Nama: col(row, 0), NIM: col(row, 1), Jurusan: col(row, 2), Kelas: strings.ToUpper(col(row, 3)), Errors: []string{}}
		jumlahNIM[b.NIM]++
		rows = append(rows, b)
	}
	valid := 0
	for i := range rows {
		b := &rows[i]
		if !textLen(b.Nama, 1, 255) {
			b.Errors = append(b.Errors, "Nama kosong")
		}
		switch {
		case b.NIM == "":
			b.Errors = append(b.Errors, "NIM kosong")
		case len(b.NIM) > 50:
			b.Errors = append(b.Errors, "NIM terlalu panjang")
		case jumlahNIM[b.NIM] > 1:
			b.Errors = append(b.Errors, "NIM dobel di file")
		case ada[b.NIM]:
			b.Errors = append(b.Errors, "NIM sudah terdaftar")
		}
		if !textLen(b.Jurusan, 1, 255) {
			b.Errors = append(b.Errors, "Jurusan kosong")
		}
		if b.Kelas == "" {
			b.Errors = append(b.Errors, "Kelas kosong")
		} else if b.KelasID = kelasMap[b.Kelas]; b.KelasID == 0 {
			b.Errors = append(b.Errors, "Kelas tidak ditemukan")
		}
		if len(b.Errors) == 0 {
			valid++
		}
	}
	data, _ := json.Marshal(rows)
	var token string
	if err := a.db.QueryRow(r.Context(), `insert into import_tertunda (token, user_id, rows, expires_at)
		values (gen_random_uuid(), $1, $2, now() + interval '30 minutes') returning token::text`, u.ID, data).Scan(&token); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"token": token, "rows": rows, "total": len(rows), "valid": valid, "error": len(rows) - valid})
}

func (a *App) importSimpan(w http.ResponseWriter, r *http.Request, u *User) {
	var in struct {
		Token string `json:"token"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	var n int64
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var raw []byte
		if err := tx.QueryRow(r.Context(), `delete from import_tertunda where token::text = $1 and user_id = $2 and expires_at > now() returning rows`,
			in.Token, u.ID).Scan(&raw); err != nil {
			return err
		}
		var rows []barisImport
		if err := json.Unmarshal(raw, &rows); err != nil {
			return err
		}
		var nama, nim, jur []string
		var kelas []int64
		for _, b := range rows {
			if len(b.Errors) == 0 {
				nama, nim, jur, kelas = append(nama, b.Nama), append(nim, b.NIM), append(jur, b.Jurusan), append(kelas, b.KelasID)
			}
		}
		// NIM yang keburu terdaftar sejak pratinjau dilewati, bukan menggagalkan semuanya.
		tag, err := tx.Exec(r.Context(), `insert into mahasiswa (nama, nim, jurusan, kelas_id)
			select x.nama, x.nim, x.jur, x.kelas from unnest($1::text[], $2::text[], $3::text[], $4::bigint[]) as x(nama, nim, jur, kelas)
			join kelas k on k.id = x.kelas
			on conflict (nim) where deleted_at is null do nothing`, nama, nim, jur, kelas)
		if err != nil {
			return err
		}
		n = tag.RowsAffected()
		return catat(r.Context(), tx, u, "mahasiswa.import", sprintf("%d mahasiswa dari Excel", n))
	})
	if notFound(err) {
		fail(w, 410, "Pratinjau import sudah kedaluwarsa. Unggah ulang file Excel-nya.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": sprintf("%d mahasiswa berhasil diimpor.", n)})
}
