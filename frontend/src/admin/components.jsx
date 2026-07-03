import { useState } from 'react'
import { parseApiError } from '../lib/forms'

/** Card padrão do painel. */
export function Card({ title, actions, children }) {
  return (
    <div className="card mb-3">
      <div className="card-header d-flex justify-content-between align-items-center">
        <h3 className="card-title mb-0">{title}</h3>
        <div>{actions}</div>
      </div>
      <div className="card-body">{children}</div>
    </div>
  )
}

/** Alerta de erro de API (409/422/…) com dispensa. */
export function ApiErrorAlert({ error, onClose }) {
  if (!error) return null

  return (
    <div className="alert alert-danger alert-dismissible" role="alert">
      {error.message}
      {error.fields?.missing && (
        <ul className="mb-0 mt-2">
          {error.fields.missing.map((item) => <li key={item}>{item}</li>)}
        </ul>
      )}
      {onClose && <button type="button" className="btn-close" onClick={onClose} />}
    </div>
  )
}

/** Envolve uma mutação assíncrona capturando o erro de API. */
export function useApiAction() {
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const run = async (action, { onSuccess } = {}) => {
    setError(null)
    setBusy(true)
    try {
      const result = await action()
      onSuccess?.(result)
      return result
    } catch (err) {
      setError(parseApiError(err))
      return undefined
    } finally {
      setBusy(false)
    }
  }

  return { run, error, setError, busy }
}

export function StatusBadge({ ok, okLabel, badLabel }) {
  return (
    <span className={`badge ${ok ? 'bg-green-lt' : 'bg-red-lt'}`}>
      {ok ? okLabel : badLabel}
    </span>
  )
}
