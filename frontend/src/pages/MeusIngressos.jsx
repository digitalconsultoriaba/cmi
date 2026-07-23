import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney } from '../lib/money'
import { parseApiError } from '../lib/forms'
import QrCode from '../components/QrCode'
import Loading from '../components/Loading'

const STATUS_LABEL = {
  reserved: 'Reservado', awaiting_payment: 'Aguardando pagamento', paid: 'Pago',
  confirmed: 'Confirmado', courtesy: 'Cortesia', cancelled: 'Cancelado',
  refunded: 'Estornado', transferred: 'Transferido', used: 'Utilizado',
}
const STATUS_BADGE = {
  paid: 'bg-success text-white', confirmed: 'bg-success text-white', courtesy: 'bg-teal text-white',
  used: 'bg-secondary text-white', cancelled: 'bg-danger text-white', refunded: 'bg-purple text-white',
  transferred: 'bg-info text-white', reserved: 'bg-warning text-dark', awaiting_payment: 'bg-warning text-dark',
}

function CancelModal({ ticket, onClose, onDone }) {
  const [error, setError] = useState(null)
  const [pending, setPending] = useState(false)

  const hasRefund = ticket.refundPreview && Number(ticket.refundPreview) > 0

  const cancel = async (confirmNoRefund = false) => {
    setError(null)
    setPending(true)
    try {
      await apiPost(`/tickets/${ticket.code}/cancel`, {
        confirm_no_refund: confirmNoRefund,
      })
      onDone()
    } catch (err) {
      const parsed = parseApiError(err)
      if (parsed.type === 'refund_confirmation_required') {
        setError({ ...parsed, needsConfirm: true })
      } else {
        setError(parsed)
      }
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="card my-2 border-danger">
      <div className="card-body">
        <h4>Cancelar ingresso de {ticket.participantName}?</h4>
        {hasRefund ? (
          <p>Devolução prevista pela política: <strong>{formatMoney(ticket.refundPreview)}</strong>{' '}
            (a tesouraria processará o reembolso).</p>
        ) : (
          <p className="text-warning">
            Pela política do evento, este cancelamento <strong>não tem devolução</strong>.
          </p>
        )}
        {error && !error.needsConfirm && <div className="alert alert-danger">{error.message}</div>}
        {error?.needsConfirm && (
          <div className="alert alert-warning">
            {error.message}
            <button className="btn btn-danger btn-sm ms-2" disabled={pending}
              onClick={() => cancel(true)}>
              Cancelar mesmo sem devolução
            </button>
          </div>
        )}
        <div className="btn-list">
          <button className="btn btn-danger" disabled={pending}
            onClick={() => cancel(!hasRefund)}>
            {pending ? 'Cancelando…' : 'Confirmar cancelamento'}
          </button>
          <button className="btn" onClick={onClose}>Voltar</button>
        </div>
      </div>
    </div>
  )
}

function TransferModal({ ticket, onClose, onDone }) {
  const [form, setForm] = useState({ name: '', email: '' })
  const [error, setError] = useState(null)
  const [pending, setPending] = useState(false)

  const transfer = async () => {
    setError(null)
    setPending(true)
    try {
      await apiPost(`/tickets/${ticket.code}/transfer`, {
        participant_name: form.name,
        participant_email: form.email,
      })
      onDone()
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="card my-2 border-primary">
      <div className="card-body">
        <h4>Transferir ingresso de {ticket.participantName}</h4>
        <p className="text-secondary">
          O ingresso atual deixa de valer e um novo (com QR próprio) é emitido
          para a pessoa indicada — ela receberá um e-mail.
        </p>
        {error && <div className="alert alert-danger">{error.message}</div>}
        <div className="row g-2">
          <div className="col-md-5">
            <input className="form-control" placeholder="Nome do novo participante"
              value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </div>
          <div className="col-md-5">
            <input type="email" className="form-control" placeholder="E-mail"
              value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </div>
        </div>
        <div className="btn-list mt-2">
          <button className="btn btn-primary" disabled={pending || !form.name || !form.email}
            onClick={transfer}>
            {pending ? 'Transferindo…' : 'Confirmar transferência'}
          </button>
          <button className="btn" onClick={onClose}>Voltar</button>
        </div>
      </div>
    </div>
  )
}

export default function MeusIngressos() {
  const queryClient = useQueryClient()
  const [action, setAction] = useState(null) // { type: 'cancel'|'transfer', ticket }

  const { data: tickets = [], isLoading } = useQuery({
    queryKey: ['my', 'tickets'],
    queryFn: () => apiGet('/tickets'),
  })

  if (isLoading) return <Loading fullscreen={false} />

  const refresh = () => {
    setAction(null)
    queryClient.invalidateQueries({ queryKey: ['my'] })
  }

  return (
    <>
      {tickets.length === 0 && (
        <div className="empty"><p className="empty-title">Você ainda não tem ingressos.</p></div>
      )}

      {action?.type === 'cancel' && (
        <CancelModal ticket={action.ticket} onClose={() => setAction(null)} onDone={refresh} />
      )}
      {action?.type === 'transfer' && (
        <TransferModal ticket={action.ticket} onClose={() => setAction(null)} onDone={refresh} />
      )}

      <div className="row row-cards">
        {tickets.map((ticket) => {
          const isUsed = ticket.status === 'used'
          const canDownload = ticket.receiptAvailable && !isUsed
          const startsAt = ticket.event?.startsAt ? new Date(ticket.event.startsAt) : null
          return (
            <div className="col-md-6 col-xl-4" key={ticket.code}>
              <div className={`card h-100 ${isUsed ? 'opacity-75' : ''}`}>
                <div className="card-status-top bg-primary" />
                <div className="card-body text-center">
                  <div className="text-secondary text-uppercase small fw-bold">{ticket.event.name}</div>
                  <div className="h3 mt-1 mb-0">{ticket.participantName}</div>
                  {ticket.companion && <div className="text-secondary">+ {ticket.companion.name}</div>}
                  <div className="mb-2">
                    <span className="text-secondary">{ticket.ticketTypeName}</span>
                    <span className={`badge ms-2 ${STATUS_BADGE[ticket.status] ?? 'bg-secondary'}`}>
                      {STATUS_LABEL[ticket.status]}
                    </span>
                  </div>
                  {startsAt && (
                    <div className="text-secondary small mb-2">
                      {startsAt.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
                    </div>
                  )}

                  <div className="d-flex justify-content-center my-3">
                    <QrCode value={ticket.code} size={180} />
                  </div>
                  <div className="text-secondary"><code>{ticket.code}</code></div>
                  {ticket.shirt && (
                    <div className="text-secondary small mt-1">Camisa {ticket.shirt.model} {ticket.shirt.size}</div>
                  )}
                  {isUsed && (
                    <div className="text-secondary small mt-1">Ingresso já utilizado na portaria.</div>
                  )}
                  {ticket.transferredToCode && (
                    <div className="text-secondary small mt-1">Transferido → {ticket.transferredToCode}</div>
                  )}
                </div>
                <div className="card-footer">
                  <div className="btn-list justify-content-center">
                    {canDownload && (
                      <a className="btn btn-sm btn-primary" target="_blank" rel="noopener"
                        href={`/api/tickets/${ticket.code}/receipt`}>
                        Baixar ingresso
                      </a>
                    )}
                    {ticket.transferable && (
                      <button className="btn btn-sm" onClick={() => setAction({ type: 'transfer', ticket })}>
                        Transferir
                      </button>
                    )}
                    {ticket.cancellable && (
                      <button className="btn btn-sm btn-outline-danger"
                        onClick={() => setAction({ type: 'cancel', ticket })}>
                        Cancelar
                      </button>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </>
  )
}
