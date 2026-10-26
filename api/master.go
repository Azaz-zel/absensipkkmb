package main

import (
	"net/http"
	"regexp"
	"strings"
	"time"
)

// ---------- Mahasiswa ----------

func (a *App) mahasiswaIndex(w http.ResponseWriter, r *http.Request, u *User) {
	q := where{}
	q.add("m.deleted_at is null")
	if s := qStr(r, "q"); s != "" {
		q.add("(m.nama ilike ? or m.nim ilike ?)", likePattern(s), likePattern(s))
	}
	if v := qInt(r, "kelas_id"); v > 0 {
		q.add("m.kelas_id = ?", v)
	}
	if v := qStr(r, "jurusan"); v != "" {
		q.add("m.jurusan = ?", v)
	}
	p := page(r)
	rows, err := a.db.Query(r.Context(), `select m.id, m.nama, m.nim, m.jurusan, m.kelas_id, k.nama_kelas, k.kategori::text, count(*) over()::int
		from mahasiswa m join kelas k on k.id = m.kelas_id where `+q.sql()+
		sprintf(` order by m.nama limit %d offset %d`, perHalaman, (p-1)*perHalaman), q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	data := []M{}
	total := 0
	for rows.Next() {
		var id, kid int64
		var nama, nim, jur, kn, kat string
		if err := rows.Scan(&id, &nama, &nim, &jur, &kid, &kn, &kat, &total); err != nil {
			serverError(w, err)
			return
		}
		data = append(data, M{"id": id, "nama": nama, "nim": nim, "jurusan": jur, "kelas_id": kid, "kelas": kn, "kategori": kat})
	}
	var semua int
	if err := a.db.QueryRow(r.Context(), `select count(*)::int from mahasiswa where deleted_at is null`).Scan(&semua); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"data": data, "total": total, "page": p, "per_page": perHalaman, "semua": semua})
}

type mahasiswaIn struct {
	Nama    string `json:"nama"`
	NIM     string `json:"nim"`
	Jurusan string `json:"jurusan"`
	KelasID int64  `json:"kelas_id"`
}

func (a *App) validasiMahasiswa(r *http.Request, in *mahasiswaIn, abaikanID int64) (Errs, error) {
	e := Errs{}
	in.Nama, in.NIM, in.Jurusan = strings.TrimSpace(in.Nama), strings.TrimSpace(in.NIM), strings.TrimSpace(in.Jurusan)
	if !textLen(in.Nama, 1, 255) {
		e["nama"] = "Isi nama mahasiswa."
	}
	if !textLen(in.NIM, 1, 50) {
		e["nim"] = "Isi NIM (maksimal 50 karakter)."
	}
	if !textLen(in.Jurusan, 1, 255) {
		e["jurusan"] = "Isi jurusan."
	}
	var ada, nimDipakai bool
	err := a.db.QueryRow(r.Context(), `select exists(select 1 from kelas where id=$1),
		exists(select 1 from mahasiswa where nim=$2 and deleted_at is null and id <> $3)`, in.KelasID, in.NIM, abaikanID).Scan(&ada, &nimDipakai)
	if !ada {
		e["kelas_id"] = "Pilih kelas."
	}
	if nimDipakai {
		e["nim"] = "NIM ini sudah terdaftar."
	}
	return e, err
}

func (a *App) mahasiswaBuat(w http.ResponseWriter, r *http.Request, u *User) {
	var in mahasiswaIn
	if !readJSON(w, r, &in) {
		return
	}
	e, err := a.validasiMahasiswa(r, &in, 0)
	if err != nil {
		serverError(w, err)
		return
	}
	if e.send(w) {
		return
	}
	_, err = a.db.Exec(r.Context(), `insert into mahasiswa (nama, nim, jurusan, kelas_id) values ($1,$2,$3,$4)`, in.Nama, in.NIM, in.Jurusan, in.KelasID)
	if isUnique(err) {
		Errs{"nim": "NIM ini sudah terdaftar."}.send(w)
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 201, M{"message": in.Nama + " ditambahkan."})
}

