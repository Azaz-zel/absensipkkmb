import { StrictMode, type ReactNode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, createRoutesFromElements, Navigate, Outlet, Route, RouterProvider, useLocation } from 'react-router-dom'
import './styles.css'
import { AuthProvider, ToastProvider, useAuth, type Role } from './lib'
import Layout from './Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Sesi from './pages/Sesi'
import { AbsensiIndex, AbsensiKelas } from './pages/Absensi'
import Rekap from './pages/Rekap'
import Poin from './pages/Poin'
import Mahasiswa from './pages/Mahasiswa'
import Kelas from './pages/Kelas'
import Akun from './pages/Akun'
import Riwayat from './pages/Riwayat'
import Lainnya from './pages/Lainnya'

function Guard({ roles, children }: { roles?: Role[]; children: ReactNode }) {
  const { user, ready } = useAuth()
  const loc = useLocation()
  if (!ready) return null
  if (!user) return <Navigate to="/login" replace state={{ from: loc.pathname }} />
  if (roles && !roles.includes(user.role)) return <Navigate to="/" replace />
  return <>{children}</>
}

function Root() {
  const { ready } = useAuth()
  if (!ready) return <div className="bg" aria-busy="true" />
  return <Outlet />
}

function LoginRoute() {
  const { user } = useAuth()
  return user ? <Navigate to="/" replace /> : <Login />
}

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route element={<Root />}>
      <Route path="/login" element={<LoginRoute />} />
      <Route element={<Guard><Layout /></Guard>}>
        <Route index element={<Dashboard />} />
        <Route path="absensi" element={<Guard roles={['admin', 'pj']}><AbsensiIndex /></Guard>} />
        <Route path="absensi/:sesi/:kelas" element={<Guard roles={['admin', 'pj']}><AbsensiKelas /></Guard>} />
        <Route path="rekap" element={<Guard roles={['admin', 'pj']}><Rekap /></Guard>} />
        <Route path="poin" element={<Guard roles={['admin', 'manajer', 'pj']}><Poin /></Guard>} />
        <Route path="sesi" element={<Guard roles={['admin']}><Sesi /></Guard>} />
        <Route path="mahasiswa" element={<Guard roles={['admin']}><Mahasiswa /></Guard>} />
        <Route path="kelas" element={<Guard roles={['admin']}><Kelas /></Guard>} />
        <Route path="akun" element={<Guard roles={['admin']}><Akun /></Guard>} />
        <Route path="riwayat" element={<Guard roles={['admin']}><Riwayat /></Guard>} />
        <Route path="lainnya" element={<Lainnya />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Route>,
  ),
)

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ToastProvider>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </ToastProvider>
  </StrictMode>,
)
