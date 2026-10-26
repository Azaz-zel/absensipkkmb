import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, ApiError, qs, useApi, useToast } from '../lib'
import { Confirm, Field, Icon, Loading, Modal, Pager, Search, useDebounced } from '../ui'

type Maba = { id: number; nama: string; nim: string; jurusan: string; kelas_id: number; kelas: string; kategori: string }
type Opsi = { kelas: { id: number; nama: string; kategori: string }[]; jurusan: string[] }
type Preview = { token: string; rows: { baris: number; nama: string; nim: string; jurusan: string; kelas: string; errors: string[] }[]; total: number; valid: number; error: number }

export default function Mahasiswa() {
  const toast = useToast()
  const [sp, setSp] = useSearchParams()
  const [q, setQ] = useState(sp.get('q') ?? '')
  const dq = useDebounced(q)
  const set = (k: string, v: string) => setSp(p => { const n = new URLSearchParams(p); if (v) n.set(k, v); else n.delete(k); if (k !== 'page') n.delete('page'); return n }, { replace: true })
  useEffect(() => { if ((sp.get('q') ?? '') !== dq) set('q', dq) }, [dq]) // eslint-disable-line react-hooks/exhaustive-deps
  const list = useApi<{ data: Maba[]; total: number; page: number; per_page: number; semua: number }>('/mahasiswa' + qs({ q: sp.get('q'), kelas_id: sp.get('kelas_id'), jurusan: sp.get('jurusan'), page: sp.get('page') }))
  const opsi = useApi<Opsi>('/filter')
  const [sel, setSel] = useState<Set<number>>(new Set())
  const [form, setForm] = useState<Partial<Maba> | null>(null)
  const [imp, setImp] = useState(false)
  const [del, setDel] = useState<null | { title: string; body: string; typed?: string; body2: unknown }>(null)

  const rows = list.data?.data ?? []
  const allOn = rows.length > 0 && rows.every(r => sel.has(r.id))
  const toggle = (id: number) => setSel(s => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n })
  const reload = () => { list.reload(); opsi.reload() }
  const kelasPilih = opsi.data?.kelas.find(k => String(k.id) === sp.get('kelas_id'))

  async function hapusMassal(body: unknown) {
    setDel(null)
    try {
      const r = await api<{ message: string }>('/mahasiswa/hapus-massal', { method: 'POST', body })
      toast(r.message)
      setSel(new Set())
      reload()
    } catch (e) { toast((e as ApiError).message, 'error') }
  }

  return (
    <>
      <div className="top">
        <div><h1 className="h1">Mahasiswa</h1><p className="sub">{list.data ? `${list.data.semua.toLocaleString('id-ID')} maba di ${opsi.data?.kelas.length ?? '…'} kelas.` : 'Memuat…'}</p></div>
        <div className="row"><button className="pill" onClick={() => setImp(true)}><Icon name="up" size={16} />Import Excel</button><button className="cta" onClick={() => setForm({})}>Tambah mahasiswa</button></div>
      </div>
      <section className="glass card">
        <div className="hd">
          <div style={{ width: 300, maxWidth: '100%' }}><Search value={q} onChange={setQ} placeholder="Cari nama atau NIM" label="Cari mahasiswa" /></div>
          <div className="row filters">
            <label><span className="sr">Kelas</span><select className="field round" value={sp.get('kelas_id') ?? ''} onChange={e => set('kelas_id', e.target.value)}><option value="">Semua kelas</option>{opsi.data?.kelas.map(k => <option key={k.id} value={k.id}>{k.nama} · {k.kategori}</option>)}</select></label>
            <label><span className="sr">Jurusan</span><select className="field round" value={sp.get('jurusan') ?? ''} onChange={e => set('jurusan', e.target.value)}><option value="">Semua jurusan</option>{opsi.data?.jurusan.map(j => <option key={j}>{j}</option>)}</select></label>
          </div>
        </div>
        {!list.data ? <Loading error={list.error} retry={list.reload} /> : rows.length === 0 ? (
          <p className="empty">{list.data.semua === 0 ? 'Belum ada data mahasiswa. Mulai dengan Import Excel atau Tambah mahasiswa.' : 'Tidak ada mahasiswa yang cocok dengan filter ini.'}</p>
        ) : (
          <>
            <table className="tbl cards">
              <thead><tr>
                <th style={{ width: 36 }}><input type="checkbox" checked={allOn} onChange={() => setSel(s => { const n = new Set(s); rows.forEach(r => (allOn ? n.delete(r.id) : n.add(r.id))); return n })} aria-label="Pilih semua di halaman ini" style={{ width: 18, height: 18, accentColor: 'var(--plum)' }} /></th>
                <th>Nama</th><th>NIM</th><th>Jurusan</th><th>Kelas</th><th className="r"><span className="sr">Aksi</span></th>
              </tr></thead>
              <tbody>
                {rows.map(m => (
                  <tr key={m.id}>
                    <td className="first"><label className="check" style={{ minHeight: 24 }}><input type="checkbox" checked={sel.has(m.id)} onChange={() => toggle(m.id)} aria-label={`Pilih ${m.nama}`} /><span className="t show-m">{m.nama}</span></label></td>
                    <td className="t hide-m">{m.nama}</td>
                    <td className="num muted" data-label="NIM">{m.nim}</td>
                    <td data-label="Jurusan">{m.jurusan}</td>
                    <td data-label="Kelas">{m.kelas} · {m.kategori === 'pagi' ? 'Pagi' : 'Malam'}</td>
                    <td className="r act"><button className="ghost" onClick={() => setForm(m)}>Ubah</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
            <Pager page={list.data.page} total={list.data.total} per={list.data.per_page} onPage={p => set('page', String(p))} />
          </>
        )}
        {sel.size > 0 && (
          <div className="glass floatbar">
            <strong className="num">{sel.size} maba dipilih</strong>
            <div className="row"><button className="ghost" onClick={() => setSel(new Set())}>Batal pilih</button>
              <button className="ghost danger" onClick={() => setDel({ title: `Hapus ${sel.size} maba?`, body: 'Absensi dan poin mereka ikut hilang dari rekap.', body2: { ids: [...sel] } })}>Hapus yang dipilih</button></div>
          </div>
        )}
      </section>
      <section className="glass card" style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 12, padding: '16px 22px' }}>
        <div><div className="t">Hapus massal</div><div className="s">Menghapus maba ikut menghapus absensi dan poinnya dari rekap.</div></div>
        <div className="row">
          <button className="ghost danger" disabled={!kelasPilih} title={kelasPilih ? undefined : 'Pilih kelas di filter dulu'}
            onClick={() => kelasPilih && setDel({ title: `Hapus semua maba kelas ${kelasPilih.nama}?`, body: `Seluruh mahasiswa kelas ${kelasPilih.nama} dihapus.`, typed: kelasPilih.nama, body2: { kelas_id: kelasPilih.id, konfirmasi: kelasPilih.nama } })}>
            {kelasPilih ? `Hapus kelas ${kelasPilih.nama}` : 'Hapus satu kelas'}
          </button>
          <button className="ghost danger" onClick={() => setDel({ title: 'Hapus semua data mahasiswa?', body: `${list.data?.semua ?? 0} mahasiswa dari semua kelas akan dihapus.`, typed: 'HAPUS SEMUA DATA', body2: { konfirmasi: 'HAPUS SEMUA DATA' } })}>Hapus semua data</button>
        </div>
      </section>
      <MabaForm value={form} opsi={opsi.data} onClose={() => setForm(null)} onSaved={() => { setForm(null); reload() }} />
      <Import open={imp} onClose={() => setImp(false)} onDone={() => { setImp(false); reload() }} />
      <Confirm open={!!del} onClose={() => setDel(null)} danger ok="Hapus" title={del?.title ?? ''} typed={del?.typed}
        body={<>{del?.body} Tindakan ini tercatat di riwayat.</>} onOk={() => hapusMassal(del!.body2)} />
    </>
  )
}