func (a *App) mahasiswaUbah(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in mahasiswaIn
	if !readJSON(w, r, &in) {
		return
	}
	e, err := a.validasiMahasiswa(r, &in, id)
	if err != nil {
		serverError(w, err)
		return
	}
	if e.send(w) {
		return
	}
	tag, err := a.db.Exec(r.Context(), `update mahasiswa set nama=$2, nim=$3, jurusan=$4, kelas_id=$5, updated_at=now()
		where id=$1 and deleted_at is null`, id, in.Nama, in.NIM, in.Jurusan, in.KelasID)
	if isUnique(err) {
		Errs{"nim": "NIM ini sudah terdaftar."}.send(w)
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if tag.RowsAffected() == 0 {
		fail(w, 404, "Data mahasiswa tidak ditemukan.")
		return
	}
	writeJSON(w, 200, M{"message": "Data " + in.Nama + " diperbarui."})
}

func (a *App) hapusMahasiswa(r *http.Request, u *User, cond string, ket string, args ...any) (int64, error) {
	var n int64
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		tag, err := tx.Exec(r.Context(), `update mahasiswa set deleted_at=now() where deleted_at is null and `+cond, args...)
		if err != nil {
			return err
		}
		n = tag.RowsAffected()
		if n == 0 {
			return nil
		}
		return catat(r.Context(), tx, u, "mahasiswa.hapus", sprintf("%d mahasiswa: %s", n, ket))
	})
	return n, err
}

func (a *App) mahasiswaHapus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
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
	if _, err := a.hapusMahasiswa(r, u, "id = $1", nama+" ("+nim+")", id); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": nama + " dihapus."})
}

// mahasiswaHapusMassal: terpilih (ids), satu kelas (kelas_id), atau semua (konfirmasi teks).
func (a *App) mahasiswaHapusMassal(w http.ResponseWriter, r *http.Request, u *User) {
	var in struct {
		IDs        []int64 `json:"ids"`
		KelasID    int64   `json:"kelas_id"`
		Konfirmasi string  `json:"konfirmasi"`
	}
	if !readJSON(w, r, &in) {
		return
	}
	var n int64
	var err error
	switch {
	case len(in.IDs) > 0 && len(in.IDs) <= 1000:
		n, err = a.hapusMahasiswa(r, u, "id = any($1)", "terpilih", in.IDs)
	case in.KelasID > 0:
		var nama string
		if err := a.db.QueryRow(r.Context(), `select nama_kelas from kelas where id=$1`, in.KelasID).Scan(&nama); err != nil {
			fail(w, 404, "Kelas tidak ditemukan.")
			return
		}
		if in.Konfirmasi != nama {
			fail(w, 422, "Ketik nama kelas untuk konfirmasi.")
			return
		}
		n, err = a.hapusMahasiswa(r, u, "kelas_id = $1", "seluruh kelas "+nama, in.KelasID)
	case in.Konfirmasi == "HAPUS SEMUA DATA":
		n, err = a.hapusMahasiswa(r, u, "true", "seluruh data")
	default:
		fail(w, 422, "Pilih data yang mau dihapus.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": sprintf("%d data mahasiswa dihapus.", n)})
}

// ---------- Kelas ----------

func (a *App) kelasIndex(w http.ResponseWriter, r *http.Request, u *User) {
	rows, err := a.db.Query(r.Context(), `select k.id, k.nama_kelas, k.kategori::text, k.kapasitas,
		(select count(*) from mahasiswa m where m.kelas_id=k.id and m.deleted_at is null)::int,
		coalesce((select array_agg(us.name order by us.name) from kelas_user ku join users us on us.id=ku.user_id
		   where ku.kelas_id=k.id and us.role='pj'), '{}')
		from kelas k order by k.kategori, k.nama_kelas`)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	data := []M{}
	for rows.Next() {
		var id int64
		var nama, kat string
		var kap *int
		var maba int
		var pj []string
		if err := rows.Scan(&id, &nama, &kat, &kap, &maba, &pj); err != nil {
			serverError(w, err)
			return
		}
		data = append(data, M{"id": id, "nama": nama, "kategori": kat, "kapasitas": kap, "maba": maba, "pj": pj})
	}
	writeJSON(w, 200, M{"data": data})
}

type kelasIn struct {
	Nama      string `json:"nama"`
	Kategori  string `json:"kategori"`
	Kapasitas *int   `json:"kapasitas"`
}

var (
	reKelasPagi  = regexp.MustCompile(`^[A-Z]$`)
	reKelasMalam = regexp.MustCompile(`^[A-Z]{2}$`)
)

