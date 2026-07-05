import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiDelete } from '../../lib/api'
import { Modal, ApiErrorAlert, useApiAction } from '../components'

/** Cadastro de tipos de evento — abre a partir do botão "Tipos" na lista. */
export default function TiposEventoModal({ onClose }) {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
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
    <Modal title="Tipos de evento" size="md" onClose={onClose}
      footer={<button className="btn" onClick={onClose}>Fechar</button>}>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      <div className="input-group mb-3">
        <input className="form-control" placeholder="Ex.: Jantar, Palestra…" value={name}
          onChange={(e) => setName(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && name.trim() && add()} />
        <button className="btn btn-primary" onClick={add} disabled={busy || !name.trim()}>Adicionar</button>
      </div>
      <table className="table table-vcenter">
        <thead><tr><th>Nome</th><th /></tr></thead>
        <tbody>
          {types.map((t) => (
            <tr key={t.id}>
              <td>{t.name}</td>
              <td className="text-end">
                <button className="btn btn-sm btn-outline-danger" disabled={busy}
                  onClick={() => remove(t.id)}>Excluir</button>
              </td>
            </tr>
          ))}
          {types.length === 0 && <tr><td colSpan={2} className="text-secondary">Nenhum tipo cadastrado.</td></tr>}
        </tbody>
      </table>
    </Modal>
  )
}
