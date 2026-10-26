import { useEffect, useState, type FormEvent } from 'react'
import { api, ApiError, ROLE_LABEL, useApi, useToast, type Role } from '../lib'
import { Confirm, Field, Loading, Modal, Search } from '../ui'

type Akun = { id: number; name: string; email: string; username: string | null; role: Role; status: string; kelas_ids: number[]; kelas: string[]; saya: boolean }

export default function AkunPage() {
  const toast = useToast()
  const { data, error, reload } = useApi<{ data: Akun[] }>('/users')
  const kelas = useApi<{ kelas: { id: number; nama: string; kategori: string }[] }>('/filter')
  const [role, setRole] = useState<'' | Role>('')
  const [q, setQ] = useState('')
  const [form, setForm] = useState<Partial<Akun> | null>(null)
  const [toggle, setToggle] = useState<Akun | null>(null)

  const all = data?.data ?? []
  const term = q.trim().toLowerCase()
  const rows = all.filter(a => (!role || a.role === role) && (!term || a.name.toLowerCase().includes(term) || (a.username ?? '').toLowerCase().includes(term) || a.email.includes(term)))
  const n = (r: Role) => all.filter(a => a.role === r).length

  async function doToggle() {
    const a = toggle!
    setToggle(null)
    try { const r = await api<{ message: string }>(`/users/${a.id}/status`, { method: 'PATCH' }); toast(`${a.name}: ${r.message}`); reload() } catch (e) { toast((e as ApiError).message, 'error') }
  }

  return (
    <>
      <div className="top">
        <div><h1 className="h1">Akun pengguna</h1><p className="sub">PJ hanya bisa melihat dan mengisi kelas yang ditugaskan ke akunnya.</p></div>
        <button className="cta" onClick={() => setForm({ role: 'pj', status: 'aktif', kelas_ids: [] })}>Tambah akun</button>
      </div>
      <section className="glass card">
        <div className="hd">
          <div className="row" role="group" aria-label="Filter peran">
            {([['', 'Semua', all.length], ['pj', 'PJ', n('pj')], ['manajer', 'Manajer', n('manajer')], ['admin', 'Admin', n('admin')]] as const).map(([v, l, c]) => (
              <button key={v} className={'pill sm' + (role === v ? ' on' : '')} aria-pressed={role === v} onClick={() => setRole(v)}>{l} · {c}</button>
            ))}
          </div>
          <div style={{ width: 260, maxWidth: '100%' }}><Search value={q} onChange={setQ} placeholder="Cari nama atau username" label="Cari akun" /></div>
        </div>
        {!data ? <Loading error={error} retry={reload} /> : rows.length === 0 ? <p className="empty">Tidak ada akun yang cocok.</p> : (
          <table className="tbl cards">
            <thead><tr><th>Nama</th><th>Peran</th><th>Kelas</th><th>Status</th><th className="r"><span className="sr">Aksi</span></th></tr></thead>
            <tbody>
              {rows.map(a => (
                <tr key={a.id}>
                  <td className="first"><div className="t">{a.name}{a.saya && <span className="s"> (kamu)</span>}</div><div className="s ellipsis">{a.username ?? '—'} · {a.email}</div></td>
                  <td data-label="Peran">{ROLE_LABEL[a.role]}</td>
                  <td data-label="Kelas" className={a.kelas.length ? '' : 'muted'}>{a.role === 'admin' ? 'Semua kelas' : a.role === 'manajer' ? 'Tidak perlu' : a.kelas.join(', ') || 'Belum ada'}</td>
                  <td data-label="Status"><span className={'chip ' + (a.status === 'aktif' ? 'c-dibuka' : 'c-ditutup')}>{a.status === 'aktif' ? 'Aktif' : 'Nonaktif'}</span></td>
                  <td className="r act" style={{ whiteSpace: 'nowrap' }}>
                    <button className="ghost" onClick={() => setForm(a)}>Ubah</button>
                    {!a.saya && <button className={'ghost' + (a.status === 'aktif' ? ' danger' : '')} onClick={() => setToggle(a)}>{a.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
      <AkunForm value={form} kelas={kelas.data?.kelas ?? []} onClose={() => setForm(null)} onSaved={() => { setForm(null); reload() }} />
      <Confirm open={!!toggle} onClose={() => setToggle(null)} danger={toggle?.status === 'aktif'} ok={toggle?.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}
        title={`${toggle?.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'} ${toggle?.name}?`}
        body={toggle?.status === 'aktif' ? 'Akun ini langsung keluar dari semua perangkat dan tidak bisa masuk sampai diaktifkan lagi.' : 'Akun ini bisa masuk lagi dengan kata sandinya.'} onOk={doToggle} />
    </>
  )
}

function AkunForm({ value, kelas, onClose, onSaved }: { value: Partial<Akun> | null; kelas: { id: number; nama: string; kategori: string }[]; onClose: () => void; onSaved: () => void }) {
  const toast = useToast()
  const [f, setF] = useState({ name: '', email: '', username: '', password: '', role: 'pj' as Role, status: 'aktif', kelas_ids: [] as number[] })
  const [err, setErr] = useState<Record<string, string>>({})
  const [hapus, setHapus] = useState(false)
  useEffect(() => {
    if (value) { setF({ name: value.name ?? '', email: value.email ?? '', username: value.username ?? '', password: '', role: value.role ?? 'pj', status: value.status ?? 'aktif', kelas_ids: value.kelas_ids ?? [] }); setErr({}) }
  }, [value])
  const edit = !!value?.id
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      const r = await api<{ message: string }>(edit ? `/users/${value!.id}` : '/users', { method: edit ? 'PUT' : 'POST', body: f })
      toast(r.message)
      onSaved()
    } catch (e) { setErr((e as ApiError).fields); if (!Object.keys((e as ApiError).fields).length) toast((e as ApiError).message, 'error') }
  }
  async function remove() {
    setHapus(false)
    try { const r = await api<{ message: string }>(`/users/${value!.id}`, { method: 'DELETE' }); toast(r.message); onSaved() } catch (e) { toast((e as ApiError).message, 'error') }
  }
  const tog = (id: number) => setF(x => ({ ...x, kelas_ids: x.kelas_ids.includes(id) ? x.kelas_ids.filter(k => k !== id) : [...x.kelas_ids, id] }))
  return (
    <Modal open={!!value} onClose={onClose} title={edit ? `Ubah akun ${value!.name}` : 'Tambah akun'}>
      <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <Field label="Nama" error={err.name}><input className="field" value={f.name} onChange={e => setF({ ...f, name: e.target.value })} required maxLength={255} aria-invalid={!!err.name} /></Field>
        <div className="row" style={{ gap: 12, alignItems: 'flex-start' }}>
          <div style={{ flex: '1 1 180px' }}><Field label="Email" error={err.email}><input className="field" type="email" value={f.email} onChange={e => setF({ ...f, email: e.target.value })} required autoComplete="off" aria-invalid={!!err.email} /></Field></div>
          <div style={{ flex: '1 1 160px' }}><Field label="Username" error={err.username}><input className="field" value={f.username} onChange={e => setF({ ...f, username: e.target.value })} required autoComplete="off" aria-invalid={!!err.username} /></Field></div>
        </div>
        <Field label={edit ? 'Kata sandi baru (kosongkan kalau tidak diganti)' : 'Kata sandi (minimal 8 karakter)'} error={err.password}>
          <input className="field" type="password" value={f.password} onChange={e => setF({ ...f, password: e.target.value })} required={!edit} minLength={8} autoComplete="new-password" aria-invalid={!!err.password} />
        </Field>
        <div className="row" style={{ gap: 12, alignItems: 'flex-start' }}>
          <div style={{ flex: '1 1 160px' }}><Field label="Peran" error={err.role}><select className="field" value={f.role} onChange={e => setF({ ...f, role: e.target.value as Role })} disabled={value?.saya}><option value="pj">PJ kelas</option><option value="manajer">Manajer poin</option><option value="admin">Admin</option></select></Field></div>
          <div style={{ flex: '1 1 160px' }}><Field label="Status" error={err.status}><select className="field" value={f.status} onChange={e => setF({ ...f, status: e.target.value })} disabled={value?.saya}><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></Field></div>
        </div>
        {f.role === 'pj' && (
          <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
            <legend className="lbl" style={{ marginBottom: 6 }}>Kelas yang dipegang</legend>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, maxHeight: 160, overflowY: 'auto' }}>
              {kelas.map(k => (
                <button type="button" key={k.id} className={'pill sm' + (f.kelas_ids.includes(k.id) ? ' on' : '')} aria-pressed={f.kelas_ids.includes(k.id)} onClick={() => tog(k.id)}>{k.nama}</button>
              ))}
            </div>
          </fieldset>
        )}
        <div className="row" style={{ justifyContent: 'space-between' }}>
          {edit && !value?.saya ? <button type="button" className="ghost danger" onClick={() => setHapus(true)}>Hapus akun</button> : <span />}
          <div className="row"><button type="button" className="pill" onClick={onClose}>Batal</button><button className="cta">Simpan</button></div>
        </div>
      </form>
      <Confirm open={hapus} onClose={() => setHapus(false)} danger ok="Hapus akun" title={`Hapus akun ${value?.name}?`}
        body="Riwayat perubahan tetap menyimpan namanya. Kalau hanya ingin memblokir sementara, pakai Nonaktifkan." onOk={remove} />
    </Modal>
  )
}