func (k *kelasIn) validate() Errs {
	e := Errs{}
	k.Nama = strings.ToUpper(strings.TrimSpace(k.Nama))
	switch k.Kategori {
	case "pagi":
		if !reKelasPagi.MatchString(k.Nama) {
			e["nama"] = "Nama kelas pagi berupa 1 huruf (A–Z)."
		}
	case "malam":
		if !reKelasMalam.MatchString(k.Nama) {
			e["nama"] = "Nama kelas malam berupa 2 huruf (AA–ZZ)."
		}
	default:
		e["kategori"] = "Pilih pagi atau malam."
	}
	if k.Kapasitas != nil && (*k.Kapasitas < 1 || *k.Kapasitas > 10000) {
		e["kapasitas"] = "Kapasitas minimal 1."
	}
	return e
}

func (a *App) kelasBuat(w http.ResponseWriter, r *http.Request, u *User) {
	var in kelasIn
	if !readJSON(w, r, &in) || in.validate().send(w) {
		return
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		var id int64
		if err := tx.QueryRow(r.Context(), `insert into kelas (nama_kelas, kategori, kapasitas) values ($1,$2,$3) returning id`,
			in.Nama, in.Kategori, in.Kapasitas).Scan(&id); err != nil {
			return err
		}
		// Sesi yang sudah ada ikut punya baris untuk kelas baru ini.
		_, err := tx.Exec(r.Context(), `insert into sesi_kelas (sesi_absensi_id, kelas_id) select id, $1 from sesi_absensi`, id)
		return err
	})
	if isUnique(err) {
		Errs{"nama": "Kelas " + in.Nama + " sudah ada."}.send(w)
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 201, M{"message": "Kelas " + in.Nama + " ditambahkan."})
}

