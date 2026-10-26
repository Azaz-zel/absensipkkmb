import { useEffect, useState, type FormEvent } from 'react'
import { api, ApiError, useApi, useToast } from '../lib'
import { Confirm, Field, Loading, Modal } from '../ui'

type K = { id: number; nama: string; kategori: 'pagi' | 'malam'; kapasitas: number | null; maba: number; pj: string[] }

export default function Kelas() {
  const { data, error, reload } = useApi<{ data: K[] }>('/kelas')
  const [form, setForm] = useState<Partial<K> | null>(null)
  const groups = (['pagi', 'malam'] as const).map(k => ({ k, rows: data?.data.filter(x => x.kategori === k) ?? [] }))
  return (
    <>
      <div className="top">
        <div>
          <h1 className="h1">Kelas</h1>
          <p className="sub">{data ? `${data.data.length} kelas: ${groups[0].rows.length} pagi, ${groups[1].rows.length} malam.` : 'Memuat…'} Kelas pagi 1 huruf, kelas malam 2 huruf.</p>
        </div>
        <button className="cta" onClick={() => setForm({ kategori: 'pagi' })}>Tambah kelas</button>
      </div>
      {!data ? <Loading error={error} retry={reload} /> : (
        <div className="cols">
          {groups.map(g => (
            <section key={g.k} className="glass card" style={{ flex: '1 1 400px', gap: 6 }}>
              <div className="hd" style={{ paddingBottom: 6 }}>
                <h2 className="h2">Kelas {g.k}</h2>
                <span className="s num">{g.rows.length} kelas · {g.rows.reduce((a, x) => a + x.maba, 0)} maba</span>
              </div>
              {g.rows.length === 0 ? <p className="empty">Belum ada kelas {g.k}.</p> : (
                <div className="list">
                  {g.rows.map(k => (
                    <div key={k.id} className="li">
                      <div style={{ width: 46, height: 46, borderRadius: 12, background: g.k === 'pagi' ? 'var(--tint-pagi)' : 'var(--tint-malam)', display: 'grid', placeItems: 'center', fontWeight: 700, fontSize: 15, flexShrink: 0 }}>{k.nama}</div>
                      <div className="grow1">
                        <div className="t ellipsis">{k.pj.length ? 'PJ ' + k.pj.join(', ') : 'Belum ada PJ'}</div>
                        <div className="s num">{k.maba} maba{k.kapasitas ? ` · kapasitas ${k.kapasitas}` : ''}</div>
                      </div>
                      <a className="ghost hide-m" href={`/api/export/kelas/${k.id}`} download>Export</a>
                      <button className="ghost" aria-label={`Ubah kelas ${k.nama}`} onClick={() => setForm(k)}>Ubah</button>
                    </div>
                  ))}
                </div>
              )}
            </section>
          ))}
        </div>
      )}
      <KelasForm value={form} onClose={() => setForm(null)} onSaved={() => { setForm(null); reload() }} />
    </>
  )
}

function KelasForm({ value, onClose, onSaved }: { value: Partial<K> | null; onClose: () => void; onSaved: () => void }) {
  const toast = useToast()
  const [nama, setNama] = useState('')
  const [kategori, setKategori] = useState<'pagi' | 'malam'>('pagi')
  const [kap, setKap] = useState('')
  const [err, setErr] = useState<Record<string, string>>({})
  const [hapus, setHapus] = useState(false)
  useEffect(() => { if (value) { setNama(value.nama ?? ''); setKategori(value.kategori ?? 'pagi'); setKap(value.kapasitas ? String(value.kapasitas) : ''); setErr({}) } }, [value])
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      const r = await api<{ message: string }>(value?.id ? `/kelas/${value.id}` : '/kelas', { method: value?.id ? 'PUT' : 'POST', body: { nama, kategori, kapasitas: kap ? Number(kap) : null } })
      toast(r.message)
      onSaved()
    } catch (e) { setErr((e as ApiError).fields); if (!Object.keys((e as ApiError).fields).length) toast((e as ApiError).message, 'error') }
  }
  async function remove() {
    setHapus(false)
    try { const r = await api<{ message: string }>(`/kelas/${value!.id}`, { method: 'DELETE' }); toast(r.message); onSaved() } catch (e) { toast((e as ApiError).message, 'error') }
  }
  return (
    <Modal open={!!value} onClose={onClose} title={value?.id ? `Ubah kelas ${value.nama}` : 'Tambah kelas'}>
      <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div className="seg" role="group" aria-label="Kategori" style={{ display: 'flex' }}>
          {(['pagi', 'malam'] as const).map(k => (
            <button type="button" key={k} aria-pressed={kategori === k} className="sb" style={{ flex: 1, ...(kategori === k ? { background: '#fff', color: 'var(--ink)' } : {}) }} onClick={() => setKategori(k)}>
              {k === 'pagi' ? 'Pagi' : 'Malam'}
            </button>
          ))}
        </div>
        <Field label={kategori === 'pagi' ? 'Nama kelas (1 huruf, A–Z)' : 'Nama kelas (2 huruf, AA–ZZ)'} error={err.nama}>
          <input className="field" value={nama} onChange={e => setNama(e.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2))} required aria-invalid={!!err.nama} autoCapitalize="characters" />
        </Field>
        <Field label="Kapasitas (opsional)" error={err.kapasitas}>
          <input className="field num" value={kap} onChange={e => setKap(e.target.value.replace(/\D/g, '').slice(0, 4))} inputMode="numeric" />
        </Field>
        {!value?.id && <p className="s">Kelas baru otomatis masuk ke semua sesi yang sudah ada dengan status belum dibuka.</p>}
        <div className="row" style={{ justifyContent: 'space-between' }}>
          {value?.id ? <button type="button" className="ghost danger" onClick={() => setHapus(true)}>Hapus kelas</button> : <span />}
          <div className="row"><button type="button" className="pill" onClick={onClose}>Batal</button><button className="cta">Simpan</button></div>
        </div>
      </form>
      <Confirm open={hapus} onClose={() => setHapus(false)} danger ok="Hapus kelas" title={`Hapus kelas ${value?.nama}?`}
        body="Kelas hanya bisa dihapus kalau sudah tidak punya mahasiswa. Data absensi kelas ini di semua sesi ikut terhapus." onOk={remove} />
    </Modal>
  )
}
