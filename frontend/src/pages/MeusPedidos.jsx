import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useLocation } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney } from '../lib/money'
import { parseApiError } from '../lib/forms'

const STATUS_LABEL = {
  pending: 'Aguardando pagamento', paid: 'Pago', partially_paid: 'Parcialmente pago',
  cancelled: 'Cancelado', expired: 'Expirado', refunded: 'Estornado',
}
const STATUS_BADGE = {
  pending: 'bg-warning text-dark', paid: 'bg-success text-white', partially_paid: 'bg-info text-white',
  cancelled: 'bg-danger text-white', expired: 'bg-secondary text-white', refunded: 'bg-purple text-white',
}
const PAYMENT_LABEL = {
  pix: 'Pix', boleto: 'Boleto', card: 'Cartão', manual: 'Pagamento manual', cash: 'Dinheiro',
}
const TICKET_STATUS_LABEL = {
  reserved: 'Reservado', awaiting_payment: 'Aguardando pagamento', paid: 'Pago',
  confirmed: 'Confirmado', courtesy: 'Cortesia', cancelled: 'Cancelado',
  refunded: 'Estornado', transferred: 'Transferido', used: 'Utilizado',
}

function dt(iso) {
  if (!iso) return null
  const d = new Date(iso)
  return { data: d.toLocaleDateString('pt-BR'), hora: d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }
}

export default function MeusPedidos() {
  const location = useLocation()
  const queryClient = useQueryClient()
  const [error, setError] = useState(null)
  const [openCode, setOpenCode] = useState(null)
  const { data: orders = [], isLoading } = useQuery({
    queryKey: ['my', 'orders'],
    queryFn: () => apiGet('/orders'),
  })

  const cancelTicket = async (ticket, confirmNoRefund = false) => {
    setError(null)
    if (!confirmNoRefund && !window.confirm(`Solicitar cancelamento do ingresso de ${ticket.participantName}?`)) return
    try {
      await apiPost(`/tickets/${ticket.code}/cancel`, { confirm_no_refund: confirmNoRefund })
      queryClient.invalidateQueries({ queryKey: ['my'] })
    } catch (err) {
      const parsed = parseApiError(err)
      if (parsed.type === 'refund_confirmation_required') {
        if (window.confirm('Pela política do evento, não há devolução. Cancelar mesmo assim?')) {
          return cancelTicket(ticket, true)
        }
        return
      }
      setError(parsed)
    }
  }

  if (isLoading) return <p>Carregando…</p>

  return (
    <>
      {location.state?.created && (
        <div className="alert alert-success">
          Pedido criado! Sua reserva está garantida pelo prazo indicado.
        </div>
      )}
      {error && <div className="alert alert-danger">{error.message}</div>}

      {orders.length === 0 && (
        <div className="empty">
          <p className="empty-title">Você ainda não tem pedidos.</p>
          <div className="empty-action"><Link className="btn btn-primary" to="/">Ver eventos</Link></div>
        </div>
      )}

      {orders.map((order) => {
        const when = dt(order.createdAt)
        const paidWhen = dt(order.paidAt)
        const open = openCode === order.code
        const nomes = order.tickets.map((t) => t.participantName).filter(Boolean)
        return (
          <div className="card mb-3" key={order.code}>
            <div className="card-body" role="button"
              onClick={() => setOpenCode(open ? null : order.code)}>
              <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <div className="d-flex align-items-center gap-2">
                    <span className="h3 mb-0">Pedido <code>{order.code}</code></span>
                    <span className={`badge ${STATUS_BADGE[order.status] ?? 'bg-secondary'}`}>
                      {STATUS_LABEL[order.status] ?? order.status}
                    </span>
                  </div>
                  <div className="text-secondary">{order.event.name}</div>
                  <div className="text-secondary small mt-1">
                    {nomes.length} {nomes.length === 1 ? 'ingresso' : 'ingressos'}: {nomes.join(', ')}
                  </div>
                </div>
                <div className="text-end">
                  <div className="h3 mb-0">{formatMoney(order.totalAmount)}</div>
                  <div className="text-secondary small">
                    {when && <>{when.data} às {when.hora}</>}
                  </div>
                  <div className="text-secondary small">
                    {order.paymentMethod
                      ? <>Pagamento: {PAYMENT_LABEL[order.paymentMethod] ?? order.paymentMethod}
                          {paidWhen && ` · ${paidWhen.data}`}</>
                      : 'Sem pagamento registrado'}
                  </div>
                </div>
              </div>

              {order.status === 'pending' && (
                <div className="mt-2" onClick={(e) => e.stopPropagation()}>
                  {order.reservedUntil && (
                    <span className="text-warning me-2 small">
                      Reserva até {new Date(order.reservedUntil).toLocaleString('pt-BR')}.
                    </span>
                  )}
                  <Link className="btn btn-primary btn-sm" to={`/pedido/${order.code}/pagar`}>Pagar agora</Link>
                </div>
              )}
            </div>

            {open && (
              <div className="list-group list-group-flush border-top">
                {order.tickets.map((ticket) => (
                  <div className="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2" key={ticket.code}>
                    <div>
                      <strong>{ticket.participantName}</strong>
                      {ticket.companion && <span className="text-secondary"> + {ticket.companion.name}</span>}
                      <span className="text-secondary"> — {ticket.ticketTypeName}</span>
                      {ticket.isCourtesy && <span className="badge bg-success text-white ms-2">Cortesia</span>}
                      <span className={`badge ms-2 ${STATUS_BADGE[ticket.status] ?? 'bg-secondary'}`}>
                        {TICKET_STATUS_LABEL[ticket.status] ?? ticket.status}
                      </span>
                      <div className="small text-secondary"><code>{ticket.code}</code></div>
                    </div>
                    <div className="text-end">
                      <div>{formatMoney(ticket.unitPrice)}</div>
                      {ticket.isCourtesy ? (
                        <span className="text-secondary small">Cortesia não é cancelável</span>
                      ) : ticket.cancellable ? (
                        <button className="btn btn-sm btn-outline-danger mt-1"
                          onClick={() => cancelTicket(ticket)}>
                          Cancelar este ingresso
                        </button>
                      ) : null}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )
      })}
    </>
  )
}
