import { useState } from 'react'
import { apiUpload } from '../../../../lib/api'

/** URL de preview a partir do path guardado (proxy /storage → backend). */
export function mediaUrl(path) {
  if (!path) return null
  if (path.startsWith('http') || path.startsWith('/storage')) return path
  return `/storage/${path}`
}

/**
 * Upload de imagem do Site. Guarda o `path` no payload; usa a URL para preview.
 */
export default function MediaUpload({ label, value, onChange, eventId, small }) {
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState(null)

  const send = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setBusy(true); setErr(null)
    try {
      const data = new FormData()
      data.append('file', file)
      const res = await apiUpload(`/admin/events/${eventId}/site/media`, data)
      onChange(res.path)
    } catch {
      setErr('Falha no upload. Verifique o formato (JPG/PNG/WEBP/SVG, até 4 MB).')
    } finally {
      setBusy(false)
    }
  }

  const url = mediaUrl(value)

  return (
    <div className="mb-2">
      {label && <label className="form-label mb-1">{label}</label>}
      <div className="d-flex align-items-center gap-2 flex-wrap">
        {url && <img src={url} alt="" style={{ height: small ? 40 : 64, borderRadius: 6, objectFit: 'cover', background: '#f1f3f7' }} />}
        <label className="btn btn-sm mb-0">
          {busy ? 'Enviando…' : (value ? 'Trocar' : 'Enviar imagem')}
          <input type="file" hidden accept="image/jpeg,image/png,image/webp,image/svg+xml" onChange={send} disabled={busy} />
        </label>
        {value && <button className="btn btn-sm btn-outline-danger" type="button" onClick={() => onChange(null)}>Remover</button>}
      </div>
      {err && <div className="text-danger small mt-1">{err}</div>}
    </div>
  )
}
