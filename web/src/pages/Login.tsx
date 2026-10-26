import { useState, type FormEvent } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { api, ApiError, useAuth, type User } from '../lib'

export default function Login() {
  const { refresh } = useAuth()
  const nav = useNavigate()
  const loc = useLocation()
  const [login, setLogin] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError('')
    try {
      await api<User>('/login', { method: 'POST', body: { login, password } })
      await refresh()
      nav((loc.state as { from?: string })?.from ?? '/', { replace: true })
    } catch (err) {
      setError((err as ApiError).message)
      setBusy(false)
    }
  }

  return (
    <div className="bg" style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: 64, padding: '48px 20px' }}>
      <div style={{ flex: '1 1 340px', maxWidth: 520 }}>
        <div className="row" style={{ gap: 10, marginBottom: 36 }}>
          <div className="logo" aria-hidden="true">P</div>
          <strong style={{ fontSize: 15 }}>PKKMB INSTIKI 2026</strong>
        </div>
        <h1 style={{ fontSize: 'clamp(38px, 6vw, 56px)', fontWeight: 600, letterSpacing: '-.03em', lineHeight: 1.03 }}>
          Selamat bertugas, <span className="serif">panitia.</span>
        </h1>
        <p className="muted" style={{ marginTop: 18, fontSize: 17, lineHeight: 1.55, maxWidth: 440 }}>
          Masuk untuk mengisi absensi kelas, melihat rekap, atau mencatat poin maba.
        </p>
      </div>
      <form className="glass card" onSubmit={submit} style={{ flex: '0 1 400px', borderRadius: 28, padding: 30, gap: 16 }} noValidate>
        <div>
          <h2 style={{ fontSize: 22, fontWeight: 600 }}>Masuk</h2>
          <p className="muted" style={{ marginTop: 6, fontSize: 14.5 }}>Akun dibuat oleh admin panitia.</p>
        </div>
        <label className="fl">
          <span className="lbl">Email atau username</span>
          <input className="field" value={login} onChange={e => setLogin(e.target.value)} autoComplete="username" required autoFocus aria-invalid={!!error} />
        </label>
        <label className="fl">
          <span className="lbl">Kata sandi</span>
          <input className="field" type="password" value={password} onChange={e => setPassword(e.target.value)} autoComplete="current-password" required aria-invalid={!!error} />
        </label>
        {error && <p className="err" role="alert">{error}</p>}
        <button className="cta" type="submit" disabled={busy || !login || !password} style={{ width: '100%', minHeight: 48, fontSize: 15 }}>
          {busy ? 'Memeriksa…' : 'Masuk'}
        </button>
        <p className="s" style={{ textAlign: 'center' }}>Lupa kata sandi? Hubungi admin panitia.</p>
      </form>
    </div>
  )
}
