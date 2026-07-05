import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

function Stat({ label, value, hint, className = '' }) {
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card card-sm"><div className="card-body">
        <div className="subheader">{label}</div>
        <div className={`h1 mb-0 ${className}`}>{value}</div>
        {hint && <div className="text-secondary small">{hint}</div>}
      </div></div>
    </div>
  )
}

export default function Dashboard() {
  const [event, setEvent] = useState('')
  const [monthSel, setMonthSel] = useState('')

  const { data: events = [] } = useQuery({ queryKey: ['admin', 'events'], queryFn: () => apiGet('/admin/events') })

  const params = new URLSearchParams()
  if (event) params.set('event', event)
  if (monthSel) {
    params.set('month', monthSel)
    // agrupa também o escopo geral pelo mês selecionado
    const [y, m] = monthSel.split('-').map(Number)
    params.set('from', `${monthSel}-01`)
    params.set('to', `${monthSel}-${String(new Date(y, m, 0).getDate()).padStart(2, '0')}`)
  }
  const qs = params.toString()

  const { data } = useQuery({
    queryKey: ['finance', 'dashboard', qs],
    queryFn: () => apiGet(`/finance/dashboard${qs ? `?${qs}` : ''}`),
    keepPreviousData: true,
  })

  if (!data) return <p className="text-secondary">Carregando…</p>
  const { month, overdue, balances, bestEvents, worstEvents, dueBuckets, upcoming } = data

  return (
    <>
      <div className="card mb-3"><div className="card-body">
        <div className="row g-2 align-items-end">
          <div className="col-md-7">
            <label className="form-label">Evento</label>
            <select className="form-select" value={event} onChange={(e) => setEvent(e.target.value)}>
              <option value="">Todos os eventos</option>
              {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.name}</option>)}
            </select>
          </div>
          <div className="col-md-3"><label className="form-label">Mês</label>
            <input type="month" className="form-control" value={monthSel} onChange={(e) => setMonthSel(e.target.value)} /></div>
          <div className="col-md-2 d-flex align-items-end">
            <button className="btn w-100" onClick={() => setMonthSel('')} disabled={!monthSel}>Mês atual</button>
          </div>
        </div>
      </div></div>

      <div className="row row-deck row-cards mb-3">
        <Stat label="A receber no mês" value={money(month.toReceive)} className="text-blue" />
        <Stat label="Recebido no mês" value={money(month.received)} className="text-green" />
        <Stat label="A pagar no mês" value={money(month.toPay)} className="text-orange" />
        <Stat label="Pago no mês" value={money(month.paid)} className="text-red" />
      </div>
      <div className="row row-deck row-cards mb-3">
        <Stat label="Saldo previsto" value={money(balances.expected)} />
        <Stat label="Saldo realizado" value={money(balances.realized)} className="text-green" />
        <Stat label="Vencidas a pagar" value={money(overdue.payable.amount)}
          hint={`${overdue.payable.count} conta(s)`} className="text-red" />
        <Stat label="Vencidas a receber" value={money(overdue.receivable.amount)}
          hint={`${overdue.receivable.count} conta(s)`} className="text-orange" />
      </div>

      <div className="row row-deck row-cards">
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Vencimentos</h3></div>
            <div className="card-body">
              <div className="d-flex gap-4">
                <div><div className="subheader">Hoje</div><div className="h2 text-red">{dueBuckets.today}</div></div>
                <div><div className="subheader">Próximos 7 dias</div><div className="h2 text-orange">{dueBuckets.next7}</div></div>
                <div><div className="subheader">+30 dias vencidas</div><div className="h2 text-red">{dueBuckets.over30}</div></div>
              </div>
            </div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <thead><tr><th>Próximos vencimentos</th><th>Tipo</th><th>Vence</th><th className="text-end">Saldo</th></tr></thead>
                <tbody>
                  {upcoming.map((u) => (
                    <tr key={u.entryId}>
                      <td>{u.description}</td>
                      <td>{u.direction === 'receivable' ? 'A receber' : 'A pagar'}</td>
                      <td>{u.dueDate ? new Date(u.dueDate + 'T00:00').toLocaleDateString('pt-BR') : '—'}</td>
                      <td className="text-end">{money(u.amount)}</td>
                    </tr>
                  ))}
                  {upcoming.length === 0 && <tr><td colSpan={4} className="text-secondary">Sem vencimentos.</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div className="col-lg-6">
          <div className="card mb-3">
            <div className="card-header"><h3 className="card-title">Eventos com melhor resultado</h3></div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <tbody>
                  {bestEvents.map((e, i) => (
                    <tr key={i}><td>{e.event}</td><td className="text-end text-green">{money(e.result)}</td></tr>
                  ))}
                  {bestEvents.length === 0 && <tr><td className="text-secondary">Sem dados.</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
          {worstEvents.length > 0 && (
            <div className="card">
              <div className="card-header"><h3 className="card-title">Eventos no prejuízo</h3></div>
              <div className="card-table table-responsive">
                <table className="table table-vcenter">
                  <tbody>
                    {worstEvents.map((e, i) => (
                      <tr key={i}><td>{e.event}</td><td className="text-end text-red">{money(e.result)}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  )
}
