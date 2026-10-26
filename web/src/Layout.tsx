import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { api, useAuth, type Role } from './lib'
import { Icon } from './ui'

type Item = { to: string; label: string; icon: string; tab?: string }

const MENU: Record<Role, { main: Item[]; kelola?: Item[]; tabs: Item[] }> = {
  admin: {
    main: [
      { to: '/', label: 'Dashboard', icon: 'grid' },
      { to: '/sesi', label: 'Sesi absensi', icon: 'cal' },
      { to: '/absensi', label: 'Isi absensi', icon: 'clip' },
      { to: '/rekap', label: 'Rekap kehadiran', icon: 'chart' },
      { to: '/poin', label: 'Poin pelanggaran', icon: 'flag' },
    ],
    kelola: [
      { to: '/mahasiswa', label: 'Mahasiswa', icon: 'users' },
      { to: '/kelas', label: 'Kelas', icon: 'school' },
      { to: '/akun', label: 'Akun pengguna', icon: 'shield' },
      { to: '/riwayat', label: 'Riwayat perubahan', icon: 'clock' },
    ],
    tabs: [
      { to: '/', label: 'Beranda', icon: 'grid' },
      { to: '/sesi', label: 'Sesi', icon: 'cal' },
      { to: '/rekap', label: 'Rekap', icon: 'chart' },
      { to: '/poin', label: 'Poin', icon: 'flag' },
      { to: '/lainnya', label: 'Lainnya', icon: 'more' },
    ],
  },
  pj: {
    main: [
      { to: '/', label: 'Beranda', icon: 'grid' },
      { to: '/absensi', label: 'Isi absensi', icon: 'clip' },
      { to: '/rekap', label: 'Rekap kelas', icon: 'chart' },
    ],
    tabs: [
      { to: '/', label: 'Beranda', icon: 'grid' },
      { to: '/absensi', label: 'Absensi', icon: 'clip' },
      { to: '/rekap', label: 'Rekap', icon: 'chart' },
    ],
  },
  manajer: {
    main: [
      { to: '/', label: 'Beranda', icon: 'grid' },
      { to: '/poin', label: 'Poin mahasiswa', icon: 'flag' },
    ],
    tabs: [
      { to: '/', label: 'Beranda', icon: 'grid' },
      { to: '/poin', label: 'Poin', icon: 'flag' },
    ],
  },
}

export const KELOLA = MENU.admin.kelola!

export function useLogout() {
  const { setUser } = useAuth()
  const nav = useNavigate()
  return async () => {
    try { await api('/logout', { method: 'POST' }) } catch { /* sesi di server mungkin sudah habis */ }
    setUser(null)
    nav('/login', { replace: true })
  }
}

export default function Layout() {
  const { user, kelas } = useAuth()
  const logout = useLogout()
  const m = MENU[user!.role]
  const sub = user!.role === 'pj' ? 'PJ kelas ' + (kelas.map(k => k.nama).join(', ') || '-') : user!.role === 'manajer' ? 'Manajer poin' : 'Admin panitia'
  return (
    <div className="bg">
      <div className="glass shell">
        <aside className="side" aria-label="Menu">
          <div className="brand">
            <div className="logo" aria-hidden="true">P</div>
            <div><strong>PKKMB 2026</strong><span>{sub}</span></div>
          </div>
          <nav aria-label="Menu utama" style={{ display: 'contents' }}>
            {m.main.map(i => (
              <NavLink key={i.to} to={i.to} end={i.to === '/'} className="nav"><Icon name={i.icon} />{i.label}</NavLink>
            ))}
            {m.kelola && <div className="navlbl">Kelola</div>}
            {m.kelola?.map(i => (
              <NavLink key={i.to} to={i.to} className="nav"><Icon name={i.icon} />{i.label}</NavLink>
            ))}
          </nav>
          <div className="grow" />
          <div className="li" style={{ padding: '12px 10px', border: 0 }}>
            <div className="grow1"><div className="t ellipsis">{user!.name}</div><div className="s ellipsis">{user!.username ?? user!.email}</div></div>
          </div>
          <button className="nav" onClick={logout}><Icon name="out" />Keluar</button>
        </aside>
        <main className="main" id="konten">
          <Outlet />
        </main>
      </div>
      <nav className="glass tabbar" aria-label="Menu utama">
        {m.tabs.map(i => (
          <NavLink key={i.to} to={i.to} end={i.to === '/'} className="tab"><Icon name={i.icon} size={20} />{i.label}</NavLink>
        ))}
      </nav>
    </div>
  )
}
