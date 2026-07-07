import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '../lib/api'
import { parseApiError } from '../lib/forms'

const TYPE_LABEL = {
  refund: 'Reembolso', cancellation: 'Cancelamento', question: 'Dúvida',
  shirt_change: 'Troca de camisa', other: 'Outro',
}
const STATUS_LABEL = { open: 'Aberto', finished: 'Finalizado', reopened: 'Reaberto' }
const STATUS_BADGE = { open: 'bg-success text-white', finished: 'bg-secondary text-white', reopened: 'bg-warning text-dark' }

function onlyDigits(s) { return (s ?? '').replace(/\D/g, '') }

export default function Suporte() {
  const queryClient = useQueryClient()
  const [selectedId, setSelectedId] = useState(null)
  const [showNew, setShowNew] = useState(false)
  const [newCase, setNewCase] = useState({ type: 'question', subject: '', message: '', order_code: '' })
  const [reply, setReply] = useState('')
  const [error, setError] = useState(null)

  const { data: cases = [] } = useQuery({ queryKey: ['my', 'support'], queryFn: () => apiGet('/support-cases') })
  const { data: orders = [] } = useQuery({ queryKey: ['my', 'orders'], queryFn: () => apiGet('/orders') })

  const { data: current } = useQuery({
    queryKey: ['my', 'support', selectedId],
    queryFn: () => apiGet(`/support-cases/${selectedId}`),
    enabled: !!selectedId,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['my', 'support'] })

  const selectedOrder = useMemo(
    () => orders.find((o) => o.code === newCase.order_code),
    [orders, newCase.order_code],
  )

  // Contato do atendimento (WhatsApp/e-mail) — vem do evento das inscrições.
  const support = useMemo(() => {
    const ev = orders.map((o) => o.event).find((e) => e?.supportWhatsapp || e?.supportEmail)
    return ev ? { whatsapp: ev.supportWhatsapp, email: ev.supportEmail } : null
  }, [orders])

  const create = async () => {
    setError(null)
    try {
      const payload = { ...newCase }
      if (!payload.order_code) delete payload.order_code
      const created = await apiPost('/support-cases', payload)
      setShowNew(false)
      setNewCase({ type: 'question', subject: '', message: '', order_code: '' })
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
    <>
      {error && <div className="alert alert-danger">{error.message}</div>}

      {support && (support.whatsapp || support.email) && (
        <div className="card mb-3" style={{ borderLeft: '4px solid #16A34A' }}>
          <div className="card-body d-flex align-items-center flex-wrap gap-3">
            <div>
              <div className="fw-bold">Fale com o atendimento</div>
              <div className="text-secondary small">Dúvidas sobre sua inscrição? Fale direto com a organização.</div>
            </div>
            <div className="ms-auto d-flex gap-2 flex-wrap">
              {support.whatsapp && (
                <a className="btn btn-success" target="_blank" rel="noreferrer"
                  href={`https://wa.me/55${onlyDigits(support.whatsapp)}`}>
                  WhatsApp {support.whatsapp}
                </a>
              )}
              {support.email && (
                <a className="btn btn-outline-primary" href={`mailto:${support.email}`}>{support.email}</a>
              )}
            </div>
          </div>
        </div>
      )}

      {!selectedId && (
        <>
          <div className="d-flex justify-content-between align-items-center mb-3">
            <h2 className="mb-0">Suporte</h2>
            <button className="btn btn-primary" onClick={() => setShowNew(!showNew)}>
              {showNew ? 'Fechar' : 'Nova solicitação'}
            </button>
          </div>

          {showNew && (
            <div className="card mb-3"><div className="card-body">
              <div className="row g-2">
                <div className="col-md-4">
                  <label className="form-label">Tipo</label>
                  <select className="form-select" value={newCase.type}
                    onChange={(e) => setNewCase({ ...newCase, type: e.target.value })}>
                    {Object.entries(TYPE_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select>
                </div>
                <div className="col-md-8">
                  <label className="form-label">Pedido (opcional)</label>
                  <select className="form-select" value={newCase.order_code}
                    onChange={(e) => setNewCase({ ...newCase, order_code: e.target.value })}>
                    <option value="">Não vincular a um pedido</option>
                    {orders.map((o) => (
                      <option key={o.code} value={o.code}>
                        {o.code} — {o.event.name} ({o.tickets.length} ingresso{o.tickets.length > 1 ? 's' : ''})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="col-12">
                  <label className="form-label">Assunto</label>
                  <input className="form-control" placeholder="Assunto"
                    value={newCase.subject}
                    onChange={(e) => setNewCase({ ...newCase, subject: e.target.value })} />
                </div>
                <div className="col-12">
                  <label className="form-label">Mensagem</label>
                  <textarea className="form-control" rows={3} placeholder="Descreva sua solicitação"
                    value={newCase.message}
                    onChange={(e) => setNewCase({ ...newCase, message: e.target.value })} />
                </div>

                {selectedOrder && (selectedOrder.event.supportWhatsapp || selectedOrder.event.supportEmail) && (
                  <div className="col-12">
                    <div className="alert alert-info mb-0">
                      Atendimento direto do evento <strong>{selectedOrder.event.name}</strong>:
                      {selectedOrder.event.supportWhatsapp && (
                        <a className="btn btn-sm btn-success ms-2"
                          href={`https://wa.me/${onlyDigits(selectedOrder.event.supportWhatsapp)}`}
                          target="_blank" rel="noopener">WhatsApp</a>
                      )}
                      {selectedOrder.event.supportEmail && (
                        <a className="btn btn-sm ms-2" href={`mailto:${selectedOrder.event.supportEmail}`}>E-mail</a>
                      )}
                    </div>
                  </div>
                )}

                <div className="col-12">
                  <button className="btn btn-primary"
                    disabled={!newCase.subject || !newCase.message} onClick={create}>
                    Abrir solicitação
                  </button>
                </div>
              </div>
            </div></div>
          )}

          {cases.length === 0 && <p className="text-secondary">Você ainda não abriu nenhuma solicitação.</p>}
          {cases.map((item) => (
            <div key={item.id} className="card mb-2" role="button" onClick={() => setSelectedId(item.id)}>
              <div className="card-body d-flex justify-content-between align-items-center">
                <span>
                  <span className="badge bg-primary text-white me-2">{TYPE_LABEL[item.type] ?? item.type}</span>
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
          <button className="btn btn-sm mb-3" onClick={() => setSelectedId(null)}>← Minhas solicitações</button>
          <div className="card"><div className="card-body">
            <div className="d-flex justify-content-between align-items-center mb-2">
              <h3 className="mb-0">
                {current.subject}
                {current.orderCode && <code className="ms-2 small">{current.orderCode}</code>}
              </h3>
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
                current.status === 'finished' ? 'Responder reabre a solicitação…' : 'Sua mensagem…'
              } value={reply} onChange={(e) => setReply(e.target.value)} />
              <button className="btn btn-primary" disabled={!reply.trim()} onClick={send}>Enviar</button>
            </div>
          </div></div>
        </>
      )}
    </>
  )
}
