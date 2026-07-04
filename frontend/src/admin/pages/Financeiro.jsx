import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'

const money = (value) => Number(value ?? 0).toLocaleString('pt-BR', {
  style: 'currency', currency: 'BRL',
})

const MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']

function StatCard({ subheader, value, hint }) {
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card">
        <div className="card-body">
          <div className="subheader">{subheader}</div>
          <div className="d-flex align-items-baseline">
            <div className="h1 mb-0 me-2">{value}</div>
          </div>
          {hint && <div className="text-secondary mt-2">{hint}</div>}
        </div>
      </div>
    </div>
  )
}

export default function Financeiro() {
  const now = new Date()
  const [mode, setMode] = useState('all') // all | month | range
  const [month, setMonth] = useState(now.getMonth() + 1)
  const [year, setYear] = useState(now.getFullYear())
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const params = new URLSearchParams()
  if (mode === 'month') { params.set('month', month); params.set('year', year) }
  if (mode === 'range' && from) params.set('from', from)
  if (mode === 'range' && to) params.set('to', to)
  const query = params.toString()

  const { data } = useQuery({
    queryKey: ['treasury', 'finance', query],
    queryFn: () => apiGet(`/treasury/finance${query ? `?${query}` : ''}`),
    keepPreviousData: true,
  })

  if (!data) return <p className="text-secondary">Carregando…</p>

  return (
    <>
      <div className="page-header d-print-none">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">Tesouraria</div>
            <h1 className="page-title">Financeiro</h1>
          </div>
          <div className="col-auto ms-auto d-print-none">
            <div className="btn-list">
              <a className="btn btn-primary"
                href={`/api/treasury/reports/finance.xlsx${query ? `?${query}` : ''}`}>
                Exportar .xlsx
              </a>
            </div>
          </div>
        </div>
      </div>

      <div className="card mb-3">
        <div className="card-body">
          <div className="d-flex gap-2 flex-wrap align-items-center">
            <select className="form-select form-select-sm w-auto" value={mode}
              onChange={(e) => setMode(e.target.value)}>
              <option value="all">Todo o período</option>
              <option value="month">Mês/ano</option>
              <option value="range">Intervalo</option>
            </select>
            {mode === 'month' && (
              <>
                <select className="form-select form-select-sm w-auto" value={month}
                  onChange={(e) => setMonth(Number(e.target.value))}>
                  {MONTHS.map((label, i) => <option key={i} value={i + 1}>{label}</option>)}
                </select>
                <input type="number" className="form-control form-control-sm" style={{ width: '6rem' }}
                  value={year} onChange={(e) => setYear(Number(e.target.value))} />
              </>
            )}
            {mode === 'range' && (
              <>
                <input type="date" className="form-control form-control-sm w-auto"
                  value={from} onChange={(e) => setFrom(e.target.value)} />
                <span className="text-secondary">até</span>
                <input type="date" className="form-control form-control-sm w-auto"
                  value={to} onChange={(e) => setTo(e.target.value)} />
              </>
            )}
            <span className="text-secondary small ms-auto">
              Datas no fuso oficial do evento (Brasil).
            </span>
          </div>
        </div>
      </div>

      <div className="row row-deck row-cards mb-3">
        <StatCard subheader="Recebido no período" value={money(data.total.amount)}
          hint={`${data.total.count} pagamento(s)`} />
        <StatCard subheader="Devolvido no período" value={money(data.refunds.amount)}
          hint={`${data.refunds.count} estorno(s)`} />
        <StatCard subheader="Líquido" value={money(data.net)}
          hint="recebido − devolvido" />
        <StatCard subheader="Em aberto (hoje)" value={money(data.pendingOrders.amount)}
          hint={`${data.pendingOrders.count} pedido(s) pendente(s)`} />
      </div>

      <div className="row row-deck row-cards">
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Por forma de pagamento</h3></div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter">
                <thead>
                  <tr><th>Forma</th><th className="text-end">Qtde</th><th className="text-end">Valor</th></tr>
                </thead>
                <tbody>
                  {data.byMethod.map((row) => (
                    <tr key={row.method}>
                      <td>{row.label}</td>
                      <td className="text-end">{row.count}</td>
                      <td className="text-end">{money(row.amount)}</td>
                    </tr>
                  ))}
                  {data.byMethod.length === 0 && (
                    <tr><td colSpan={3} className="text-secondary">Nada recebido no período.</td></tr>
                  )}
                </tbody>
                <tfoot>
                  <tr>
                    <th>Total</th>
                    <th className="text-end">{data.total.count}</th>
                    <th className="text-end">{money(data.total.amount)}</th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Patrocínios (posição atual)</h3></div>
            <div className="card-body">
              <div className="row g-3 mb-3">
                <div className="col-auto">
                  <div className="subheader">Recebido</div>
                  <div className="h3 mb-0">{money(data.sponsorships.received)}</div>
                </div>
                <div className="col-auto">
                  <div className="subheader">A receber</div>
                  <div className="h3 mb-0">{money(data.sponsorships.receivable)}</div>
                </div>
              </div>
              {data.sponsorships.overdue.count > 0 ? (
                <div className="alert alert-warning mb-0">
                  <strong>{data.sponsorships.overdue.count} parcela(s) em atraso</strong>
                  {' '}somando {money(data.sponsorships.overdue.amount)}.
                </div>
              ) : (
                <div className="text-secondary">Nenhuma parcela em atraso.</div>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
