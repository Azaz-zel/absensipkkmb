import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { angka, fmtPersen, jam, persen, sapaan, tgl, useApi, useAuth, type MabaPoin } from '../lib'
import { Bar, Icon, Loading, SanksiChip, StatusChip } from '../ui'
import { useLogout } from '../Layout'

type KelasSesi = { id: number; nama: string; kategori: string; maba: number; terisi: number; status: string; pj: string[] }
type Sesi = { id: number; nama: string; tanggal: string; dibuka: number; ditutup: number; belum_dibuka: number; status: string }

function Sapa({ nama, sub }: { nama: string; sub: string }) {
  const logout = useLogout()
  return (
    <div className="top">
      <div>
        <h1 className="h1">{sapaan()}, <span className="serif">{nama.split(' ')[0]}</span></h1>
        <p className="sub">{sub}</p>
      </div>
      <button className="pill sm show-m" onClick={logout}>Keluar</button>
    </div>
  )
}

export default function Dashboard() {
  const { user } = useAuth()
  if (user!.role === 'admin') return <Admin />
  if (user!.role === 'manajer') return <Manajer />
  return <PJ />
}

// ---------------- Admin ----------------

type AdminData = {
  total_maba: number
  sesi?: Sesi
  progres?: { hadir: number; izin: number; sakit: number; alpa: number }
  kelas?: KelasSesi[]
  perlu_sanksi: MabaPoin[]
  tren: { nama: string; tanggal: string; semua: number | null; pagi: number | null; malam: number | null; alpa: number }[]
}

function Admin() {
  const { user } = useAuth()
  const nav = useNavigate()
  const { data, error, reload } = useApi<AdminData>('/dashboard')
  const [kat, setKat] = useState<'semua' | 'pagi' | 'malam'>('semua')
  const [tab, setTab] = useState<'dibuka' | 'belum_dibuka'>('dibuka')
  const [q, setQ] = useState('')

  const cari = (e: FormEvent) => {
    e.preventDefault()
    if (q.trim()) nav('/rekap?q=' + encodeURIComponent(q.trim()))
  }
  const today = tgl(new Date().toISOString(), { hari: true, tahun: true })

  return (
    <>
      <div className="top">
        <div>
          <h1 className="h1">{sapaan()}, <span className="serif">{user!.name.split(' ')[0]}</span></h1>
          <p className="sub">{data?.sesi ? `${data.sesi.nama} · ${today}` : today}</p>
        </div>
        <form onSubmit={cari} className="hide-m" style={{ width: 300 }} role="search">
          <label className="search">
            <Icon name="search" size={16} /><span className="sr">Cari maba</span>
            <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Cari nama maba atau NIM" />
          </label>
        </form>
      </div>
      <div className="hd">
        <div className="row" role="group" aria-label="Kategori kelas">
          {(['semua', 'pagi', 'malam'] as const).map(k => (
            <button key={k} className={'pill' + (kat === k ? ' on' : '')} aria-pressed={kat === k} onClick={() => setKat(k)}>
              {k === 'semua' ? 'Semua kelas' : k === 'pagi' ? 'Pagi' : 'Malam'}
            </button>
          ))}
        </div>
        <div className="row hide-m">
          <a className="pill" href="/api/export/rekap" download>Export rekap</a>
          <Link className="cta" to="/sesi">Kelola sesi</Link>
        </div>
      </div>
      {!data ? <Loading error={error} retry={reload} /> : !data.sesi ? (
        <div className="glass card">
          <h2 className="h2">Belum ada sesi absensi</h2>
          <p className="muted">Buat sesi pertama, lalu buka absensi kelasnya supaya PJ bisa mulai mengisi.</p>
          <div><Link className="cta" to="/sesi">Buat sesi</Link></div>
        </div>
      ) : (
        <AdminBody d={data} kat={kat} tab={tab} setTab={setTab} />
      )}
    </>
  )
}

