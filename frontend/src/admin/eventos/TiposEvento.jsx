import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiDelete } from '../../lib/api'
import { ApiErrorAlert, useApiAction } from '../components'

/** Tipos de evento (aba do módulo) — Jantar, Palestra, etc. */
export default function TiposEvento() {
  const queryClient = useQueryClient()
  const { run, error, setError } = useApiAction()
  const [name, setName] = useState('')

  const { data: types = [] } = useQuery({
    queryKey: ['admin', 'event-types'],
    queryFn: () => apiGet('/admin/event-types'),
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'event-types'] })

  const add = () => run(() => apiPost('/admin/event-types', { name }),
    { onSuccess: () => { setName(''); refresh() } })

  const remove = (id) => run(() => apiDelete(`/admin/event-types/${id}`), { onSuccess: refresh })

  return (
    <div className="card">
      <div className="card-header"><h3 className="card-title">Tipos de evento</h3></div>
      <div className="card-body">
        <ApiErrorAlert error={error} onClose={() => setError(null)} />
        <div className="row g-2 mb-3">
          <div className="col-md-4">
            <input className="form-control" placeholder="Ex.: Jantar" value={name}
              onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="col-md-2">
            <button className="btn btn-primary" onClick={add} disabled={!name.trim()}>Adicionar</button>
          </div>
        </div>
        <table className="table table-vcenter">
          <thead><tr><th>Nome</th><th /></tr></thead>
          <tbody>
            {types.map((t) => (
              <tr key={t.id}>
                <td>{t.name}</td>
                <td className="text-end">
                  <button className="btn btn-sm btn-outline-danger" onClick={() => remove(t.id)}>Excluir</button>
                </td>
              </tr>
            ))}
            {types.length === 0 && <tr><td colSpan={2} className="text-secondary">Nenhum tipo cadastrado.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