function MabaForm({ value, opsi, onClose, onSaved }: { value: Partial<Maba> | null; opsi: Opsi | null; onClose: () => void; onSaved: () => void }) {
  const toast = useToast()
  const [f, setF] = useState({ nama: '', nim: '', jurusan: '', kelas_id: 0 })
  const [err, setErr] = useState<Record<string, string>>({})
  const [hapus, setHapus] = useState(false)
  useEffect(() => { if (value) { setF({ nama: value.nama ?? '', nim: value.nim ?? '', jurusan: value.jurusan ?? '', kelas_id: value.kelas_id ?? 0 }); setErr({}) } }, [value])
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      const r = await api<{ message: string }>(value?.id ? `/mahasiswa/${value.id}` : '/mahasiswa', { method: value?.id ? 'PUT' : 'POST', body: f })
      toast(r.message)
      onSaved()
    } catch (e) { setErr((e as ApiError).fields); if (!Object.keys((e as ApiError).fields).length) toast((e as ApiError).message, 'error') }
  }
  async function remove() {
    setHapus(false)
    try { const r = await api<{ message: string }>(`/mahasiswa/${value!.id}`, { method: 'DELETE' }); toast(r.message); onSaved() } catch (e) { toast((e as ApiError).message, 'error') }
  }
  return (
    <Modal open={!!value} onClose={onClose} title={value?.id ? 'Ubah mahasiswa' : 'Tambah mahasiswa'}>
      <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <Field label="Nama lengkap" error={err.nama}><input className="field" value={f.nama} onChange={e => setF({ ...f, nama: e.target.value })} maxLength={255} required aria-invalid={!!err.nama} /></Field>
        <Field label="NIM" error={err.nim}><input className="field num" value={f.nim} onChange={e => setF({ ...f, nim: e.target.value })} maxLength={50} required aria-invalid={!!err.nim} /></Field>
        <Field label="Jurusan" error={err.jurusan}>
          <input className="field" list="daftar-jurusan" value={f.jurusan} onChange={e => setF({ ...f, jurusan: e.target.value })} maxLength={255} required aria-invalid={!!err.jurusan} />
          <datalist id="daftar-jurusan">{opsi?.jurusan.map(j => <option key={j} value={j} />)}</datalist>
        </Field>
        <Field label="Kelas" error={err.kelas_id}>
          <select className="field" value={f.kelas_id || ''} onChange={e => setF({ ...f, kelas_id: Number(e.target.value) })} required aria-invalid={!!err.kelas_id}>
            <option value="" disabled>Pilih kelas</option>{opsi?.kelas.map(k => <option key={k.id} value={k.id}>{k.nama} · {k.kategori}</option>)}
          </select>
        </Field>
        <div className="row" style={{ justifyContent: 'space-between' }}>
          {value?.id ? <button type="button" className="ghost danger" onClick={() => setHapus(true)}>Hapus mahasiswa</button> : <span />}
          <div className="row"><button type="button" className="pill" onClick={onClose}>Batal</button><button className="cta">Simpan</button></div>
        </div>
      </form>
      <Confirm open={hapus} onClose={() => setHapus(false)} danger ok="Hapus" title={`Hapus ${value?.nama}?`} body="Absensi dan poinnya ikut hilang dari rekap." onOk={remove} />
    </Modal>
  )
}

