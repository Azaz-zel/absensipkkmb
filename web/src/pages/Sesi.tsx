import { useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiError, persen, tgl, useApi, useToast } from '../lib'
import { Bar, Confirm, Field, Loading, Modal, StatusChip } from '../ui'

type Sesi = { id: number; nama: string; tanggal: string; dibuka: number; ditutup: number; belum_dibuka: number; status: string }
type KelasSesi = { id: number; nama: string; kategori: string; maba: number; terisi: number; status: string; pj: string[] }

export default function SesiPage() {
  const toast = useToast()
  const [sp, setSp] = useSearchParams()
  const list = useApi<{ data: Sesi[] }>('/sesi')
  const sesiList = list.data?.data ?? []
  const pilih = Number(sp.get('id')) || sesiList[0]?.id || 0
  const detail = useApi<{ sesi: Sesi; kelas: KelasSesi[] }>(pilih ? `/sesi/${pilih}` : null)
  const [form, setForm] = useState<{ id?: number; nama: string; tanggal: string } | null>(null)
  const [hapus, setHapus] = useState(false)

  const reloadAll = () => { list.reload(); detail.reload() }

  async function remove() {
    setHapus(false)
    try {
      const r = await api<{ message: string }>(`/sesi/${pilih}`, { method: 'DELETE' })
      toast(r.message)
      setSp({})
      list.reload()
    } catch (e) { toast((e as ApiError).message, 'error') }
  }

  const s = detail.data?.sesi
  return (
    <>
      <div className="top">
        <div><h1 className="h1">Sesi absensi</h1><p className="sub">Buat sesi, lalu buka atau tutup absensi tiap kelas.</p></div>
        <button className="cta" onClick={() => setForm({ nama: `Sesi ${sesiList.length + 1}`, tanggal: new Date().toLocaleDateString('sv-SE') })}>Buat sesi baru</button>
      </div>
      {!list.data ? <Loading error={list.error} retry={list.reload} /> : sesiList.length === 0 ? (
        <div className="glass card"><p className="t">Belum ada sesi.</p><p className="muted">Sesi baru otomatis berisi semua kelas dengan status belum dibuka.</p></div>
      ) : (
        <div className="cols">
          <section className="glass card hide-m" style={{ flex: '0 1 300px', padding: 14, gap: 4 }} aria-label="Daftar sesi">
            <div className="lbl" style={{ padding: '6px 8px' }}>{sesiList.length} sesi</div>
            <div className="list">
              {sesiList.map(x => (
                <button key={x.id} className={'li' + (x.id === pilih ? ' on' : '')} aria-current={x.id === pilih} onClick={() => setSp({ id: String(x.id) })}>
                  <div className="grow1"><div className="t">{x.nama}</div><div className="s">{tgl(x.tanggal, { hari: true })}</div></div>
                  <StatusChip s={x.status} />
                </button>
              ))}
            </div>
          </section>
          <div className="show-m" style={{ width: '100%' }}>
            <label className="fl"><span className="lbl">Sesi</span>
              <select className="field" value={pilih} onChange={e => setSp({ id: e.target.value })}>
                {sesiList.map(x => <option key={x.id} value={x.id}>{x.nama} · {tgl(x.tanggal)}</option>)}
              </select>
            </label>
          </div>
          <section className="glass card" style={{ flex: '999 1 420px' }}>
            {!detail.data || !s ? <Loading error={detail.error} retry={detail.reload} /> : (
              <>
                <div className="hd">
                  <div>
                    <h2 style={{ fontSize: 22, fontWeight: 600, letterSpacing: '-.01em' }}>{s.nama}</h2>
                    <div className="s">{tgl(s.tanggal, { hari: true, tahun: true })} · {ringkas(detail.data.kelas)}</div>
                  </div>
                  <div className="row" style={{ gap: 4 }}>
                    <button className="ghost" onClick={() => setForm({ id: s.id, nama: s.nama, tanggal: s.tanggal })}>Ubah nama/tanggal</button>
                    <button className="ghost danger" onClick={() => setHapus(true)}>Hapus sesi</button>
                  </div>
                </div>
                <KelasTable sesiId={s.id} kelas={detail.data.kelas} onDone={reloadAll} />
              </>
            )}
          </section>
        </div>
      )}
      <SesiForm value={form} onClose={() => setForm(null)} onSaved={(id) => { setForm(null); if (id) setSp({ id: String(id) }); reloadAll() }} />
      <Confirm open={hapus} onClose={() => setHapus(false)} onOk={remove} danger ok="Hapus sesi" title={`Hapus ${s?.nama}?`}
        body="Semua data absensi pada sesi ini ikut terhapus dan poin maba dihitung ulang. Tindakan ini tidak bisa dibatalkan." />
    </>
  )
}

