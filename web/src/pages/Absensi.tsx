import { useEffect, useMemo, useState } from 'react'
import { Link, useBlocker, useParams, useSearchParams } from 'react-router-dom'
import { api, ApiError, HADIR_LABEL, inisial, persen, qs, tgl, useApi, useAuth, useToast, type StatusHadir } from '../lib'
import { Bar, Confirm, Icon, Loading, Search, StatusChip } from '../ui'

type KelasSesi = { id: number; nama: string; kategori: string; maba: number; terisi: number; status: string; pj: string[] }
type Sesi = { id: number; nama: string; tanggal: string; status: string }

export function AbsensiIndex() {
  const [sp, setSp] = useSearchParams()
  const { data, error, reload } = useApi<{ sesi: Sesi[]; sesi_id: number; kelas: KelasSesi[] }>('/absensi' + qs({ sesi: sp.get('sesi') }))
  return (
    <>
      <div className="top">
        <div><h1 className="h1">Isi absensi</h1><p className="sub">Pilih sesi dan kelas yang mau diisi.</p></div>
        {data && data.sesi.length > 0 && (
          <label className="fl" style={{ minWidth: 240 }}><span className="lbl">Sesi</span>
            <select className="field" value={data.sesi_id} onChange={e => setSp({ sesi: e.target.value })}>
              {data.sesi.map(s => <option key={s.id} value={s.id}>{s.nama} · {tgl(s.tanggal, { hari: true })}</option>)}
            </select>
          </label>
        )}
      </div>
      {!data ? <Loading error={error} retry={reload} /> : data.sesi.length === 0 ? (
        <div className="glass card"><p className="t">Belum ada sesi absensi.</p><p className="muted">Sesi dibuat oleh admin di menu Sesi absensi.</p></div>
      ) : data.kelas.length === 0 ? (
        <div className="glass card"><p className="t">Tidak ada kelas untuk diisi.</p><p className="muted">Akunmu belum ditugaskan ke kelas mana pun. Hubungi admin panitia.</p></div>
      ) : (
        <section className="glass card" style={{ gap: 0, padding: '8px 18px' }}>
          <div className="list">
            {data.kelas.map(k => (
              <Link key={k.id} className="li" to={`/absensi/${data.sesi_id}/${k.id}`} style={{ padding: '14px 4px' }}>
                <div style={{ width: 44, height: 44, borderRadius: 12, background: k.kategori === 'pagi' ? 'var(--tint-pagi)' : 'var(--tint-malam)', display: 'grid', placeItems: 'center', fontWeight: 700, flexShrink: 0 }}>{k.nama}</div>
                <div className="grow1"><div className="t">Kelas {k.nama} · {k.kategori === 'pagi' ? 'Pagi' : 'Malam'}</div><div className="s ellipsis">{k.pj.length ? 'PJ ' + k.pj.join(', ') : 'Belum ada PJ'}</div></div>
                <div className="hide-m" style={{ width: 120 }}><Bar value={persen(k.terisi, k.maba)} /></div>
                <span className="num s" style={{ width: 52, textAlign: 'right' }}>{k.terisi}/{k.maba}</span>
                <StatusChip s={k.status} />
                <Icon name="chev" />
              </Link>
            ))}
          </div>
        </section>
      )}
    </>
  )
}

type Detail = {
  sesi: { id: number; nama: string; tanggal: string }
  kelas: { id: number; nama: string; kategori: string; pj: string[] }
  status: string
  bisa_isi: boolean
  maba: { id: number; nama: string; nim: string; jurusan: string; status: StatusHadir | null }[]
}

const KEYS: StatusHadir[] = ['hadir', 'izin', 'sakit', 'alpa']
const AV = ['var(--tint-pagi)', 'var(--tint-malam)', 'var(--tint-mint)', 'var(--tint-sand)']