function AdminBody({ d, kat, tab, setTab }: { d: AdminData; kat: string; tab: string; setTab: (t: 'dibuka' | 'belum_dibuka') => void }) {
  const p = d.progres!
  const kelas = d.kelas!.filter(k => kat === 'semua' || k.kategori === kat)
  const sudah = p.hadir + p.izin + p.sakit + p.alpa
  const belumSelesai = d.kelas!.filter(k => k.terisi < k.maba && k.status !== 'ditutup')
  const sisa = kelas.filter(k => k.terisi < k.maba && k.status === tab)
  const nDibuka = kelas.filter(k => k.terisi < k.maba && k.status === 'dibuka').length
  const nBelum = kelas.filter(k => k.terisi < k.maba && k.status === 'belum_dibuka').length
  const parts = [
    { k: 'Hadir', v: p.hadir, c: 'var(--hadir)' },
    { k: 'Izin', v: p.izin, c: 'var(--izin)' },
    { k: 'Sakit', v: p.sakit, c: 'var(--sakit)' },
    { k: 'Alpa', v: p.alpa, c: 'var(--alpa)' },
  ]
  return (
    <>
      <div className="grid3">
        <section className="glass card" style={{ gap: 18 }}>
          <div className="hd"><h2 className="h2">Progres {d.sesi!.nama}</h2><StatusChip s={d.sesi!.status} /></div>
          <div>
            <div className="huge">{angka(sudah)}<span className="muted" style={{ fontSize: 20, fontWeight: 500, letterSpacing: 0 }}> / {angka(d.total_maba)} maba</span></div>
            <p className="muted" style={{ marginTop: 8, fontSize: 14 }}>
              {sudah >= d.total_maba ? 'Semua maba sudah diabsen.' : `sudah diabsen. ${angka(d.total_maba - sudah)} lagi ada di ${belumSelesai.length} kelas yang belum selesai.`}
            </p>
          </div>
          <div className="stack" role="img" aria-label={parts.map(x => `${x.k} ${x.v}`).join(', ') + `, belum diabsen ${Math.max(0, d.total_maba - sudah)}`}>
            {parts.map(x => x.v > 0 && <div key={x.k} style={{ width: persen(x.v, d.total_maba) + '%', background: x.c }} />)}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0,1fr))', gap: 8 }}>
            {parts.map(x => (
              <div key={x.k} className="tile" style={{ padding: '10px 12px' }}>
                <div className="lbl legend"><span className="dot" style={{ background: x.c }} />{x.k}</div>
                <div className="num" style={{ fontSize: 20, fontWeight: 600, marginTop: 4 }}>{angka(x.v)}</div>
              </div>
            ))}
          </div>
        </section>

        <section className="glass card">
          <div className="hd"><h2 className="h2">Kelas yang belum selesai</h2><span className="s num">{nDibuka + nBelum} dari {kelas.length}</span></div>
          <div className="row" role="group" aria-label="Status kelas">
            <button className={'pill sm' + (tab === 'dibuka' ? ' on' : '')} aria-pressed={tab === 'dibuka'} onClick={() => setTab('dibuka')}>Dibuka · {nDibuka}</button>
            <button className={'pill sm' + (tab === 'belum_dibuka' ? ' on' : '')} aria-pressed={tab === 'belum_dibuka'} onClick={() => setTab('belum_dibuka')}>Belum dibuka · {nBelum}</button>
          </div>
          <div className="list">
            {sisa.length === 0 && <p className="empty">{tab === 'dibuka' ? 'Semua kelas yang dibuka sudah lengkap.' : 'Tidak ada kelas yang menunggu dibuka.'}</p>}
            {sisa.slice(0, 5).map(k => (
              <Link key={k.id} className="li" to={`/absensi/${d.sesi!.id}/${k.id}`}>
                <div className="grow1"><div className="t">{k.nama} · {k.kategori === 'pagi' ? 'Pagi' : 'Malam'}</div><div className="s ellipsis">{k.pj.length ? 'PJ ' + k.pj.join(', ') : 'Belum ada PJ'}</div></div>
                <div style={{ width: 80 }}><Bar value={persen(k.terisi, k.maba)} /></div>
                <span className="num" style={{ fontSize: 13.5, fontWeight: 600, width: 48, textAlign: 'right' }}>{k.terisi}/{k.maba}</span>
              </Link>
            ))}
          </div>
          {sisa.length > 5 && <Link to="/sesi" style={{ fontSize: 13.5, fontWeight: 600 }}>Lihat {sisa.length - 5} kelas lainnya</Link>}
        </section>

        <section className="glass card">
          <div className="hd"><h2 className="h2">Perlu sanksi</h2><Link className="pill sm" to="/poin">Semua poin</Link></div>
          {d.perlu_sanksi.length === 0 ? <p className="empty">Belum ada maba yang kena poin.</p> : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0,1fr))', gap: 10 }}>
              {d.perlu_sanksi.slice(0, 4).map(m => (
                <Link key={m.id} className="tile" to={`/poin?id=${m.id}`} style={{ padding: 14, textDecoration: 'none', color: 'var(--ink)', display: 'flex', flexDirection: 'column', gap: 8 }}>
                  <div className="num" style={{ fontSize: 26, fontWeight: 600, letterSpacing: '-.02em' }}>{m.total_poin}<span className="s"> poin</span></div>
                  <div className="t ellipsis" style={{ fontSize: 13.5 }}>{m.nama}</div>
                  <div><SanksiChip s={m.sanksi} /></div>
                </Link>
              ))}
            </div>
          )}
        </section>
      </div>
      <Tren tren={d.tren} kat={kat as 'semua' | 'pagi' | 'malam'} />
    </>
  )
}

