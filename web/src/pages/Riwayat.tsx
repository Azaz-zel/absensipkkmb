import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { jam, qs, ROLE_LABEL, tgl, useApi, type Role } from '../lib'
import { Loading, Pager, Search, useDebounced } from '../ui'

type R = {
  data: { id: number; created_at: string; aksi: string; label: string; keterangan: string; pelaku: string; role: Role | null }[]
  total: number; page: number; per_page: number; hari_ini: number
  users: { id: number; name: string; role: Role }[]
  aksi: Record<string, string>
}

export default function Riwayat() {
  const [sp, setSp] = useSearchParams()
  const [q, setQ] = useState(sp.get('q') ?? '')
  const dq = useDebounced(q)
  const set = (k: string, v: string) => setSp(p => { const n = new URLSearchParams(p); if (v) n.set(k, v); else n.delete(k); if (k !== 'page') n.delete('page'); return n }, { replace: true })
  useEffect(() => { if ((sp.get('q') ?? '') !== dq) set('q', dq) }, [dq]) // eslint-disable-line react-hooks/exhaustive-deps
  const { data, error, reload } = useApi<R>('/riwayat' + qs({ q: sp.get('q'), aksi: sp.get('aksi'), user_id: sp.get('user_id'), tanggal: sp.get('tanggal'), page: sp.get('page') }))

  // Kelompokkan per hari supaya mudah dipindai.
  const groups: { day: string; rows: R['data'] }[] = []
  data?.data.forEach(r => {
    const day = new Date(r.created_at).toLocaleDateString('sv-SE')
    if (groups.at(-1)?.day !== day) groups.push({ day, rows: [] })
    groups.at(-1)!.rows.push(r)
  })
  const hariIni = new Date().toLocaleDateString('sv-SE')

  return (
    <>
      <div className="top">
        <div><h1 className="h1">Riwayat perubahan</h1><p className="sub">Siapa mengubah apa dan kapan. Catatan ini tidak bisa diubah atau dihapus.</p></div>
        {data && <span className="chip c-ditutup num" style={{ fontSize: 13, padding: '6px 12px' }}>{data.hari_ini} perubahan hari ini</span>}
      </div>
      <section className="glass card" style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, padding: '14px 16px', alignItems: 'center' }}>
        <div style={{ flex: '999 1 240px' }}><Search value={q} onChange={setQ} placeholder="Cari nama maba atau pelaku" label="Cari riwayat" /></div>
        <label><span className="sr">Aksi</span>
          <select className="field round" value={sp.get('aksi') ?? ''} onChange={e => set('aksi', e.target.value)}>
            <option value="">Semua aksi</option>{data && Object.entries(data.aksi).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </label>
        <label><span className="sr">Pelaku</span>
          <select className="field round" value={sp.get('user_id') ?? ''} onChange={e => set('user_id', e.target.value)}>
            <option value="">Semua pengguna</option>{data?.users.map(u => <option key={u.id} value={u.id}>{u.name} ({ROLE_LABEL[u.role]})</option>)}
          </select>
        </label>
        <label><span className="sr">Tanggal</span><input className="field round" type="date" value={sp.get('tanggal') ?? ''} onChange={e => set('tanggal', e.target.value)} /></label>
      </section>
      {!data ? <Loading error={error} retry={reload} /> : data.data.length === 0 ? (
        <div className="glass card"><p className="empty">Belum ada perubahan yang cocok. Riwayat terisi saat absensi diubah, kelas dibuka/ditutup, atau pelanggaran dicatat.</p></div>
      ) : (
        <section className="glass card" style={{ gap: 10 }}>
          {groups.map(g => (
            <div key={g.day}>
              <div className="lbl" style={{ padding: '4px 0 8px' }}>{g.day === hariIni ? 'Hari ini · ' : ''}{tgl(g.day, { hari: true, tahun: true })}</div>
              <div className="list">
                {g.rows.map(r => (
                  <div key={r.id} className="li" style={{ alignItems: 'flex-start' }}>
                    <span className="s num" style={{ width: 48, paddingTop: 2, flexShrink: 0 }}>{jam(r.created_at)}</span>
                    <div className="grow1"><div className="t">{r.label}</div><div className="s" style={{ overflowWrap: 'anywhere' }}>{r.keterangan}</div></div>
                    <div style={{ textAlign: 'right', flexShrink: 0, maxWidth: '35%' }}><div className="ellipsis" style={{ fontSize: 14, fontWeight: 600 }}>{r.pelaku}</div><div className="s">{r.role ? ROLE_LABEL[r.role] : 'akun terhapus'}</div></div>
                  </div>
                ))}
              </div>
            </div>
          ))}
          <Pager page={data.page} total={data.total} per={data.per_page} onPage={p => set('page', String(p))} />
        </section>
      )}
    </>
  )
}
