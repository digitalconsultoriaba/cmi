import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../../lib/api'
import DonutChart from '../../components/DonutChart'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

function Stat({ label, value, className = '' }) {
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card card-sm">
        <div className="card-body">
          <div className="subheader">{label}</div>
          <div className={`h1 mb-0 ${className}`}>{value}</div>
        </div>
      </div>
    </div>
  )
}

export default function PainelEvento() {
  const { eventId } = useParams()
  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'panel'],
    queryFn: () => apiGet(`/admin/events/${eventId}/dashboard`),
  })

  if (!data) return <p className="text-secondary">Carregando…</p>

  const { counters, financial, ticketsByStatus, byTicketType } = data

  return (
    <>
      <div className="row row-deck row-cards mb-3">
        <Stat label="Capacidade" value={counters.capacity ?? '∞'} />
        <Stat label="Inscritos (total)" value={counters.registeredTotal} />
        <Stat label="Pagos / Confirmados" value={counters.paidConfirmed} className="text-green" />
        <Stat label="Cortesias" value={counters.courtesies} className="text-blue" />
      </div>
      <div className="row row-deck row-cards mb-1">
        <Stat label="Presentes (check-in)" value={counters.present} className="text-green" />
        <Stat label="Aguardando pgto" value={counters.awaitingPayment} className="text-orange" />
        <Stat label="Cancelados" value={counters.cancelled} className="text-red" />
        <Stat label="Reembolsados" value={counters.refunded} />
      </div>
      <p className="text-secondary small">
        Inscritos (total) = Pagos/Confirmados + Cortesias. "Presentes" são os que já fizeram
        check-in (subconjunto). Aguardando pgto ainda não é inscrito.
      </p>

      <div className="row row-deck row-cards mb-3">
        <Stat label="Valor previsto" value={money(financial.expected)} />
        <Stat label="Valor confirmado" value={money(financial.confirmed)} className="text-green" />
        <Stat label="A receber" value={money(financial.receivable)} className="text-orange" />
        <Stat label="Patrocínio (pago)" value={money(financial.sponsorshipPaid)} className="text-green" />
      </div>

      <div className="row row-deck row-cards">
        <div className="col-lg-5">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Situação dos ingressos</h3></div>
            <div className="card-body">
              <DonutChart
                series={ticketsByStatus.map((s) => s.count)}
                labels={ticketsByStatus.map((s) => s.label)}
              />
            </div>
          </div>
        </div>
        <div className="col-lg-7">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Inscrições por tipo de ingresso</h3></div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <thead><tr><th>Tipo</th><th className="text-end">Pessoas</th><th className="text-end">Receita</th></tr></thead>
                <tbody>
                  {byTicketType.map((row) => (
                    <tr key={row.type}>
                      <td>{row.type}</td>
                      <td className="text-end">{row.count}</td>
                      <td className="text-end">{money(row.revenue)}</td>
                    </tr>
                  ))}
                  {byTicketType.length === 0 && (
                    <tr><td colSpan={3} className="text-secondary">Sem inscrições ainda.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
