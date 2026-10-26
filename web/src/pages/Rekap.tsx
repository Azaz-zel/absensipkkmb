import { useNavigate, useSearchParams } from 'react-router-dom'
import { useState, useEffect, type ReactNode } from 'react'
import { angka, fmtPersen, persen, qs, useApi, useAuth, type MabaPoin } from '../lib'
import { Bar, Loading, Pager, SanksiChip, Search, Stat, useDebounced } from '../ui'

type Opsi = { kelas: { id: number; nama: string; kategori: string }[]; sesi: { id: number; nama: string }[]; jurusan: string[] }
type RekapM = { data: MabaPoin[]; total: number; page: number; per_page: number; jumlah: { hadir: number; izin: number; sakit: number; alpa: number }; jumlah_sesi: number }
type RekapK = { data: { id: number; nama: string; kategori: string; maba: number; pj: string[]; hadir: number; izin: number; sakit: number; alpa: number; belum: number; persen: number }[]; jumlah_sesi: number }

export default function Rekap() {
  const { user, kelas: kelasSaya } = useAuth()
  const admin = user!.role === 'admin'
  const nav = useNavigate()
  const [sp, setSp] = useSearchParams()
  const tab = sp.get('tab') === 'kelas' ? 'kelas' : 'mahasiswa'
  const [q, setQ] = useState(sp.get('q') ?? '')
  const dq = useDebounced(q)
  const set = (k: string, v: string) => setSp(p => { const n = new URLSearchParams(p); if (v) n.set(k, v); else n.delete(k); if (k !== 'page') n.delete('page'); return n }, { replace: true })
  useEffect(() => { if ((sp.get('q') ?? '') !== dq) set('q', dq) }, [dq]) // eslint-disable-line react-hooks/exhaustive-deps

  const opsi = useApi<Opsi>('/filter')
  const params = { q: sp.get('q'), kelas_id: sp.get('kelas_id'), sesi: sp.get('sesi'), jurusan: sp.get('jurusan'), kategori: sp.get('kategori'), poin: sp.get('poin'), sort: sp.get('sort') ?? (tab === 'mahasiswa' ? 'persen_asc' : ''), page: sp.get('page') }
  const m = useApi<RekapM>(tab === 'mahasiswa' ? '/rekap/mahasiswa' + qs(params) : null)
  const k = useApi<RekapK>(tab === 'kelas' ? '/rekap/kelas' + qs(params) : null)

  const j = tab === 'mahasiswa' ? m.data?.jumlah : k.data?.data.reduce((a, x) => ({ hadir: a.hadir + x.hadir, izin: a.izin + x.izin, sakit: a.sakit + x.sakit, alpa: a.alpa + x.alpa }), { hadir: 0, izin: 0, sakit: 0, alpa: 0 })
  const jSesi = (tab === 'mahasiswa' ? m.data?.jumlah_sesi : k.data?.jumlah_sesi) ?? 0
  const totalAbsen = j ? j.hadir + j.izin + j.sakit + j.alpa : 0
  const judul = admin ? 'Rekap kehadiran' : `Rekap kelas ${kelasSaya.map(x => x.nama).join(', ')}`

  return (
    <>
      <div className="top">
        <div><h1 className="h1">{judul}</h1><p className="sub">{jSesi ? `${jSesi} sesi dihitung${sp.get('sesi') ? '' : ' (semua sesi)'}` : 'Belum ada sesi.'}</p></div>
        {admin && <a className="pill" href={'/api/export/rekap' + qs({ ...params, page: null })} download>Export Excel</a>}
      </div>

      <div className="grid">
        <Stat label="Rata-rata hadir" value={fmtPersen(persen(j?.hadir ?? 0, totalAbsen))} note={`dari ${angka(totalAbsen)} kehadiran tercatat`} />
        <Stat label="Hadir" value={angka(j?.hadir ?? 0)} color="var(--hadir-ink)" />
        <Stat label="Izin dan sakit" value={angka((j?.izin ?? 0) + (j?.sakit ?? 0))} note="masing-masing 3 poin" color="var(--izin-ink)" />
        <Stat label="Alpa" value={angka(j?.alpa ?? 0)} note="masing-masing 5 poin" color="var(--alpa-ink)" />
      </div>

      <section className="glass card">
        <div className="hd">
          <div className="row" role="tablist" aria-label="Jenis rekap">
            <button role="tab" aria-selected={tab === 'mahasiswa'} className={'pill' + (tab === 'mahasiswa' ? ' on' : '')} onClick={() => set('tab', '')}>Per mahasiswa</button>
            <button role="tab" aria-selected={tab === 'kelas'} className={'pill' + (tab === 'kelas' ? ' on' : '')} onClick={() => set('tab', 'kelas')}>Per kelas</button>
          </div>
          {tab === 'mahasiswa' && <div style={{ width: 240, maxWidth: '100%', flex: '1 1 200px' }}><Search value={q} onChange={setQ} placeholder="Cari nama atau NIM" label="Cari maba" /></div>}
          <div className="row filters">
            {admin && tab === 'mahasiswa' && (
              <Sel value={sp.get('kelas_id') ?? ''} onChange={v => set('kelas_id', v)} label="Kelas"><option value="">Semua kelas</option>{opsi.data?.kelas.map(x => <option key={x.id} value={x.id}>{x.nama} · {x.kategori}</option>)}</Sel>
            )}
            {admin && tab === 'kelas' && (
              <Sel value={sp.get('kategori') ?? ''} onChange={v => set('kategori', v)} label="Kategori"><option value="">Pagi dan malam</option><option value="pagi">Pagi</option><option value="malam">Malam</option></Sel>
            )}
            <Sel value={sp.get('sesi') ?? ''} onChange={v => set('sesi', v)} label="Sesi"><option value="">Semua sesi</option>{opsi.data?.sesi.map(x => <option key={x.id} value={x.id}>{x.nama}</option>)}</Sel>
            <Sel value={sp.get('jurusan') ?? ''} onChange={v => set('jurusan', v)} label="Jurusan"><option value="">Semua jurusan</option>{opsi.data?.jurusan.map(x => <option key={x} value={x}>{x}</option>)}</Sel>
            {tab === 'mahasiswa' && (
              <Sel value={sp.get('poin') ?? ''} onChange={v => set('poin', v)} label="Poin"><option value="">Semua poin</option><option value="kena">Kena poin</option><option value="aman">Bersih (0 poin)</option><option value="berat">10 poin ke atas</option><option value="merah">Cap merah (20+)</option></Sel>
            )}
            <label><span className="sr">Urutkan</span>
              <select className="field round" value={params.sort} onChange={e => set('sort', e.target.value)}>
                {tab === 'mahasiswa' ? <>
                  <option value="persen_asc">Hadir terendah dulu</option><option value="persen_desc">Hadir tertinggi dulu</option>
                  <option value="poin_desc">Poin tertinggi dulu</option><option value="nama">Nama A–Z</option>
                </> : <>
                  <option value="">Urut kelas</option><option value="persen_asc">Hadir terendah dulu</option><option value="persen_desc">Hadir tertinggi dulu</option>
                </>}
              </select>
            </label>
          </div>
        </div>

        {tab === 'mahasiswa' ? (!m.data ? <Loading error={m.error} retry={m.reload} /> : m.data.data.length === 0 ? <p className="empty">Tidak ada maba yang cocok dengan filter ini.</p> : (
          <>
            <table className="tbl cards">
              <thead><tr><th>Mahasiswa</th><th>Kelas</th><th className="r">Hadir</th><th className="r">Izin</th><th className="r">Sakit</th><th className="r">Alpa</th><th>% hadir</th><th className="r">Poin</th><th>Sanksi</th></tr></thead>
              <tbody>
                {m.data.data.map(x => {
                  const p = persen(x.hadir, x.hadir + x.izin + x.sakit + x.alpa)
                  return (
                    <tr key={x.id} onClick={() => nav('/poin?id=' + x.id)} style={{ cursor: 'pointer' }}>
                      <td className="first"><a href={'/poin?id=' + x.id} onClick={e => { e.preventDefault(); nav('/poin?id=' + x.id) }} className="t" style={{ color: 'var(--ink)', textDecoration: 'none' }}>{x.nama}</a><div className="s num">{x.nim}</div></td>
                      <td data-label="Kelas" className="muted">{x.kelas}</td>
                      <td className="r num" data-label="Hadir">{x.hadir}</td><td className="r num" data-label="Izin">{x.izin}</td>
                      <td className="r num" data-label="Sakit">{x.sakit}</td><td className="r num" data-label="Alpa">{x.alpa}</td>
                      <td data-label="% hadir"><div className="row" style={{ gap: 8, flexWrap: 'nowrap' }}><div style={{ width: 70 }}><Bar value={p} /></div><span className="num" style={{ fontSize: 13.5 }}>{fmtPersen(p)}</span></div></td>
                      <td className="r num t" data-label="Poin">{x.total_poin}</td>
                      <td data-label="Sanksi"><SanksiChip s={x.sanksi} /></td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
            <Pager page={m.data.page} total={m.data.total} per={m.data.per_page} onPage={p => set('page', String(p))} />
          </>
        )) : (!k.data ? <Loading error={k.error} retry={k.reload} /> : k.data.data.length === 0 ? <p className="empty">Belum ada kelas.</p> : (
          <table className="tbl cards">
            <thead><tr><th>Kelas</th><th>PJ</th><th className="r">Maba</th><th className="r">Hadir</th><th className="r">Izin</th><th className="r">Sakit</th><th className="r">Alpa</th><th className="r">Belum diabsen</th><th>% hadir</th></tr></thead>
            <tbody>
              {k.data.data.map(x => (
                <tr key={x.id}>
                  <td className="first"><strong>{x.nama}</strong> <span className="muted">· {x.kategori === 'pagi' ? 'Pagi' : 'Malam'}</span></td>
                  <td data-label="PJ" className="muted">{x.pj.join(', ') || '—'}</td>
                  <td className="r num" data-label="Maba">{x.maba}</td><td className="r num" data-label="Hadir">{x.hadir}</td><td className="r num" data-label="Izin">{x.izin}</td>
                  <td className="r num" data-label="Sakit">{x.sakit}</td><td className="r num" data-label="Alpa">{x.alpa}</td><td className="r num" data-label="Belum diabsen">{x.belum}</td>
                  <td data-label="% hadir"><div className="row" style={{ gap: 8, flexWrap: 'nowrap' }}><div style={{ width: 90 }}><Bar value={x.persen} /></div><span className="num" style={{ fontSize: 13.5 }}>{fmtPersen(x.persen)}</span></div></td>
                </tr>
              ))}
            </tbody>
          </table>
        ))}
      </section>
    </>
  )
}

function Sel({ value, onChange, label, children }: { value: string; onChange: (v: string) => void; label: string; children: ReactNode }) {
  return (
    <label><span className="sr">{label}</span>
      <select className="field round" value={value} onChange={e => onChange(e.target.value)}>{children}</select>
    </label>
  )
}
