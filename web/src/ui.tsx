import { useEffect, useRef, useState, type ReactNode } from 'react'
import { SANKSI_LABEL, STATUS_LABEL, HADIR_LABEL, type Sanksi, type StatusHadir } from './lib'

const PATHS: Record<string, ReactNode> = {
  grid: <><rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><rect x="14" y="14" width="7" height="7" rx="2" /></>,
  cal: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></>,
  clip: <><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 3h6v3H9zM9 13l2 2 4-4" /></>,
  chart: <path d="M5 20V11M11 20V5M17 20v-6M3 20h18" />,
  flag: <path d="M5 21V4h12l-2.5 4.5L17 13H5" />,
  users: <><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c1.8.7 3 2.5 3.5 5.2" /></>,
  school: <path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" />,
  shield: <path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z" />,
  clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  out: <path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3" />,
  search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></>,
  more: <><circle cx="5" cy="12" r="1.2" /><circle cx="12" cy="12" r="1.2" /><circle cx="19" cy="12" r="1.2" /></>,
  back: <path d="M15 18l-6-6 6-6" />,
  plus: <path d="M12 5v14M5 12h14" />,
  down: <path d="M12 4v11m0 0-4-4m4 4 4-4M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4" />,
  up: <path d="M12 15V4m0 0L8 8m4-4 4 4M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4" />,
  chev: <path d="m9 6 6 6-6 6" />,
}

export function Icon({ name, size = 18 }: { name: keyof typeof PATHS | string; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {PATHS[name]}
    </svg>
  )
}

export const StatusChip = ({ s }: { s: string }) => <span className={'chip c-' + s}>{STATUS_LABEL[s] ?? s}</span>
export const HadirChip = ({ s }: { s: StatusHadir }) => <span className={'chip st-' + s}>{HADIR_LABEL[s]}</span>
export function SanksiChip({ s, full, children }: { s: Sanksi; full?: boolean; children?: ReactNode }) {
  return <span className={'chip s-' + s.level}>{children ?? (full ? s.label : SANKSI_LABEL[s.level])}</span>
}

export function Search({ value, onChange, placeholder, label, autoFocus }: { value: string; onChange: (v: string) => void; placeholder: string; label: string; autoFocus?: boolean }) {
  return (
    <label className="search">
      <Icon name="search" size={16} />
      <span className="sr">{label}</span>
      <input type="search" value={value} placeholder={placeholder} autoFocus={autoFocus} onChange={e => onChange(e.target.value)} />
    </label>
  )
}

/** Nilai yang baru berubah setelah pengguna berhenti mengetik. */
export function useDebounced<T>(v: T, ms = 300) {
  const [d, setD] = useState(v)
  useEffect(() => {
    const t = setTimeout(() => setD(v), ms)
    return () => clearTimeout(t)
  }, [v, ms])
  return d
}

export function Modal({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
  const ref = useRef<HTMLDialogElement>(null)
  useEffect(() => {
    const d = ref.current
    if (!d) return
    if (open && !d.open) d.showModal()
    if (!open && d.open) d.close()
  }, [open])
  return (
    <dialog ref={ref} className="modal" onClose={onClose} onClick={e => e.target === ref.current && onClose()} aria-labelledby="modal-title">
      {open && (
        <div className="modal-body">
          <h2 id="modal-title" style={{ fontSize: 20, fontWeight: 600 }}>{title}</h2>
          {children}
        </div>
      )}
    </dialog>
  )
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="fl">
      <span className="lbl">{label}</span>
      {children}
      {error && <span className="err">{error}</span>}
    </label>
  )
}

export function Pager({ page, total, per, onPage }: { page: number; total: number; per: number; onPage: (p: number) => void }) {
  const last = Math.max(1, Math.ceil(total / per))
  const from = total === 0 ? 0 : (page - 1) * per + 1
  return (
    <div className="hd">
      <span className="s num">{from}–{Math.min(page * per, total)} dari {total.toLocaleString('id-ID')}</span>
      {last > 1 && (
        <div className="row">
          <button className="pill sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>Sebelumnya</button>
          <button className="pill sm" disabled={page >= last} onClick={() => onPage(page + 1)}>Berikutnya</button>
        </div>
      )}
    </div>
  )
}

export function Loading({ error, retry }: { error?: string | null; retry?: () => void }) {
  if (error)
    return (
      <div className="glass card" role="alert">
        <p className="t">Data gagal dimuat.</p>
        <p className="s">{error}</p>
        {retry && <div><button className="pill sm" onClick={retry}>Coba lagi</button></div>}
      </div>
    )
  return <div className="skeleton" aria-label="Memuat" />
}

export function Stat({ label, value, note, color }: { label: string; value: ReactNode; note?: ReactNode; color?: string }) {
  return (
    <div className="glass card" style={{ padding: 18, gap: 4 }}>
      <div className="lbl">{label}</div>
      <div className="big" style={{ color }}>{value}</div>
      {note && <div className="s">{note}</div>}
    </div>
  )
}

export function Bar({ value }: { value: number }) {
  return <div className="bar"><div style={{ transform: `scaleX(${Math.min(100, Math.max(0, value)) / 100})` }} /></div>
}

/** Konfirmasi sebelum aksi yang tidak bisa dibatalkan. */
export function Confirm({ open, title, body, ok, danger, onOk, onClose, typed }: {
  open: boolean; title: string; body: ReactNode; ok: string; danger?: boolean; onOk: () => void; onClose: () => void; typed?: string
}) {
  const [v, setV] = useState('')
  useEffect(() => { if (open) setV('') }, [open])
  return (
    <Modal open={open} onClose={onClose} title={title}>
      <div className="muted" style={{ fontSize: 15, lineHeight: 1.5 }}>{body}</div>
      {typed && (
        <Field label={`Ketik ${typed} untuk melanjutkan`}>
          <input className="field" value={v} onChange={e => setV(e.target.value)} autoComplete="off" />
        </Field>
      )}
      <div className="row" style={{ justifyContent: 'flex-end' }}>
        <button className="pill" onClick={onClose}>Batal</button>
        <button className={'cta' + (danger ? ' danger' : '')} disabled={!!typed && v !== typed} onClick={onOk}>{ok}</button>
      </div>
    </Modal>
  )
}
