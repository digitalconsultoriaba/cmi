import { useState } from 'react'
import { useApiAction, ApiErrorAlert } from '../../../components'
import { apiPut } from '../../../../lib/api'

/**
 * Casca de uma seção simples do Site: mantém o rascunho do payload, o
 * interruptor "ativa" e o botão salvar (PUT /site/sections/{id}).
 * `children` recebe (draft, patch, languages).
 */
export default function SecaoSimples({ eventId, section, languages, reload, children, hideActive }) {
  const { run, busy, error, setError } = useApiAction()
  const [draft, setDraft] = useState(section.payload ?? {})
  const [active, setActive] = useState(section.isActive)
  const patch = (k, v) => setDraft((d) => ({ ...d, [k]: v }))

  const salvar = () => run(
    () => apiPut(`/admin/events/${eventId}/site/sections/${section.id}`, { payload: draft, isActive: active }),
    { onSuccess: reload },
  )

  return (
    <div>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      {children(draft, patch, languages)}
      <div className="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
        {!hideActive ? (
          <label className="form-check form-switch mb-0">
            <input className="form-check-input" type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} />
            <span className="form-check-label">Mostrar esta seção na landing</span>
          </label>
        ) : <span />}
        <button className="btn btn-primary" disabled={busy} onClick={salvar}>Salvar seção</button>
      </div>
    </div>
  )
}