func (a *App) kelasUbah(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in kelasIn
	if !readJSON(w, r, &in) || in.validate().send(w) {
		return
	}
	tag, err := a.db.Exec(r.Context(), `update kelas set nama_kelas=$2, kategori=$3, kapasitas=$4, updated_at=now() where id=$1`,
		id, in.Nama, in.Kategori, in.Kapasitas)
	if isUnique(err) {
		Errs{"nama": "Kelas " + in.Nama + " sudah ada."}.send(w)
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if tag.RowsAffected() == 0 {
		fail(w, 404, "Kelas tidak ditemukan.")
		return
	}
	writeJSON(w, 200, M{"message": "Kelas " + in.Nama + " diperbarui."})
}

func (a *App) kelasHapus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var nama string
	var maba int
	err := a.db.QueryRow(r.Context(), `select nama_kelas, (select count(*) from mahasiswa where kelas_id=$1)::int from kelas where id=$1`, id).Scan(&nama, &maba)
	if notFound(err) {
		fail(w, 404, "Kelas tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if maba > 0 {
		fail(w, 409, sprintf("Kelas %s masih punya %d data mahasiswa (termasuk yang sudah dihapus). Pindahkan mahasiswanya dulu.", nama, maba))
		return
	}
	if _, err := a.db.Exec(r.Context(), `delete from kelas where id=$1`, id); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Kelas " + nama + " dihapus."})
}

// ---------- Akun pengguna ----------

func (a *App) userIndex(w http.ResponseWriter, r *http.Request, u *User) {
	rows, err := a.db.Query(r.Context(), `select us.id, us.name, us.email, us.username, us.role::text, us.status::text,
		coalesce((select array_agg(k.id order by k.kategori, k.nama_kelas) from kelas_user ku join kelas k on k.id=ku.kelas_id where ku.user_id=us.id), '{}'),
		coalesce((select array_agg(k.nama_kelas order by k.kategori, k.nama_kelas) from kelas_user ku join kelas k on k.id=ku.kelas_id where ku.user_id=us.id), '{}')
		from users us order by us.role, us.name`)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	data := []M{}
	for rows.Next() {
		var x User
		var kids []int64
		var kn []string
		if err := rows.Scan(&x.ID, &x.Name, &x.Email, &x.Username, &x.Role, &x.Status, &kids, &kn); err != nil {
			serverError(w, err)
			return
		}
		data = append(data, M{"id": x.ID, "name": x.Name, "email": x.Email, "username": x.Username, "role": x.Role,
			"status": x.Status, "kelas_ids": kids, "kelas": kn, "saya": x.ID == u.ID})
	}
	writeJSON(w, 200, M{"data": data})
}

type userIn struct {
	Name     string  `json:"name"`
	Email    string  `json:"email"`
	Username string  `json:"username"`
	Password string  `json:"password"`
	Role     string  `json:"role"`
	Status   string  `json:"status"`
	KelasIDs []int64 `json:"kelas_ids"`
}

var reUsername = regexp.MustCompile(`^[a-zA-Z0-9._-]{3,50}$`)

func (in *userIn) validate(baru bool) Errs {
	e := Errs{}
	in.Name, in.Email, in.Username = strings.TrimSpace(in.Name), strings.TrimSpace(in.Email), strings.TrimSpace(in.Username)
	if !textLen(in.Name, 1, 255) {
		e["name"] = "Isi nama."
	}
	if !validEmail(in.Email) {
		e["email"] = "Email tidak valid."
	}
	if !reUsername.MatchString(in.Username) {
		e["username"] = "Username 3–50 karakter: huruf, angka, titik, strip."
	}
	if (baru || in.Password != "") && (len(in.Password) < 8 || len(in.Password) > 72) {
		e["password"] = "Kata sandi 8–72 karakter."
	}
	if !oneOf(in.Role, "admin", "pj", "manajer") {
		e["role"] = "Pilih peran."
	}
	if !oneOf(in.Status, "aktif", "nonaktif") {
		e["status"] = "Pilih status."
	}
	if len(in.KelasIDs) > 200 {
		e["kelas_ids"] = "Terlalu banyak kelas."
	}
	return e
}

func uniqueUserErr(w http.ResponseWriter, err error) bool {
	if !isUnique(err) {
		return false
	}
	msg := err.Error()
	switch {
	case strings.Contains(msg, "username"):
		Errs{"username": "Username sudah dipakai."}.send(w)
	default:
		Errs{"email": "Email sudah dipakai."}.send(w)
	}
	return true
}

func simpanKelasUser(r *http.Request, tx pgxTx, userID int64, role string, ids []int64) error {
	if _, err := tx.Exec(r.Context(), `delete from kelas_user where user_id=$1`, userID); err != nil {
		return err
	}
	if role != "pj" || len(ids) == 0 {
		return nil
	}
	_, err := tx.Exec(r.Context(), `insert into kelas_user (user_id, kelas_id) select $1, id from kelas where id = any($2)`, userID, ids)
	return err
}

func (a *App) userBuat(w http.ResponseWriter, r *http.Request, u *User) {
	var in userIn
	if !readJSON(w, r, &in) || in.validate(true).send(w) {
		return
	}
	hash, err := hashPassword(in.Password)
	if err != nil {
		serverError(w, err)
		return
	}
	err = inTx(r.Context(), a, func(tx pgxTx) error {
		var id int64
		if err := tx.QueryRow(r.Context(), `insert into users (name, email, username, password, role, status) values ($1,lower($2),$3,$4,$5,$6) returning id`,
			in.Name, in.Email, in.Username, hash, in.Role, in.Status).Scan(&id); err != nil {
			return err
		}
		return simpanKelasUser(r, tx, id, in.Role, in.KelasIDs)
	})
	if uniqueUserErr(w, err) {
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 201, M{"message": "Akun " + in.Name + " dibuat."})
}

func (a *App) userUbah(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	var in userIn
	if !readJSON(w, r, &in) || in.validate(false).send(w) {
		return
	}
	// Admin tidak boleh mengunci dirinya sendiri keluar.
	if id == u.ID && (in.Role != "admin" || in.Status != "aktif") {
		fail(w, 422, "Kamu tidak bisa menurunkan peran atau menonaktifkan akunmu sendiri.")
		return
	}
	var hash *string
	if in.Password != "" {
		h, err := hashPassword(in.Password)
		if err != nil {
			serverError(w, err)
			return
		}
		hash = &h
	}
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		tag, err := tx.Exec(r.Context(), `update users set name=$2, email=lower($3), username=$4, role=$5, status=$6,
			password=coalesce($7, password), updated_at=now() where id=$1`, id, in.Name, in.Email, in.Username, in.Role, in.Status, hash)
		if err != nil {
			return err
		}
		if tag.RowsAffected() == 0 {
			return errNotFound
		}
		// Ganti sandi atau nonaktif = semua sesi login akun itu diakhiri.
		if hash != nil || in.Status != "aktif" {
			if _, err := tx.Exec(r.Context(), `delete from sesi_login where user_id=$1 and user_id <> $2`, id, u.ID); err != nil {
				return err
			}
		}
		return simpanKelasUser(r, tx, id, in.Role, in.KelasIDs)
	})
	if err == errNotFound {
		fail(w, 404, "Akun tidak ditemukan.")
		return
	}
	if uniqueUserErr(w, err) {
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Akun " + in.Name + " diperbarui."})
}

