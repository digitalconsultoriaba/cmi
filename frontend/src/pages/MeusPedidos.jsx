import { useQuery } from '@tanstack/react-query'
import { Link, useLocation } from 'react-router-dom'
import { apiGet } from '../lib/api'
import { formatMoney } from '../lib/money'

const STATUS_LABEL = {
  pending: 'Aguardando pagamento', paid: 'Pago', partially_paid: 'Parcialmente pago',
  cancelled: 'Cancelado', expired: 'Expirado', refunded: 'Estornado',
}
const STATUS_BADGE = {
  pending: 'bg-yellow-lt', paid: 'bg-green-lt', partially_paid: 'bg-blue-lt',
  cancelled: 'bg-red-lt', expired: 'bg-secondary-lt', refunded: 'bg-purple-lt',
}

export default function MeusPedidos() {
  const location = useLocation()
  const { data: orders = [], isLoading } = useQuery({
    queryKey: ['my', 'orders'],
    queryFn: () => apiGet('/orders'),
  })

  if (isLoading) return <p style={{ padding: '2rem' }}>Carregando…</p>

  return (
    <main className="container-xl py-4" style={{ maxWidth: 860 }}>
      <h1>Meus pedidos</h1>
      <p><Link to="/minha-conta">← Minha conta</Link> · <Link to="/minha-conta/ingressos">Meus ingressos</Link></p>

      {location.state?.created && (
        <div className="alert alert-success">
          Pedido criado! Sua reserva está garantida pelo prazo indicado abaixo.
        </div>
      )}

      {orders.length === 0 && <p>Você ainda não tem pedidos.</p>}

      {orders.map((order) => (
        <div className="card mb-3" key={order.code}>
          <div className="card-header d-flex justify-content-between">
            <span>
              <code>{order.code}</code> · {order.event.name}
            </span>
            <span className={`badge ${STATUS_BADGE[order.status]}`}>{STATUS_LABEL[order.status]}</span>
          </div>
          <div className="card-body">
            <p className="mb-1">Total: <strong>{formatMoney(order.totalAmount)}</strong></p>
            {order.status === 'pending' && (
              <p className="mb-2">
                {order.reservedUntil && (
                  <span className="text-warning me-2">
                    Reserva garantida até {new Date(order.reservedUntil).toLocaleString('pt-BR')}.
                  </span>
                )}
                <Link className="btn btn-primary btn-sm" to={`/pedido/${order.code}/pagar`}>
                  Pagar agora
                </Link>
              </p>
            )}
            <ul className="mb-0">
              {order.tickets.map((ticket) => (
                <li key={ticket.code}>
                  {ticket.participantName} — {ticket.ticketTypeName}
                  {ticket.isCourtesy && <span className="badge bg-green-lt ms-1">cortesia</span>}
                  {' '}(<code>{ticket.code}</code>)
                </li>
              ))}
            </ul>
          </div>
        </div>
      ))}
    </main>
  )
}
