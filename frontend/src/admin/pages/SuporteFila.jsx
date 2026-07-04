import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Card, ApiErrorAlert, useApiAction } from '../components'
import { apiGet, apiPost } from '../../lib/api'

const TYPE_LABEL = { refund: 'Reembolso', question: 'Dúvida', shirt_change: 'Troca de camisa', other: 'Outro' }
const STATUS_LABEL = { open: 'Aberto', finished: 'Finalizado', reopened: 'Reaberto' }
const STATUS_BADGE = { open: 'bg-green-lt', finished: 'bg-secondary-lt', reopened: 'bg-yellow-lt' }

export default function SuporteFila() {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [filter, setFilter] = useState('')
  const [selectedId, setSelectedId] = useState(null)
  const [reply, setReply] = useState('')
  const [internal, setInternal] = useState(false)

  const { data: cases = [] } = useQuery({
    queryKey: ['admin', 'support', filter],
    queryFn: () => apiGet(`/admin/support-cases${filter ? `?status=${filter}` : ''}`),
  })

  const { data: current } = useQuery({
    queryKey: ['admin', 'support', 'case', selectedId],
    queryFn: () => apiGet(`/admin/support-cases/${selectedId}`),
    enabled: !!selectedId,
  })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin', 'support'] })
  }

  const send = () => run(
    () => apiPost(`/admin/support-cases/${selectedId}/notes`, {
      message: reply, visible_to_attendee: !internal,
    }),
    { onSuccess: () => { setReply(''); refresh() } }
  )

  const transition = (action) => run(
    () => apiPost(`/admin/support-cases/${selectedId}/${action}`),
    { onSuccess: refresh }
  )

  return (
    <>
      <h2>Suporte — fila</h2>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      {!selectedId && (
        <Card title={`Casos (${cases.length})`}
          actions={
            <select className="form-select form-select-sm w-auto" value={filter}
              onChange={(e) => setFilter(e.target.value)}>
              <option value="">Todos</option>
              {Object.entries(STATUS_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </select>
          }>
          <table className="table table-vcenter">
            <thead><tr><th>Tipo</th><th>Assunto</th><th>Solicitante</th><th>Vínculo</th><th>Situação</th></tr></thead>
            <tbody>
              {cases.map((item) => (
                <tr key={item.id} role="button" onClick={() => setSelectedId(item.id)}>
                  <td><span className="badge bg-blue-lt">{TYPE_LABEL[item.type]}</span></td>
                  <td>{item.subject}</td>
                  <td>{item.requester}</td>
                  <td>
                    {item.orderCode && <code className="small">{item.orderCode}</code>}
                    {item.ticketCode && <code className="small ms-1">{item.ticketCode}</code>}
                  </td>
                  <td><span className={`badge ${STATUS_BADGE[item.status]}`}>{STATUS_LABEL[item.status]}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}

      {selectedId && current && (
        <>
          <p><button className="btn btn-sm" onClick={() => setSelectedId(null)}>← Fila</button></p>
          <Card title={current.subject}
            actions={
              <span className="btn-list">
                <span className={`badge ${STATUS_BADGE[current.status]}`}>{STATUS_LABEL[current.status]}</span>
                {current.status !== 'finished'
                  ? <button className="btn btn-sm btn-success" disabled={busy} onClick={() => transition('finish')}>Finalizar</button>
                  : <button className="btn btn-sm" disabled={busy} onClick={() => transition('reopen')}>Reabrir</button>}
              </span>
            }>
            <p className="text-secondary">
              {current.requester} · {TYPE_LABEL[current.type]}
              {current.refundAmount && <> · valor: R$ {current.refundAmount}</>}
            </p>
            {current.notes.map((note) => (
              <div key={note.id}
                className={`mb-2 p-2 rounded ${note.fromAttendee ? 'bg-azure-lt' : note.visibleToAttendee ? 'bg-light' : 'bg-yellow-lt'}`}>
                <div className="small text-secondary">
                  {note.author}
                  {!note.visibleToAttendee && <strong> · INTERNA</strong>}
                  {' · '}{new Date(note.createdAt).toLocaleString('pt-BR')}
                </div>
                {note.body}
              </div>
            ))}
            <div className="mt-3">
              <textarea className="form-control mb-2" rows={2} placeholder="Resposta…"
                value={reply} onChange={(e) => setReply(e.target.value)} />
              <label className="form-check form-check-inline">
                <input type="checkbox" className="form-check-input" checked={internal}
                  onChange={(e) => setInternal(e.target.checked)} />
                <span className="form-check-label">Nota interna (invisível ao inscrito)</span>
              </label>
              <button className="btn btn-primary ms-2" disabled={busy || !reply.trim()} onClick={send}>
                Enviar
              </button>
            </div>
          </Card>
        </>
      )}
    </>
  )
}