func (a *App) userHapus(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	if id == u.ID {
		fail(w, 422, "Kamu tidak bisa menghapus akunmu sendiri.")
		return
	}
	var nama string
	err := a.db.QueryRow(r.Context(), `delete from users where id=$1 returning name`, id).Scan(&nama)
	if notFound(err) {
		fail(w, 404, "Akun tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Akun " + nama + " dihapus."})
}

func (a *App) userToggle(w http.ResponseWriter, r *http.Request, u *User) {
	id, ok := pathID(w, r, "id")
	if !ok {
		return
	}
	if id == u.ID {
		fail(w, 422, "Kamu tidak bisa menonaktifkan akunmu sendiri.")
		return
	}
	var st string
	err := inTx(r.Context(), a, func(tx pgxTx) error {
		if err := tx.QueryRow(r.Context(), `update users set status = case when status='aktif' then 'nonaktif'::status_akun else 'aktif'::status_akun end,
			updated_at=now() where id=$1 returning status::text`, id).Scan(&st); err != nil {
			return err
		}
		_, err := tx.Exec(r.Context(), `delete from sesi_login where user_id=$1`, id)
		return err
	})
	if notFound(err) {
		fail(w, 404, "Akun tidak ditemukan.")
		return
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, M{"message": "Akun sekarang " + st + ".", "status": st})
}

// ---------- Riwayat ----------

var labelAksi = map[string]string{
	"absensi.ubah": "Kehadiran diubah", "absensi.hapus": "Kehadiran dihapus",
	"sesi.buka": "Absensi kelas dibuka", "sesi.tutup": "Absensi kelas ditutup",
	"sesi.ubah": "Sesi diubah", "sesi.hapus": "Sesi dihapus",
	"pelanggaran.catat": "Pelanggaran dicatat", "pelanggaran.ubah": "Pelanggaran diubah",
	"pelanggaran.hapus": "Pelanggaran dihapus", "mahasiswa.hapus": "Mahasiswa dihapus",
	"mahasiswa.import": "Mahasiswa diimpor",
}

func (a *App) riwayatIndex(w http.ResponseWriter, r *http.Request, u *User) {
	q := where{}
	if s := qStr(r, "q"); s != "" {
		q.add("(rp.keterangan ilike ? or rp.nama_pelaku ilike ?)", likePattern(s), likePattern(s))
	}
	if v := qStr(r, "aksi"); labelAksi[v] != "" {
		q.add("rp.aksi = ?", v)
	}
	if v := qInt(r, "user_id"); v > 0 {
		q.add("rp.user_id = ?", v)
	}
	if t, ok := parseDate(qStr(r, "tanggal")); ok {
		q.add("(rp.created_at at time zone 'Asia/Makassar')::date = ?", t)
	}
	p := page(r)
	const per = 50
	rows, err := a.db.Query(r.Context(), `select rp.id, rp.created_at, rp.aksi, rp.keterangan, rp.nama_pelaku, us.role::text, count(*) over()::int
		from riwayat_perubahan rp left join users us on us.id = rp.user_id where `+q.sql()+
		sprintf(` order by rp.id desc limit %d offset %d`, per, (p-1)*per), q.args...)
	if err != nil {
		serverError(w, err)
		return
	}
	defer rows.Close()
	data := []M{}
	total := 0
	for rows.Next() {
		var id int64
		var at time.Time
		var aksi, ket, pelaku string
		var role *string
		if err := rows.Scan(&id, &at, &aksi, &ket, &pelaku, &role, &total); err != nil {
			serverError(w, err)
			return
		}
		label := labelAksi[aksi]
		if label == "" {
			label = aksi
		}
		data = append(data, M{"id": id, "created_at": at, "aksi": aksi, "label": label, "keterangan": ket, "pelaku": pelaku, "role": role})
	}
	var hariIni int
	if err := a.db.QueryRow(r.Context(), `select count(*)::int from riwayat_perubahan
		where (created_at at time zone 'Asia/Makassar')::date = (now() at time zone 'Asia/Makassar')::date`).Scan(&hariIni); err != nil {
		serverError(w, err)
		return
	}
	users := []M{}
	ur, err := a.db.Query(r.Context(), `select id, name, role::text from users order by name`)
	if err != nil {
		serverError(w, err)
		return
	}
	for ur.Next() {
		var id int64
		var n, ro string
		if err := ur.Scan(&id, &n, &ro); err != nil {
			serverError(w, err)
			return
		}
		users = append(users, M{"id": id, "name": n, "role": ro})
	}
	writeJSON(w, 200, M{"data": data, "total": total, "page": p, "per_page": per, "hari_ini": hariIni, "users": users, "aksi": labelAksi})
}
