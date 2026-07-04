import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'
import DonutChart from '../components/DonutChart'
import AreaChart from '../components/AreaChart'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const monthLabel = (ym) => {
  const [y, m] = ym.split('-')
  return `${m}/${y}`
}

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

export default function PainelModulo() {
  const [event, setEvent] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const { data: events = [] } = useQuery({
    queryKey: ['admin', 'events'],
    queryFn: () => apiGet('/admin/events'),
  })

  const params = new URLSearchParams()
  if (event) params.set('event', event)
  if (from) params.set('from', from)
  if (to) params.set('to', to)
  const qs = params.toString()

  const { data } = useQuery({
    queryKey: ['admin', 'overview', qs],
    queryFn: () => apiGet(`/admin/overview${qs ? `?${qs}` : ''}`),
    keepPreviousData: true,
  })

  if (!data) return <p className="text-secondary">Carregando…</p>

  const { cards, eventsByStatus, inscriptionsByMonth } = data

  return (
    <>
      <div className="card mb-3">
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-6">
              <label className="form-label">Evento</label>
              <select className="form-select" value={event} onChange={(e) => setEvent(e.target.value)}>
                <option value="">Todos os eventos</option>
                {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.name}</option>)}
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Período (de)</label>
              <input type="date" className="form-control" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div className="col-md-3">
              <label className="form-label">até</label>
              <input type="date" className="form-control" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
          </div>
        </div>
      </div>

      <div className="row row-deck row-cards mb-3">
        <Stat label="Eventos" value={cards.events} />
        <Stat label="Publicados" value={cards.published} className="text-green" />
        <Stat label="Próximos" value={cards.upcoming} className="text-blue" />
        <Stat label="Inscritos (ativos)" value={cards.activeRegistrations} />
      </div>
      <div className="row row-deck row-cards mb-3">
        <Stat label="Receita confirmada" value={money(cards.revenueConfirmed)} className="text-green" />
        <Stat label="Receita prevista" value={money(cards.revenueProjected)} className="text-orange" />
        <Stat label="Patrocínio (pago)" value={money(cards.sponsorshipPaid)} className="text-green" />
        <Stat label="Reembolsos em aberto" value={cards.refundsOpen} className="text-red" />
      </div>

      <div className="row row-deck row-cards">
        <div className="col-lg-5">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Eventos por situação</h3></div>
            <div className="card-body">
              <DonutChart
                series={eventsByStatus.map((s) => s.count)}
                labels={eventsByStatus.map((s) => s.label)}
              />
            </div>
          </div>
        </div>
        <div className="col-lg-7">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Inscrições por mês</h3></div>
            <div className="card-body">
              <AreaChart
                categories={inscriptionsByMonth.map((p) => monthLabel(p.month))}
                data={inscriptionsByMonth.map((p) => p.count)}
              />
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
