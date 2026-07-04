import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney } from '../lib/money'
import { parseApiError } from '../lib/forms'

const STATUS_LABEL = {
  reserved: 'Reservado', awaiting_payment: 'Aguardando pagamento', paid: 'Pago',
  confirmed: 'Confirmado', courtesy: 'Cortesia', cancelled: 'Cancelado',
  refunded: 'Estornado', transferred: 'Transferido', used: 'Utilizado',
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

  if (isLoading) return <p style={{ padding: '2rem' }}>Carregando…</p>

  const refresh = () => {
    setAction(null)
    queryClient.invalidateQueries({ queryKey: ['my'] })
  }

  return (
    <main className="container-xl py-4" style={{ maxWidth: 900 }}>
      <h1>Meus ingressos</h1>
      <p>
        <Link to="/minha-conta">← Minha conta</Link>
        {' · '}<Link to="/minha-conta/pedidos">Meus pedidos</Link>
        {' · '}<Link to="/minha-conta/suporte">Suporte</Link>
      </p>

      {tickets.length === 0 && <p>Você ainda não tem ingressos.</p>}

      {action?.type === 'cancel' && (
        <CancelModal ticket={action.ticket} onClose={() => setAction(null)} onDone={refresh} />
      )}
      {action?.type === 'transfer' && (
        <TransferModal ticket={action.ticket} onClose={() => setAction(null)} onDone={refresh} />
      )}

      {tickets.length > 0 && (
        <table className="table table-vcenter">
          <thead>
            <tr><th>Participante</th><th>Evento</th><th>Tipo</th><th>Situação</th><th /></tr>
          </thead>
          <tbody>
            {tickets.map((ticket) => (
              <tr key={ticket.code}>
                <td>
                  {ticket.participantName}
                  {ticket.companion && <div className="text-secondary small">+ {ticket.companion.name}</div>}
                  <code className="small">{ticket.code}</code>
                </td>
                <td>{ticket.event.name}</td>
                <td>
                  {ticket.ticketTypeName}
                  {ticket.shirt && <div className="text-secondary small">Camisa {ticket.shirt.model} {ticket.shirt.size}</div>}
                </td>
                <td>
                  <span className="badge bg-blue-lt">{STATUS_LABEL[ticket.status]}</span>
                  {ticket.transferredToCode && (
                    <div className="text-secondary small">→ {ticket.transferredToCode}</div>
                  )}
                </td>
                <td className="text-end">
                  <span className="btn-list justify-content-end">
                    {ticket.receiptAvailable && (
                      <a className="btn btn-sm btn-primary" target="_blank"
                        href={`/api/tickets/${ticket.code}/receipt`}>
                        Comprovante
                      </a>
                    )}
                    {ticket.transferable && (
                      <button className="btn btn-sm"
                        onClick={() => setAction({ type: 'transfer', ticket })}>
                        Transferir
                      </button>
                    )}
                    {ticket.cancellable && (
                      <button className="btn btn-sm btn-outline-danger"
                        onClick={() => setAction({ type: 'cancel', ticket })}>
                        Cancelar
                      </button>
                    )}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </main>
  )
}