export function AbsensiKelas() {
  const { sesi, kelas } = useParams()
  const { user } = useAuth()
  const toast = useToast()
  const { data, error, reload } = useApi<Detail>(`/absensi/${sesi}/${kelas}`)
  const [st, setSt] = useState<Record<number, StatusHadir>>({})
  const [awal, setAwal] = useState<Record<number, StatusHadir>>({})
  const [q, setQ] = useState('')
  const [onlyEmpty, setOnlyEmpty] = useState(false)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!data) return
    const m: Record<number, StatusHadir> = {}
    data.maba.forEach(x => { if (x.status) m[x.id] = x.status })
    setSt(m)
    setAwal(m)
  }, [data])

  const dirty = useMemo(() => Object.keys(st).some(k => st[+k] !== awal[+k]), [st, awal])
  const blocker = useBlocker(({ currentLocation, nextLocation }) => dirty && currentLocation.pathname !== nextLocation.pathname)
  useEffect(() => {
    const h = (e: BeforeUnloadEvent) => { if (dirty) e.preventDefault() }
    window.addEventListener('beforeunload', h)
    return () => window.removeEventListener('beforeunload', h)
  }, [dirty])

  if (!data) return <Loading error={error} retry={reload} />
  const edit = data.bisa_isi
  const c = { hadir: 0, izin: 0, sakit: 0, alpa: 0 }
  Object.values(st).forEach(v => c[v]++)
  const terisi = Object.keys(st).length
  const kosong = data.maba.length - terisi
  const term = q.trim().toLowerCase()
  const rows = data.maba.filter(m => (!onlyEmpty || !st[m.id]) && (!term || m.nama.toLowerCase().includes(term) || m.nim.includes(term)))

  const set = (id: number, v: StatusHadir) => setSt(s => {
    const n = { ...s }
    if (n[id] === v && !awal[id]) delete n[id]
    else n[id] = v
    return n
  })
  const sisanyaHadir = () => setSt(s => { const n = { ...s }; data.maba.forEach(m => { if (!n[m.id]) n[m.id] = 'hadir' }); return n })

  async function simpan() {
    setBusy(true)
    try {
      const body: Record<string, StatusHadir> = {}
      for (const [k, v] of Object.entries(st)) if (awal[+k] !== v) body[k] = v
      const r = await api<{ message: string }>(`/absensi/${sesi}/${kelas}`, { method: 'PUT', body: { absensi: body } })
      setAwal(st)
      toast(r.message)
    } catch (e) { toast((e as ApiError).message, 'error') } finally { setBusy(false) }
  }

  const backTo = user!.role === 'admin' ? `/sesi?id=${sesi}` : '/'
  return (
    <>
      <nav aria-label="Breadcrumb" className="s row" style={{ gap: 8 }}>
        <Link to={backTo} style={{ color: 'var(--muted)', textDecoration: 'none' }}>{user!.role === 'admin' ? 'Sesi absensi' : 'Beranda'}</Link>
        <span aria-hidden="true">/</span><span>{data.sesi.nama}</span><span aria-hidden="true">/</span>
        <span style={{ color: 'var(--ink)', fontWeight: 600 }}>Kelas {data.kelas.nama}</span>
      </nav>
      <div className="top">
        <div>
          <h1 className="h1">Kelas {data.kelas.nama} <span className="muted" style={{ fontWeight: 400, fontSize: '.7em' }}>· {data.kelas.kategori === 'pagi' ? 'Pagi' : 'Malam'}</span></h1>
          <p className="sub">{data.sesi.nama} · {tgl(data.sesi.tanggal, { hari: true, tahun: true })}{data.kelas.pj.length ? ' · PJ ' + data.kelas.pj.join(', ') : ''}</p>
        </div>
        <StatusChip s={data.status} />
      </div>
      {!edit && <div className="glass card" role="status" style={{ padding: '14px 18px' }}><p className="t">{data.status === 'ditutup' ? 'Absensi kelas ini sudah ditutup.' : 'Absensi kelas ini belum dibuka admin.'}</p><p className="s">Data hanya bisa dilihat.</p></div>}
      {edit && user!.role === 'admin' && data.status !== 'dibuka' && <div className="glass card" role="status" style={{ padding: '14px 18px' }}><p className="s">Kelas ini sedang {data.status === 'ditutup' ? 'ditutup' : 'belum dibuka'}. Sebagai admin kamu tetap bisa mengubah datanya.</p></div>}

      <section className="glass card" style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 20, alignItems: 'center', padding: '20px 22px' }}>
        <div style={{ flex: '1 1 220px' }}>
          <div className="big" style={{ fontSize: 34 }}>{terisi}<span className="muted" style={{ fontSize: 18, fontWeight: 500 }}> / {data.maba.length} terisi</span></div>
          <div style={{ marginTop: 12 }}><Bar value={persen(terisi, data.maba.length)} /></div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(64px, 1fr))', gap: 8, flex: '2 1 320px' }}>
          {KEYS.map(k => (
            <div key={k} className="tile" style={{ padding: '10px 12px' }}>
              <div className="lbl">{HADIR_LABEL[k]}</div>
              <div className="num" style={{ fontSize: 22, fontWeight: 600, color: `var(--${k}-ink)` }}>{c[k]}</div>
            </div>
          ))}
        </div>
      </section>

      <section className="glass card" style={{ padding: 14, gap: 4 }}>
        <div className="hd" style={{ padding: '6px 6px 10px' }}>
          <div style={{ width: 300, maxWidth: '100%' }}><Search value={q} onChange={setQ} placeholder="Cari nama atau NIM" label="Cari maba" /></div>
          <div className="row">
            <button className={'pill' + (!onlyEmpty ? ' on' : '')} aria-pressed={!onlyEmpty} onClick={() => setOnlyEmpty(false)}>Semua</button>
            <button className={'pill' + (onlyEmpty ? ' on' : '')} aria-pressed={onlyEmpty} onClick={() => setOnlyEmpty(true)}>Belum diisi · {kosong}</button>
            {edit && kosong > 0 && <button className="pill" onClick={sisanyaHadir}>Sisanya hadir</button>}
          </div>
        </div>
        {data.maba.length === 0 && <p className="empty">Belum ada mahasiswa di kelas ini.</p>}
        {data.maba.length > 0 && rows.length === 0 && <p className="empty">{onlyEmpty ? 'Semua maba sudah diisi. Tekan Simpan supaya tercatat.' : 'Tidak ada maba yang cocok dengan pencarian.'}</p>}
        <div className="list">
          {rows.map((m, i) => (
            <div key={m.id} className="li" style={{ flexWrap: 'wrap', padding: '12px 10px', gap: '10px 14px' }}>
              <div className="row" style={{ flex: '1 1 220px', minWidth: 0, gap: 12, flexWrap: 'nowrap' }}>
                <div className="av hide-m" style={{ width: 38, height: 38, borderRadius: '50%', background: AV[i % 4], display: 'grid', placeItems: 'center', fontSize: 13, fontWeight: 700, flexShrink: 0 }} aria-hidden="true">{inisial(m.nama)}</div>
                <div className="grow1"><div className="t ellipsis" style={{ fontSize: 15 }}>{m.nama}</div><div className="s num ellipsis">{m.nim} · {m.jurusan}</div></div>
              </div>
              <div className="seg wide" role="group" aria-label={`Kehadiran ${m.nama}`}>
                {KEYS.map(k => (
                  <button key={k} aria-pressed={st[m.id] === k} disabled={!edit}
                    className={'sb' + (st[m.id] === k ? ' ' + k : '')} onClick={() => set(m.id, k)}>
                    {HADIR_LABEL[k]}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>

      {edit && (
        <div className="glass floatbar" style={{ borderRadius: 999, padding: '10px 10px 10px 22px' }}>
          <span style={{ fontSize: 14.5 }}>
            <strong className="num">{kosong}</strong> <span className="muted">maba belum diisi.{dirty ? ' Ada perubahan yang belum disimpan.' : ''}</span>
          </span>
          <button className="cta" disabled={!dirty || busy} onClick={simpan}>{busy ? 'Menyimpan…' : 'Simpan absensi'}</button>
        </div>
      )}
      <Confirm open={blocker.state === 'blocked'} title="Tinggalkan halaman?" ok="Tinggalkan" danger
        body="Ada perubahan absensi yang belum disimpan. Kalau kamu pergi sekarang, perubahannya hilang."
        onOk={() => blocker.proceed?.()} onClose={() => blocker.reset?.()} />
    </>
  )
}