function ringkas(k: KelasSesi[]) {
  const n = (st: string) => k.filter(x => x.status === st).length
  return `${n('dibuka')} dibuka · ${n('ditutup')} ditutup · ${n('belum_dibuka')} belum dibuka`
}

function KelasTable({ sesiId, kelas, onDone }: { sesiId: number; kelas: KelasSesi[]; onDone: () => void }) {
  const toast = useToast()
  const [kat, setKat] = useState('semua')
  const [sel, setSel] = useState<Set<number>>(new Set())
  const [tutup, setTutup] = useState(false)
  const [busy, setBusy] = useState(false)
  useEffect(() => setSel(new Set()), [sesiId])
  const rows = kelas.filter(k => kat === 'semua' || k.kategori === kat)
  const allOn = rows.length > 0 && rows.every(r => sel.has(r.id))
  const toggle = (id: number) => setSel(s => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n })
  const toggleAll = () => setSel(s => { const n = new Set(s); rows.forEach(r => (allOn ? n.delete(r.id) : n.add(r.id))); return n })
  const belumTerisi = kelas.filter(k => sel.has(k.id)).reduce((a, k) => a + Math.max(0, k.maba - k.terisi), 0)

  async function apply(aksi: 'buka' | 'tutup') {
    setTutup(false)
    setBusy(true)
    try {
      const r = await api<{ message: string }>(`/sesi/${sesiId}/status`, { method: 'POST', body: { aksi, kelas_ids: [...sel] } })
      toast(r.message)
      setSel(new Set())
      onDone()
    } catch (e) { toast((e as ApiError).message, 'error') } finally { setBusy(false) }
  }

  const count = (k: string) => kelas.filter(x => k === 'semua' || x.kategori === k).length
  return (
    <>
      <div className="hd">
        <div className="row" role="group" aria-label="Kategori kelas">
          {['semua', 'pagi', 'malam'].map(k => (
            <button key={k} className={'pill sm' + (kat === k ? ' on' : '')} aria-pressed={kat === k} onClick={() => setKat(k)}>
              {k === 'semua' ? 'Semua' : k === 'pagi' ? 'Pagi' : 'Malam'} · {count(k)}
            </button>
          ))}
        </div>
        <button className="ghost" onClick={toggleAll} disabled={rows.length === 0}>{allOn ? 'Batal pilih semua' : 'Pilih semua yang tampil'}</button>
      </div>
      {rows.length === 0 ? <p className="empty">Belum ada kelas. Tambahkan dulu di menu Kelas.</p> : (
        <table className="tbl cards">
          <thead><tr><th style={{ width: 36 }}><span className="sr">Pilih</span></th><th>Kelas</th><th>PJ</th><th>Terisi</th><th>Status</th><th><span className="sr">Aksi</span></th></tr></thead>
          <tbody>
            {rows.map(k => (
              <tr key={k.id}>
                <td className="first">
                  <label className="check" style={{ minHeight: 24 }}>
                    <input type="checkbox" checked={sel.has(k.id)} onChange={() => toggle(k.id)} aria-label={`Pilih kelas ${k.nama}`} />
                    <span className="show-m t">{k.nama} · {k.kategori}</span>
                  </label>
                </td>
                <td className="hide-m"><strong>{k.nama}</strong> <span className="muted">· {k.kategori === 'pagi' ? 'Pagi' : 'Malam'}</span></td>
                <td data-label="PJ" className="muted">{k.pj.join(', ') || '—'}</td>
                <td data-label="Terisi">
                  <div className="row" style={{ gap: 10, flexWrap: 'nowrap' }}><div style={{ width: 90 }}><Bar value={persen(k.terisi, k.maba)} /></div><span className="num" style={{ fontSize: 13.5 }}>{k.terisi}/{k.maba}</span></div>
                </td>
                <td data-label="Status"><StatusChip s={k.status} /></td>
                <td className="r act"><Link className="ghost" to={`/absensi/${sesiId}/${k.id}`}>Isi</Link></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {sel.size > 0 && (
        <div className="glass floatbar">
          <div><strong className="num">{sel.size} kelas dipilih.</strong> <span className="s">Menutup kelas menandai maba yang belum diabsen sebagai alpa.</span></div>
          <div className="row">
            <button className="pill" disabled={busy} onClick={() => setTutup(true)}>Tutup absensi</button>
            <button className="cta" disabled={busy} onClick={() => apply('buka')}>Buka absensi</button>
          </div>
        </div>
      )}
      <Confirm open={tutup} onClose={() => setTutup(false)} onOk={() => apply('tutup')} ok="Tutup absensi" title={`Tutup ${sel.size} kelas?`}
        body={belumTerisi > 0 ? <><strong className="num" style={{ color: 'var(--alpa-ink)' }}>{belumTerisi} maba</strong> yang belum diabsen akan ditandai alpa (5 poin masing-masing). PJ tidak bisa mengubah lagi setelah ditutup.</> : 'Semua maba di kelas ini sudah diabsen. PJ tidak bisa mengubah lagi setelah ditutup.'} />
    </>
  )
}

function SesiForm({ value, onClose, onSaved }: { value: { id?: number; nama: string; tanggal: string } | null; onClose: () => void; onSaved: (id?: number) => void }) {
  const toast = useToast()
  const [nama, setNama] = useState('')
  const [tanggal, setTanggal] = useState('')
  const [err, setErr] = useState<Record<string, string>>({})
  useEffect(() => { if (value) { setNama(value.nama); setTanggal(value.tanggal); setErr({}) } }, [value])
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      const r = await api<{ message: string; id?: number }>(value?.id ? `/sesi/${value.id}` : '/sesi', { method: value?.id ? 'PUT' : 'POST', body: { nama, tanggal } })
      toast(r.message)
      onSaved(r.id)
    } catch (e) { setErr((e as ApiError).fields); if (!Object.keys((e as ApiError).fields).length) toast((e as ApiError).message, 'error') }
  }
  return (
    <Modal open={!!value} onClose={onClose} title={value?.id ? 'Ubah sesi' : 'Buat sesi baru'}>
      <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <Field label="Nama sesi" error={err.nama}><input className="field" value={nama} onChange={e => setNama(e.target.value)} required maxLength={255} aria-invalid={!!err.nama} /></Field>
        <Field label="Tanggal" error={err.tanggal}><input className="field" type="date" value={tanggal} onChange={e => setTanggal(e.target.value)} required aria-invalid={!!err.tanggal} /></Field>
        {value?.id ? <p className="s">Mengubah nama atau tanggal tidak mengubah absensi yang sudah tercatat.</p> : <p className="s">Semua kelas otomatis masuk ke sesi ini dengan status belum dibuka.</p>}
        <div className="row" style={{ justifyContent: 'flex-end' }}>
          <button type="button" className="pill" onClick={onClose}>Batal</button>
          <button className="cta">{value?.id ? 'Simpan' : 'Buat sesi'}</button>
        </div>
      </form>
    </Modal>
  )
}
