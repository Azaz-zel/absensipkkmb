import { Link } from 'react-router-dom'
import { useAuth } from '../lib'
import { KELOLA, useLogout } from '../Layout'
import { Icon } from '../ui'

// Menu tambahan untuk tab "Lainnya" di HP.
export default function Lainnya() {
  const { user } = useAuth()
  const logout = useLogout()
  const items = user!.role === 'admin' ? [{ to: '/absensi', label: 'Isi absensi', icon: 'clip' }, ...KELOLA] : []
  return (
    <>
      <div><h1 className="h1">Lainnya</h1><p className="sub">{user!.name} · {user!.username ?? user!.email}</p></div>
      {items.length > 0 && (
        <section className="glass card" style={{ gap: 0, padding: '6px 16px' }}>
          <nav className="list" aria-label="Menu lainnya">
            {items.map(i => (
              <Link key={i.to} className="li" to={i.to} style={{ padding: '14px 4px' }}><Icon name={i.icon} /><span className="grow1 t">{i.label}</span><Icon name="chev" /></Link>
            ))}
          </nav>
        </section>
      )}
      <button className="pill" onClick={logout} style={{ alignSelf: 'flex-start' }}><Icon name="out" size={16} />Keluar</button>
    </>
  )
}
