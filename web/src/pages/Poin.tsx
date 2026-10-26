import { useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError, HADIR_LABEL, jam, qs, tgl, useApi, useAuth, useToast, ROLE_LABEL, type MabaPoin, type Role, type StatusHadir } from '../lib'
import { Confirm, Field, HadirChip, Icon, Loading, Modal, SanksiChip, Search, useDebounced } from '../ui'

type Pel = { id: number; jenis: string; poin: number; pencatat: string | null; pencatat_role: Role | null; created_at: string }
type Detail = { mahasiswa: MabaPoin; pelanggaran: Pel[]; kehadiran: { id: number; sesi: string; tanggal: string; status: StatusHadir }[] }

export default function Poin() {
  const { user } = useAuth()
  const [sp, setSp] = useSearchParams()
  const [q, setQ] = useState(sp.get('q') ?? '')
  const dq = useDebounced(q)
  const id = Number(sp.get('id')) || 0
  const bisaCari = user!.role !== 'pj'
  const hasil = useApi<{ data: MabaPoin[] }>(bisaCari && dq.trim() ? '/poin/cari' + qs({ q: dq.trim() }) : null)
  const detail = useApi<Detail>(id ? `/mahasiswa/${id}/detail` : null)
  useEffect(() => {
    // Satu hasil saja: langsung tampilkan detailnya.
    if (hasil.data?.data.length === 1 && !id) pilih(hasil.data.data[0].id)
  }, [hasil.data]) // eslint-disable-line react-hooks/exhaustive-deps
  const pilih = (mid: number) => setSp(p => { const n = new URLSearchParams(p); n.set('id', String(mid)); if (dq) n.set('q', dq); return n }, { replace: true })

  const judul = user!.role === 'manajer' ? 'Poin mahasiswa' : user!.role === 'pj' ? 'Detail maba' : 'Poin pelanggaran'
  return (
    <>
      <div className="top">
        <div>
          <h1 className="h1">{judul}</h1>
          <p className="sub">Total poin = poin absensi (alpa 5, izin/sakit 3) + poin pelanggaran.</p>
        </div>
        {user!.role === 'admin' && (
          <div className="row"><a className="pill" href="/api/export/poin" download>Export catatan</a><a className="pill" href="/api/export/sanksi" download>Export daftar sanksi</a></div>
        )}
        {user!.role === 'pj' && <Link className="pill" to="/rekap">Kembali ke rekap</Link>}
      </div>
      <div className="cols">
        {bisaCari && (
          <section className={'glass card' + (id ? ' hide-m' : '')} style={{ flex: '1 1 320px', maxWidth: 400, padding: 16, gap: 10 }} aria-label="Cari maba">
            <Search value={q} onChange={setQ} placeholder="Cari NIM atau nama maba" label="Cari maba" autoFocus={!id} />
            {!dq.trim() ? <p className="s" style={{ padding: '4px 6px' }}>Ketik minimal sebagian nama atau NIM.</p>
              : !hasil.data ? <p className="s" style={{ padding: '4px 6px' }}>Mencari…</p>
              : hasil.data.data.length === 0 ? <p className="empty">Tidak ada maba dengan nama atau NIM “{dq}”.</p> : (
                <>
                  <p className="lbl" style={{ margin: '0 6px' }}>{hasil.data.data.length === 25 ? '25 hasil pertama' : `${hasil.data.data.length} hasil`}</p>
                  <div className="list">
                    {hasil.data.data.map(m => (
                      <button key={m.id} className={'li' + (m.id === id ? ' on' : '')} aria-current={m.id === id} onClick={() => pilih(m.id)}>
                        <div className="grow1"><div className="t ellipsis">{m.nama}</div><div className="s num">{m.nim} · {m.kelas} {m.kategori === 'pagi' ? 'Pagi' : 'Malam'}</div></div>
                        <span className="num" style={{ fontWeight: 700, fontSize: 18 }}>{m.total_poin}</span>
                      </button>
                    ))}
                  </div>
                </>
              )}
          </section>
        )}
        <div style={{ flex: '999 1 480px', minWidth: 0, display: 'flex', flexDirection: 'column', gap: 16 }} className={!id ? 'hide-m' : ''}>
          {!id ? (
            <div className="glass card"><p className="t">Pilih maba dari hasil pencarian.</p><p className="muted">Poin, sanksi, dan catatan pelanggarannya tampil di sini.</p></div>
          ) : !detail.data ? <Loading error={detail.error} retry={detail.reload} /> : (
            <DetailMaba d={detail.data} reload={() => { detail.reload(); hasil.reload() }} onBack={bisaCari ? () => setSp(dq ? { q: dq } : {}) : undefined} />
          )}
        </div>
      </div>
    </>
  )
}

