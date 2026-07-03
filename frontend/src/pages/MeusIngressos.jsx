import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiGet } from '../lib/api'

const STATUS_LABEL = {
  reserved: 'Reservado', awaiting_payment: 'Aguardando pagamento', paid: 'Pago',
  confirmed: 'Confirmado', courtesy: 'Cortesia', cancelled: 'Cancelado',
  refunded: 'Estornado', transferred: 'Transferido', used: 'Utilizado',
}

export default function MeusIngressos() {
  const { data: tickets = [], isLoading } = useQuery({
    queryKey: ['my', 'tickets'],
    queryFn: () => apiGet('/tickets'),
  })

  if (isLoading) return <p style={{ padding: '2rem' }}>Carregando…</p>

  return (
    <main className="container-xl py-4" style={{ maxWidth: 860 }}>
      <h1>Meus ingressos</h1>
      <p><Link to="/minha-conta">← Minha conta</Link> · <Link to="/minha-conta/pedidos">Meus pedidos</Link></p>

      {tickets.length === 0 && <p>Você ainda não tem ingressos.</p>}

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
                <td><span className="badge bg-blue-lt">{STATUS_LABEL[ticket.status]}</span></td>
                <td className="text-end">
                  {ticket.receiptAvailable && (
                    <a className="btn btn-sm btn-primary" target="_blank" rel="noreferrer"
                      href={`/api/tickets/${ticket.code}/receipt`}>
                      Comprovante
                    </a>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </main>
  )
}