function Import({ open, onClose, onDone }: { open: boolean; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const [pv, setPv] = useState<Preview | null>(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')
  useEffect(() => { if (open) { setPv(null); setErr('') } }, [open])
  async function upload(file: File) {
    setBusy(true); setErr('')
    const fd = new FormData()
    fd.append('file', file)
    try { setPv(await api<Preview>('/import/preview', { method: 'POST', form: fd })) } catch (e) { setErr((e as ApiError).message) } finally { setBusy(false) }
  }
  async function simpan() {
    setBusy(true)
    try { const r = await api<{ message: string }>('/import', { method: 'POST', body: { token: pv!.token } }); toast(r.message); onDone() }
    catch (e) { setErr((e as ApiError).message) } finally { setBusy(false) }
  }
  return (
    <Modal open={open} onClose={onClose} title="Import mahasiswa dari Excel">
      {!pv ? (
        <>
          <p className="muted" style={{ lineHeight: 1.5 }}>File .xlsx dengan kolom <strong>nama, nim, jurusan, kelas</strong> di baris pertama. Kamu bisa cek baris yang bermasalah sebelum disimpan.</p>
          <label className="pill" style={{ alignSelf: 'flex-start', cursor: busy ? 'wait' : 'pointer' }}>
            <Icon name="up" size={16} />{busy ? 'Membaca file…' : 'Pilih file Excel'}
            <input type="file" accept=".xlsx" className="sr" disabled={busy} onChange={e => e.target.files?.[0] && upload(e.target.files[0])} />
          </label>
          <a className="ghost" href="/api/import/template" download style={{ alignSelf: 'flex-start', paddingLeft: 0 }}><Icon name="down" size={16} />Unduh template</a>
        </>
      ) : (
        <>
          <div className="grid" style={{ gridTemplateColumns: 'repeat(3, minmax(0,1fr))' }}>
            <div className="tile stat"><div className="lbl">Baris</div><div className="big">{pv.total}</div></div>
            <div className="tile stat"><div className="lbl">Siap disimpan</div><div className="big" style={{ color: 'var(--hadir-ink)' }}>{pv.valid}</div></div>
            <div className="tile stat"><div className="lbl">Bermasalah</div><div className="big" style={{ color: pv.error ? 'var(--alpa-ink)' : undefined }}>{pv.error}</div></div>
          </div>
          {pv.error > 0 && (
            <div className="list" style={{ maxHeight: 240, overflowY: 'auto' }}>
              {pv.rows.filter(r => r.errors.length).map(r => (
                <div key={r.baris} className="li" style={{ alignItems: 'flex-start' }}>
                  <span className="s num" style={{ width: 56 }}>Baris {r.baris}</span>
                  <div className="grow1"><div className="t ellipsis">{r.nama || '(tanpa nama)'} <span className="s num">{r.nim}</span></div><div className="err">{r.errors.join(', ')}</div></div>
                </div>
              ))}
            </div>
          )}
          {pv.error > 0 && <p className="s">Baris bermasalah dilewati. Perbaiki di Excel lalu unggah lagi kalau perlu.</p>}
          <div className="row" style={{ justifyContent: 'flex-end' }}>
            <button className="pill" onClick={() => setPv(null)}>Unggah ulang</button>
            <button className="cta" disabled={busy || pv.valid === 0} onClick={simpan}>Simpan {pv.valid} mahasiswa</button>
          </div>
        </>
      )}
      {err && <p className="err" role="alert">{err}</p>}
    </Modal>
  )
}
