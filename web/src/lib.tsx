import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'

export type Role = 'admin' | 'pj' | 'manajer'
export type User = { id: number; name: string; email: string; username: string | null; role: Role; status: string }
export type Kelas = { id: number; nama: string; kategori: string }
export type Sanksi = { level: 'bersih' | 'ringan' | 'sedang' | 'berat' | 'merah'; label: string }
export type MabaPoin = {
  id: number; nama: string; nim: string; jurusan: string; kelas_id: number; kelas: string; kategori: string
  hadir: number; izin: number; sakit: number; alpa: number
  poin_absensi: number; poin_pelanggaran: number; total_poin: number; sanksi: Sanksi
}
export type StatusHadir = 'hadir' | 'izin' | 'sakit' | 'alpa'

export class ApiError extends Error {
  status: number
  fields: Record<string, string>
  constructor(status: number, msg: string, fields: Record<string, string> = {}) {
    super(msg)
    this.status = status
    this.fields = fields
  }
}

// Semua panggilan API lewat sini: cookie sesi ikut, header anti-CSRF dipasang.
export async function api<T = unknown>(path: string, opts: { method?: string; body?: unknown; form?: FormData } = {}): Promise<T> {
  const headers: Record<string, string> = { 'X-Requested-With': 'absensi' }
  let body: BodyInit | undefined
  if (opts.form) body = opts.form
  else if (opts.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(opts.body)
  }
  let res: Response
  try {
    res = await fetch('/api' + path, { method: opts.method ?? 'GET', headers, body, credentials: 'same-origin' })
  } catch {
    throw new ApiError(0, 'Tidak tersambung ke server. Periksa koneksi internet lalu coba lagi.')
  }
  if (res.status === 204) return undefined as T
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    if (res.status === 401 && path !== '/login' && path !== '/me') window.dispatchEvent(new Event('sesi-habis'))
    throw new ApiError(res.status, data.error ?? 'Terjadi kesalahan.', data.fields)
  }
  return data as T
}

export function qs(params: Record<string, string | number | undefined | null>) {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== null && v !== '' && v !== 0) p.set(k, String(v))
  const s = p.toString()
  return s ? '?' + s : ''
}

/** useApi memuat data dan memuat ulang saat path berubah atau reload() dipanggil. */
export function useApi<T>(path: string | null) {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [tick, setTick] = useState(0)
  useEffect(() => {
    if (!path) return
    let alive = true
    setError(null)
    api<T>(path).then(d => alive && setData(d)).catch((e: ApiError) => alive && setError(e.message))
    return () => { alive = false }
  }, [path, tick])
  return { data, error, reload: useCallback(() => setTick(t => t + 1), []), setData }
}

// ---------- Auth ----------

type AuthState = { user: User | null; kelas: Kelas[]; ready: boolean; setUser: (u: User | null) => void; refresh: () => Promise<void> }
const AuthCtx = createContext<AuthState>(null!)
export const useAuth = () => useContext(AuthCtx)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [kelas, setKelas] = useState<Kelas[]>([])
  const [ready, setReady] = useState(false)
  const refresh = useCallback(async () => {
    try {
      const d = await api<{ user: User; kelas: Kelas[] | null }>('/me')
      setUser(d.user)
      setKelas(d.kelas ?? [])
    } catch {
      setUser(null)
    } finally {
      setReady(true)
    }
  }, [])
  useEffect(() => { refresh() }, [refresh])
  useEffect(() => {
    const out = () => setUser(null)
    window.addEventListener('sesi-habis', out)
    return () => window.removeEventListener('sesi-habis', out)
  }, [])
  return <AuthCtx.Provider value={{ user, kelas, ready, setUser, refresh }}>{children}</AuthCtx.Provider>
}

// ---------- Toast ----------

type ToastFn = (msg: string, kind?: 'ok' | 'error') => void
const ToastCtx = createContext<ToastFn>(() => {})
export const useToast = () => useContext(ToastCtx)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [t, setT] = useState<{ msg: string; kind: string; id: number } | null>(null)
  const timer = useRef<number>(0)
  const show = useCallback<ToastFn>((msg, kind = 'ok') => {
    window.clearTimeout(timer.current)
    setT({ msg, kind, id: Date.now() })
    timer.current = window.setTimeout(() => setT(null), kind === 'error' ? 6000 : 3500)
  }, [])
  return (
    <ToastCtx.Provider value={show}>
      {children}
      <div aria-live="polite" role="status">
        {t && <div key={t.id} className={'toast ' + (t.kind === 'error' ? 'error' : '')}>{t.msg}</div>}
      </div>
    </ToastCtx.Provider>
  )
}

// ---------- Format ----------

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
export function tgl(iso: string, opts: { hari?: boolean; tahun?: boolean } = {}) {
  const d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso)
  let s = `${d.getDate()} ${BULAN[d.getMonth()]}`
  if (opts.tahun) s += ` ${d.getFullYear()}`
  if (opts.hari) s = `${HARI[d.getDay()]}, ${s}`
  return s
}
export function jam(iso: string) {
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
export const angka = (n: number) => n.toLocaleString('id-ID')
export const persen = (a: number, b: number) => (b > 0 ? (a / b) * 100 : 0)
export const fmtPersen = (p: number) => p.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%'
export function sapaan() {
  const h = new Date().getHours()
  return h < 11 ? 'Pagi' : h < 15 ? 'Siang' : h < 18 ? 'Sore' : 'Malam'
}
export const inisial = (nama: string) =>
  nama.split(/\s+/).filter(w => !['I', 'Ni', 'Gusti'].includes(w)).slice(0, 2).map(w => w[0]).join('').toUpperCase()

export const STATUS_LABEL: Record<string, string> = { dibuka: 'Dibuka', ditutup: 'Ditutup', belum_dibuka: 'Belum dibuka' }
export const HADIR_LABEL: Record<StatusHadir, string> = { hadir: 'Hadir', izin: 'Izin', sakit: 'Sakit', alpa: 'Alpa' }
export const SANKSI_LABEL: Record<string, string> = { bersih: 'Bersih', ringan: 'Ringan', sedang: 'Sedang', berat: 'Berat', merah: 'Cap merah' }
export const ROLE_LABEL: Record<Role, string> = { admin: 'Admin', pj: 'PJ', manajer: 'Manajer' }
