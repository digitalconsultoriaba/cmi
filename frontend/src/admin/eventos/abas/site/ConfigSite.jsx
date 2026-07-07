import { useState } from 'react'
import { useApiAction, ApiErrorAlert } from '../../../components'
import { apiPut, apiPost } from '../../../../lib/api'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

const THEME_TOKENS = [
  ['bg', 'Fundo (topo)'], ['bgEnd', 'Fundo (base)'], ['surface', 'Superfície de card'],
  ['accent', 'Dourado (acento)'], ['accentHover', 'Dourado (hover)'],
  ['textLight', 'Texto claro'], ['textMuted', 'Texto secundário'], ['blue', 'Azul de destaque'],
]
const LANGS = [['pt', 'Português'], ['en', 'English'], ['es', 'Español']]

// datetime-local <-> ISO
function toLocal(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const off = d.getTimezoneOffset() * 60000
  return new Date(d - off).toISOString().slice(0, 16)
}

export default function ConfigSite({ site, eventId, reload }) {
  const { run, busy, error, setError } = useApiAction()
  const [slug, setSlug] = useState(site.slug || '')
  const [countdown, setCountdown] = useState(toLocal(site.countdownAt))
  const [theme, setTheme] = useState(site.theme || {})
  const [identity, setIdentity] = useState(site.identity || {})
  const [seo, setSeo] = useState(site.seo || {})
  const [langs, setLangs] = useState(site.activeLanguages || ['pt'])

  const setColor = (k, v) => setTheme((t) => ({ ...t, [k]: v }))
  const toggleLang = (l) => setLangs((cur) => cur.includes(l) ? cur.filter((x) => x !== l) : [...cur, l])

  const salvar = () => run(
    () => apiPut(`/admin/events/${eventId}/site`, {
      slug,
      countdownAt: countdown ? new Date(countdown).toISOString() : null,
      theme,
      identity: { ...identity, eventName: identity.eventName ?? site.identity?.eventName },
      seo,
      activeLanguages: langs,
    }),
    { onSuccess: reload },
  )

  const publicar = () => run(() => apiPost(`/admin/events/${eventId}/site/${site.isPublished ? 'unpublish' : 'publish'}`), { onSuccess: reload })

  return (
    <div>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="alert d-flex align-items-center justify-content-between flex-wrap gap-2"
        style={{ background: site.isPublished ? '#e6f4ea' : '#fff3cd' }}>
        <div>
          <strong>{site.isPublished ? 'Site publicado' : 'Rascunho'}</strong>
          {' — '}
          {site.isPublished
            ? <>no ar em <a href={`/site/${site.slug}`} target="_blank" rel="noreferrer">/site/{site.slug}</a> (respeita a visibilidade do evento)</>
            : 'a landing não aparece publicamente até publicar.'}
        </div>
        <button className={`btn btn-sm ${site.isPublished ? 'btn-outline-danger' : 'btn-success'}`} disabled={busy} onClick={publicar}>
          {site.isPublished ? 'Despublicar' : 'Publicar site'}
        </button>
      </div>

      <div className="row g-4">
        <div className="col-md-6">
          <h4>Identidade</h4>
          <label className="form-label">Nome do evento</label>
          <input className="form-control mb-2" value={identity.eventName ?? ''} onChange={(e) => setIdentity({ ...identity, eventName: e.target.value })} />
          <MediaUpload label="Logo principal" value={identity.logoPath} onChange={(p) => setIdentity({ ...identity, logoPath: p })} eventId={eventId} />
          <MediaUpload label="Marca-d'água (hero)" value={identity.watermarkPath} onChange={(p) => setIdentity({ ...identity, watermarkPath: p })} eventId={eventId} />

          <h4 className="mt-3">Endereço público</h4>
          <label className="form-label">Slug (URL)</label>
          <div className="input-group mb-2">
            <span className="input-group-text">/site/</span>
            <input className="form-control" value={slug} onChange={(e) => setSlug(e.target.value)} />
          </div>

          <label className="form-label">Data do evento (countdown)</label>
          <input type="datetime-local" className="form-control mb-2" value={countdown} onChange={(e) => setCountdown(e.target.value)} />

          <h4 className="mt-3">Idiomas</h4>
          {LANGS.map(([l, label]) => (
            <label className="form-check" key={l}>
              <input className="form-check-input" type="checkbox" checked={langs.includes(l)} disabled={l === 'pt'} onChange={() => toggleLang(l)} />
              <span className="form-check-label">{label}{l === 'pt' && ' (base)'}</span>
            </label>
          ))}
        </div>

        <div className="col-md-6">
          <h4>Tema / Cores</h4>
          <div className="row g-2">
            {THEME_TOKENS.map(([k, label]) => (
              <div className="col-6 d-flex align-items-center gap-2" key={k}>
                <input type="color" className="form-control form-control-color" value={theme[k] || '#000000'} onChange={(e) => setColor(k, e.target.value)} />
                <span className="small">{label}</span>
              </div>
            ))}
          </div>

          <h4 className="mt-3">SEO</h4>
          <LocalizedInput label="Título" value={seo.title} onChange={(v) => setSeo({ ...seo, title: v })} languages={langs} />
          <LocalizedInput label="Descrição" value={seo.description} onChange={(v) => setSeo({ ...seo, description: v })} languages={langs} textarea />
          <MediaUpload label="Imagem de compartilhamento (OG)" value={seo.ogImagePath} onChange={(p) => setSeo({ ...seo, ogImagePath: p })} eventId={eventId} />
        </div>
      </div>

      <div className="mt-3 pt-3 border-top text-end">
        <button className="btn btn-primary" disabled={busy} onClick={salvar}>Salvar configurações</button>
      </div>
    </div>
  )
}