function Tren({ tren, kat }: { tren: AdminData['tren']; kat: 'semua' | 'pagi' | 'malam' }) {
  const pts = tren.map(t => ({ ...t, v: t[kat] })).filter(t => t.v !== null) as (AdminData['tren'][number] & { v: number })[]
  if (pts.length === 0)
    return (
      <section className="glass card">
        <h2 className="h2">Persentase hadir per sesi</h2>
        <p className="muted">Grafik muncul setelah ada sesi yang semua kelasnya sudah ditutup.</p>
      </section>
    )
  const min = Math.max(0, Math.floor(Math.min(...pts.map(p => p.v)) / 5) * 5 - 5)
  const W = 1000, H = 220
  const x = (i: number) => (pts.length === 1 ? W / 2 : (i / (pts.length - 1)) * W)
  const y = (v: number) => H - ((v - min) / (100 - min)) * H
  const line = pts.map((p, i) => `${i ? 'L' : 'M'}${x(i)} ${y(p.v)}`).join(' ')
  const low = pts.reduce((a, b) => (b.v < a.v ? b : a))
  const li = pts.indexOf(low)
  const ticks = [100, min + (100 - min) / 2, min]
  return (
    <section className="glass card">
      <div>
        <h2 className="h2">Persentase hadir per sesi</h2>
        <p className="s" style={{ marginTop: 4 }}>Hanya sesi yang semua kelasnya sudah ditutup{kat !== 'semua' ? ` · kelas ${kat}` : ''}.</p>
      </div>
      <div style={{ display: 'flex', gap: 12 }}>
        <div className="s num" style={{ position: 'relative', width: 40, height: H, flexShrink: 0 }}>
          {ticks.map(t => <span key={t} style={{ position: 'absolute', right: 0, top: y(t) - 8 }}>{Math.round(t)}%</span>)}
        </div>
        <div style={{ position: 'relative', flex: 1, minWidth: 0, height: H }}>
          <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" width="100%" height={H} role="img"
            aria-label={'Persentase hadir: ' + pts.map(p => `${p.nama} ${fmtPersen(p.v)}`).join(', ')} style={{ display: 'block', overflow: 'visible' }}>
            <defs><linearGradient id="area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style={{ stopColor: "var(--plum)", stopOpacity: .26 }} /><stop offset="1" style={{ stopColor: "var(--plum)", stopOpacity: 0 }} /></linearGradient></defs>
            {ticks.map(t => <path key={t} d={`M0 ${y(t)}H${W}`} stroke="rgba(31,27,36,.08)" vectorEffect="non-scaling-stroke" />)}
            {pts.length > 1 && <path d={`${line} L${W} ${H} L0 ${H} Z`} fill="url(#area)" />}
            <path d={line} fill="none" style={{ stroke: "var(--plum)" }} strokeWidth="2.5" vectorEffect="non-scaling-stroke" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
          {pts.map((p, i) => (
            <span key={i} title={`${p.nama}: ${fmtPersen(p.v)}`} style={{ position: 'absolute', left: `${(x(i) / W) * 100}%`, top: y(p.v), width: i === li ? 14 : 8, height: i === li ? 14 : 8, transform: 'translate(-50%,-50%)', borderRadius: '50%', background: '#fff', border: `${i === li ? 3 : 2}px solid var(--plum)` }} />
          ))}
          <div className="glass hide-m" style={{ position: 'absolute', left: `${Math.min(85, Math.max(15, (x(li) / W) * 100))}%`, top: Math.max(0, y(low.v) - 96), transform: 'translateX(-50%)', borderRadius: 14, padding: '10px 14px', whiteSpace: 'nowrap', background: 'rgba(255,255,255,.88)' }}>
            <div className="lbl" style={{ fontSize: 12 }}>{low.nama} · {tgl(low.tanggal)}</div>
            <div className="num" style={{ fontSize: 18, fontWeight: 600 }}>{fmtPersen(low.v)} hadir</div>
            <div className="num" style={{ fontSize: 12.5, color: 'var(--alpa-ink)', fontWeight: 600 }}>{low.alpa} alpa · terendah</div>
          </div>
        </div>
      </div>
      <div className="s num" style={{ display: 'flex', justifyContent: 'space-between', paddingLeft: 52, gap: 4 }}>
        {pts.map((p, i) => <span key={i} className="ellipsis">{p.nama}</span>)}
      </div>
    </section>
  )
}