function DetailMaba({ d, reload, onBack }: { d: Detail; reload: () => void; onBack?: () => void }) {
  const { user } = useAuth()
  const toast = useToast()
  const admin = user!.role === 'admin'
  const bisaCatat = admin || user!.role === 'manajer'
  const m = d.mahasiswa
  const [jenis, setJenis] = useState('')
  const [poin, setPoin] = useState('')
  const [err, setErr] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  const [ubah, setUbah] = useState<Pel | null>(null)
  const [hapus, setHapus] = useState<Pel | null>(null)
  const [hadir, setHadir] = useState<Detail['kehadiran'][number] | null>(null)
  useEffect(() => { setJenis(''); setPoin(''); setErr({}) }, [m.id])

  async function catat(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    try {
      const r = await api<{ message: string }>(`/mahasiswa/${m.id}/pelanggaran`, { method: 'POST', body: { jenis: jenis.trim(), poin: Number(poin) } })
      toast(r.message)
      setJenis(''); setPoin(''); setErr({})
      reload()
    } catch (e) { setErr((e as ApiError).fields); if (!Object.keys((e as ApiError).fields).length) toast((e as ApiError).message, 'error') } finally { setBusy(false) }
  }
  async function run(path: string, method: string, body?: unknown) {
    try {
      const r = await api<{ message: string }>(path, { method, body })
      toast(r.message)
      reload()
    } catch (e) { toast((e as ApiError).message, 'error') }
  }
  const kurang = 20 - m.total_poin
  return (
    <>
      <div className="glass card">
        {onBack && <button className="ghost show-m" style={{ alignSelf: 'flex-start', paddingLeft: 0 }} onClick={onBack}><Icon name="back" size={16} />Hasil pencarian</button>}
        <div className="hd">
          <div style={{ minWidth: 0 }}>
            <div className="lbl">{m.kelas} {m.kategori === 'pagi' ? 'Pagi' : 'Malam'} · {m.jurusan} · <span className="num">{m.nim}</span></div>
            <h2 style={{ marginTop: 4, fontSize: 26, fontWeight: 600, letterSpacing: '-.02em' }}>{m.nama}</h2>
          </div>
          <SanksiChip s={m.sanksi} full />
        </div>
        <div className="grid">
          <div className="tile stat"><div className="lbl">Poin absensi</div><div className="big">{m.poin_absensi}</div><div className="s">{[m.alpa && `${m.alpa} alpa`, m.izin && `${m.izin} izin`, m.sakit && `${m.sakit} sakit`].filter(Boolean).join(', ') || 'tidak ada'}</div></div>
          <div className="tile stat"><div className="lbl">Poin pelanggaran</div><div className="big">{m.poin_pelanggaran}</div><div className="s">{d.pelanggaran.length} catatan</div></div>
          <div className="tile stat" style={{ background: '#fff' }}><div className="lbl">Total poin</div><div className="big" style={{ color: m.total_poin >= 20 ? 'var(--merah)' : undefined }}>{m.total_poin}</div><div className="s">{kurang > 0 ? `${kurang} lagi ke cap merah` : 'lewat batas 20 poin'}</div></div>
        </div>
      </div>

      {bisaCatat && (
        <form className="glass card" onSubmit={catat}>
          <h3 className="h2">Catat pelanggaran baru</h3>
          <div className="row" style={{ gap: 12, alignItems: 'flex-start' }}>
            <div style={{ flex: '999 1 240px' }}><Field label="Jenis pelanggaran" error={err.jenis}><input className="field" value={jenis} onChange={e => setJenis(e.target.value)} maxLength={255} placeholder="Misal: telat apel pagi" required aria-invalid={!!err.jenis} /></Field></div>
            <div style={{ flex: '1 1 110px' }}><Field label="Poin (1–20)" error={err.poin}><input className="field num" value={poin} onChange={e => setPoin(e.target.value.replace(/\D/g, '').slice(0, 2))} inputMode="numeric" placeholder="3" required aria-invalid={!!err.poin} /></Field></div>
            <button className="cta" style={{ marginTop: 25, flex: '1 0 auto' }} disabled={busy || !jenis.trim() || !poin}>{busy ? 'Menyimpan…' : 'Catat'}</button>
          </div>
        </form>
      )}

      <div className="glass card" style={{ gap: 4 }}>
        <div className="hd" style={{ paddingBottom: 6 }}><h3 className="h2">Catatan pelanggaran</h3><span className="s num">{d.pelanggaran.length} catatan · {m.poin_pelanggaran} poin</span></div>
        {d.pelanggaran.length === 0 ? <p className="empty">Belum ada pelanggaran yang dicatat.</p> : (
          <div className="list">
            {d.pelanggaran.map(p => (
              <div key={p.id} className="li" style={{ alignItems: 'flex-start' }}>
                <span className="num" style={{ fontWeight: 700, width: 32, color: 'var(--alpa-ink)' }}>+{p.poin}</span>
                <div className="grow1">
                  <div className="t">{p.jenis}</div>
                  <div className="s">{tgl(p.created_at)} {jam(p.created_at)} · dicatat {p.pencatat ?? 'akun terhapus'}{p.pencatat_role ? ` (${ROLE_LABEL[p.pencatat_role]})` : ''}</div>
                </div>
                {admin && <><button className="ghost" onClick={() => setUbah(p)}>Ubah</button><button className="ghost danger" onClick={() => setHapus(p)}>Hapus</button></>}
              </div>
            ))}
          </div>
        )}
      </div>

      {user!.role !== 'manajer' && (
        <div className="glass card" style={{ gap: 4 }}>
          <div className="hd" style={{ paddingBottom: 6 }}><h3 className="h2">Riwayat kehadiran</h3><span className="s num">{d.kehadiran.length} sesi · {m.poin_absensi} poin</span></div>
          {d.kehadiran.length === 0 ? <p className="empty">Belum ada sesi yang tercatat untuk maba ini.</p> : (
            <div className="list">
              {d.kehadiran.map(h => (
                <div key={h.id} className="li">
                  <div className="grow1"><div className="t">{h.sesi}</div><div className="s">{tgl(h.tanggal, { hari: true, tahun: true })}</div></div>
                  <HadirChip s={h.status} />
                  {admin && <button className="ghost" onClick={() => setHadir(h)}>Ubah</button>}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      <UbahPelanggaran p={ubah} onClose={() => setUbah(null)} onSaved={() => { setUbah(null); reload() }} />
      <Confirm open={!!hapus} onClose={() => setHapus(null)} danger ok="Hapus catatan" title="Hapus catatan pelanggaran?"
        body={hapus && <>“{hapus.jenis}” (+{hapus.poin} poin) dihapus dan total poin {m.nama} berkurang. Penghapusan tercatat di riwayat.</>}
        onOk={() => { const p = hapus!; setHapus(null); run(`/pelanggaran/${p.id}`, 'DELETE') }} />
      <Modal open={!!hadir} onClose={() => setHadir(null)} title={`Ubah kehadiran ${hadir?.sesi ?? ''}`}>
        <div className="seg wide" role="group" aria-label="Status kehadiran" style={{ display: 'flex' }}>
          {(['hadir', 'izin', 'sakit', 'alpa'] as StatusHadir[]).map(k => (
            <button key={k} aria-pressed={hadir?.status === k} className={'sb' + (hadir?.status === k ? ' ' + k : '')} style={{ flex: 1 }}
              onClick={() => { const h = hadir!; setHadir(null); if (h.status !== k) run(`/absensi-record/${h.id}`, 'PUT', { status: k }) }}>{HADIR_LABEL[k]}</button>
          ))}
        </div>
        <div className="row" style={{ justifyContent: 'space-between' }}>
          <button className="ghost danger" onClick={() => { const h = hadir!; setHadir(null); run(`/absensi-record/${h.id}`, 'DELETE') }}>Hapus data kehadiran</button>
          <button className="pill" onClick={() => setHadir(null)}>Batal</button>
        </div>
      </Modal>
    </>
  )
}

function UbahPelanggaran({ p, onClose, onSaved }: { p: Pel | null; onClose: () => void; onSaved: () => void }) {
  const toast = useToast()
  const [jenis, setJenis] = useState('')
  const [poin, setPoin] = useState('')
  const [err, setErr] = useState<Record<string, string>>({})
  useEffect(() => { if (p) { setJenis(p.jenis); setPoin(String(p.poin)); setErr({}) } }, [p])
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      const r = await api<{ message: string }>(`/pelanggaran/${p!.id}`, { method: 'PUT', body: { jenis: jenis.trim(), poin: Number(poin) } })
      toast(r.message)
      onSaved()
    } catch (e) { setErr((e as ApiError).fields); if (!Object.keys((e as ApiError).fields).length) toast((e as ApiError).message, 'error') }
  }
  return (
    <Modal open={!!p} onClose={onClose} title="Ubah catatan pelanggaran">
      <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <Field label="Jenis pelanggaran" error={err.jenis}><input className="field" value={jenis} onChange={e => setJenis(e.target.value)} maxLength={255} required /></Field>
        <Field label="Poin (1–20)" error={err.poin}><input className="field num" value={poin} onChange={e => setPoin(e.target.value.replace(/\D/g, '').slice(0, 2))} inputMode="numeric" required /></Field>
        <div className="row" style={{ justifyContent: 'flex-end' }}><button type="button" className="pill" onClick={onClose}>Batal</button><button className="cta">Simpan</button></div>
      </form>
    </Modal>
  )
}
