import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api'
import { parseApiError } from '../lib/forms'

const TYPE_LABEL = { refund: 'Reembolso', question: 'Dúvida', shirt_change: 'Troca de camisa', other: 'Outro' }
const STATUS_LABEL = { open: 'Aberto', finished: 'Finalizado', reopened: 'Reaberto' }
const STATUS_BADGE = { open: 'bg-green-lt', finished: 'bg-secondary-lt', reopened: 'bg-yellow-lt' }

export default function Suporte() {
  const queryClient = useQueryClient()
  const [selectedId, setSelectedId] = useState(null)
  const [showNew, setShowNew] = useState(false)
  const [newCase, setNewCase] = useState({ type: 'question', subject: '', message: '' })
  const [reply, setReply] = useState('')
  const [error, setError] = useState(null)

  const { data: cases = [] } = useQuery({
    queryKey: ['my', 'support'],
    queryFn: () => apiGet('/support-cases'),
  })

  const { data: current } = useQuery({
    queryKey: ['my', 'support', selectedId],
    queryFn: () => apiGet(`/support-cases/${selectedId}`),
    enabled: !!selectedId,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['my', 'support'] })

  const create = async () => {
    setError(null)
    try {
      const created = await apiPost('/support-cases', newCase)
      setShowNew(false)
      setNewCase({ type: 'question', subject: '', message: '' })
      refresh()
      setSelectedId(created.id)
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  const send = async () => {
    setError(null)
    try {
      await apiPost(`/support-cases/${selectedId}/notes`, { message: reply })
      setReply('')
      refresh()
      queryClient.invalidateQueries({ queryKey: ['my', 'support', selectedId] })
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  return (
    <main className="container-xl py-4" style={{ maxWidth: 900 }}>
      <h1>Suporte</h1>
      <p><Link to="/minha-conta">← Minha conta</Link></p>

      {error && <div className="alert alert-danger">{error.message}</div>}

      {!selectedId && (
        <>
          <button className="btn btn-primary mb-3" onClick={() => setShowNew(!showNew)}>
            Novo caso
          </button>

          {showNew && (
            <div className="card mb-3"><div className="card-body row g-2">
              <div className="col-md-3">
                <select className="form-select" value={newCase.type}
                  onChange={(e) => setNewCase({ ...newCase, type: e.target.value })}>
                  {Object.entries(TYPE_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              <div className="col-md-9">
                <input className="form-control" placeholder="Assunto"
                  value={newCase.subject}
                  onChange={(e) => setNewCase({ ...newCase, subject: e.target.value })} />
              </div>
              <div className="col-12">
                <textarea className="form-control" rows={3} placeholder="Descreva sua solicitação"
                  value={newCase.message}
                  onChange={(e) => setNewCase({ ...newCase, message: e.target.value })} />
              </div>
              <div className="col-12">
                <button className="btn btn-primary"
                  disabled={!newCase.subject || !newCase.message} onClick={create}>
                  Abrir caso
                </button>
              </div>
            </div></div>
          )}

          {cases.length === 0 && <p>Você ainda não abriu nenhum caso.</p>}
          {cases.map((item) => (
            <div key={item.id} className="card mb-2" role="button" onClick={() => setSelectedId(item.id)}>
              <div className="card-body d-flex justify-content-between">
                <span>
                  <span className="badge bg-blue-lt me-2">{TYPE_LABEL[item.type]}</span>
                  {item.subject}
                  {item.orderCode && <code className="ms-2 small">{item.orderCode}</code>}
                </span>
                <span className={`badge ${STATUS_BADGE[item.status]}`}>{STATUS_LABEL[item.status]}</span>
              </div>
            </div>
          ))}
        </>
      )}

      {selectedId && current && (
        <>
          <p>
            <button className="btn btn-sm" onClick={() => setSelectedId(null)}>← Meus casos</button>
          </p>
          <div className="card"><div className="card-body">
            <div className="d-flex justify-content-between">
              <h3>{current.subject}</h3>
              <span className={`badge ${STATUS_BADGE[current.status]}`}>{STATUS_LABEL[current.status]}</span>
            </div>
            {current.notes.map((note) => (
              <div key={note.id} className={`mb-2 p-2 rounded ${note.fromAttendee ? 'bg-azure-lt' : 'bg-light'}`}>
                <div className="small text-secondary">
                  {note.fromAttendee ? 'Você' : note.author ?? 'Organização'} ·{' '}
                  {new Date(note.createdAt).toLocaleString('pt-BR')}
                </div>
                {note.body}
              </div>
            ))}
            <div className="d-flex gap-2 mt-3">
              <input className="form-control" placeholder={
                current.status === 'finished' ? 'Responder reabre o caso…' : 'Sua mensagem…'
              } value={reply} onChange={(e) => setReply(e.target.value)} />
              <button className="btn btn-primary" disabled={!reply.trim()} onClick={send}>Enviar</button>
            </div>
          </div></div>
        </>
      )}
    </main>
  )
}