// ---------------- PJ ----------------

type PJData = {
  sesi: Sesi | null
  kelas: KelasSesi[]
  riwayat: { sesi_id: number; sesi: string; tanggal: string; kelas_id: number; kelas: string; status: string; hadir: number; izin: number; sakit: number; alpa: number }[]
}

function PJ() {
  const { user, kelas: kelasSaya } = useAuth()
  const { data, error, reload } = useApi<PJData>('/dashboard')
  const banyakKelas = kelasSaya.length > 1
  return (
    <>
      <Sapa nama={user!.name} sub={kelasSaya.length ? `PJ kelas ${kelasSaya.map(k => k.nama).join(', ')}` : 'Belum ditugaskan ke kelas mana pun'} />
      {!data ? <Loading error={error} retry={reload} /> : kelasSaya.length === 0 ? (
        <div className="glass card"><p className="t">Akunmu belum ditugaskan ke kelas.</p><p className="muted">Minta admin panitia menambahkan kelasmu di menu Akun pengguna.</p></div>
      ) : !data.sesi ? (
        <div className="glass card"><p className="t">Belum ada sesi absensi.</p><p className="muted">Sesi dibuat oleh admin. Halaman ini otomatis menampilkan sesi terbaru.</p></div>
      ) : (
        <>
          {data.kelas.map(k => {
            const buka = k.status === 'dibuka'
            return (
              <section key={k.id} className="glass card" style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 24, alignItems: 'center', padding: 26 }}>
                <div style={{ flex: '999 1 300px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                  <div className="row"><StatusChip s={k.status} /><span className="s">{data.sesi!.nama} · {tgl(data.sesi!.tanggal, { hari: true })}</span></div>
                  <h2 style={{ fontSize: 26, fontWeight: 600, letterSpacing: '-.02em' }}>Absensi kelas {k.nama}{banyakKelas ? ` · ${k.kategori}` : ' hari ini'}</h2>
                  <div className="row" style={{ gap: 12 }}>
                    <div style={{ width: 220, maxWidth: '50vw' }}><Bar value={persen(k.terisi, k.maba)} /></div>
                    <span className="num t">{k.terisi} dari {k.maba} terisi</span>
                  </div>
                  {!buka && <p className="s">{k.status === 'belum_dibuka' ? 'Admin belum membuka absensi kelas ini.' : 'Absensi sudah ditutup. Kamu masih bisa melihat isinya.'}</p>}
                </div>
                <Link className={buka ? 'cta' : 'pill'} to={`/absensi/${data.sesi!.id}/${k.id}`} style={{ padding: '14px 26px', fontSize: 15 }}>
                  {buka ? (k.terisi ? 'Lanjut isi absensi' : 'Mulai isi absensi') : 'Lihat absensi'}
                </Link>
              </section>
            )
          })}
          <section className="glass card" style={{ gap: 4 }}>
            <div className="hd" style={{ paddingBottom: 6 }}><h2 className="h2">Sesi sebelumnya</h2><span className="s">Ditutup admin, hanya bisa dilihat</span></div>
            {data.riwayat.length === 0 ? <p className="empty">Belum ada sesi sebelumnya.</p> : (
              <table className="tbl cards">
                <thead><tr><th>Sesi</th>{banyakKelas && <th>Kelas</th>}<th className="r">Hadir</th><th className="r">Izin</th><th className="r">Sakit</th><th className="r">Alpa</th><th>Status</th><th><span className="sr">Aksi</span></th></tr></thead>
                <tbody>
                  {data.riwayat.map(r => (
                    <tr key={r.sesi_id + '-' + r.kelas_id}>
                      <td className="first"><span className="t">{r.sesi}</span> <span className="s">· {tgl(r.tanggal, { hari: true })}</span></td>
                      {banyakKelas && <td data-label="Kelas">{r.kelas}</td>}
                      <td className="r num" data-label="Hadir">{r.hadir}</td><td className="r num" data-label="Izin">{r.izin}</td>
                      <td className="r num" data-label="Sakit">{r.sakit}</td><td className="r num" data-label="Alpa">{r.alpa}</td>
                      <td data-label="Status"><StatusChip s={r.status} /></td>
                      <td className="r act"><Link className="ghost" to={`/absensi/${r.sesi_id}/${r.kelas_id}`}>Lihat</Link></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </section>
        </>
      )}
    </>
  )
}

// ---------------- Manajer ----------------

type MjData = {
  total_maba: number; hari_ini: number
  sebaran: Record<'bersih' | 'ringan' | 'sedang' | 'berat' | 'merah', number>
  terbaru: { id: number; mahasiswa_id: number; nama: string; kelas: string; jenis: string; poin: number; created_at: string; pencatat: string | null }[]
}

const LEVELS = [
  { k: 'bersih', label: 'Bersih', rule: '0 poin', c: 'var(--lv-bersih)' },
  { k: 'ringan', label: 'Ringan', rule: '1–5 · video PKKMB Journey', c: 'var(--lv-ringan)' },
  { k: 'sedang', label: 'Sedang', rule: '6–9 · video Edukasi Digitalisasi', c: 'var(--lv-sedang)' },
  { k: 'berat', label: 'Berat', rule: '10–19 · video Problem and Solution', c: 'var(--lv-berat)' },
  { k: 'merah', label: 'Cap merah', rule: '20+ · mengulang PKKMB', c: 'var(--lv-merah)' },
] as const

function Manajer() {
  const { user } = useAuth()
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const { data, error, reload } = useApi<MjData>('/dashboard')
  return (
    <>
      <Sapa nama={user!.name} sub={data ? `${tgl(new Date().toISOString(), { hari: true, tahun: true })} · ${data.hari_ini} pelanggaran dicatat hari ini` : 'Memuat…'} />
      <form className="glass card" role="search" style={{ padding: 26 }} onSubmit={e => { e.preventDefault(); nav('/poin?q=' + encodeURIComponent(q.trim())) }}>
        <h2 style={{ fontSize: 22, fontWeight: 600 }}>Cari maba untuk dicatat</h2>
        <div className="row" style={{ gap: 10 }}>
          <label className="search" style={{ flex: '999 1 260px', minHeight: 52 }}>
            <Icon name="search" /><span className="sr">NIM atau nama</span>
            <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Ketik NIM atau nama maba" style={{ fontSize: 16 }} />
          </label>
          <button className="cta" style={{ minHeight: 52, padding: '0 28px', flex: '1 0 auto' }} disabled={!q.trim()}>Cari</button>
        </div>
      </form>
      {!data ? <Loading error={error} retry={reload} /> : (
        <div className="cols">
          <section className="glass card" style={{ flex: '1 1 380px' }}>
            <div className="hd"><h2 className="h2">Sebaran sanksi</h2><span className="s num">{angka(data.total_maba)} maba</span></div>
            <div className="stack" style={{ height: 14 }} role="img" aria-label={LEVELS.map(l => `${l.label} ${data.sebaran[l.k]}`).join(', ')}>
              {LEVELS.map(l => data.sebaran[l.k] > 0 && <div key={l.k} style={{ width: persen(data.sebaran[l.k], data.total_maba) + '%', background: l.c }} />)}
            </div>
            <div className="list">
              {LEVELS.map(l => (
                <div key={l.k} className="li">
                  <span className={'chip s-' + l.k} style={{ width: 86, justifyContent: 'center' }}>{l.label}</span>
                  <span className="grow1 s">{l.rule}</span>
                  <span className="num t">{angka(data.sebaran[l.k])}</span>
                </div>
              ))}
            </div>
          </section>
          <section className="glass card" style={{ flex: '1 1 380px', gap: 4 }}>
            <div className="hd" style={{ paddingBottom: 6 }}><h2 className="h2">Baru dicatat</h2><Link className="ghost" to="/poin">Ke halaman poin</Link></div>
            {data.terbaru.length === 0 ? <p className="empty">Belum ada pelanggaran yang dicatat.</p> : (
              <div className="list">
                {data.terbaru.map(p => (
                  <Link key={p.id} className="li" to={`/poin?id=${p.mahasiswa_id}`}>
                    <span className="num" style={{ fontWeight: 700, width: 30, color: 'var(--alpa-ink)' }}>+{p.poin}</span>
                    <div className="grow1"><div className="t ellipsis">{p.nama}</div><div className="s ellipsis">{p.jenis} · {p.kelas}</div></div>
                    <span className="s num">{jam(p.created_at)}</span>
                  </Link>
                ))}
              </div>
            )}
          </section>
        </div>
      )}
    </>
  )
}
