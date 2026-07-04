import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'

const TYPES = [
  ['geral', 'Geral'], ['contas-a-pagar', 'Contas a Pagar'], ['contas-a-receber', 'Contas a Receber'],
  ['categoria', 'Por categoria'], ['pessoa', 'Por fornecedor/cliente'], ['forma', 'Por forma de pagamento'],
  ['ingressos', 'Receitas de ingressos'], ['patrocinios', 'Patrocínios'],
  ['despesas-evento', 'Despesas do evento'], ['previsto-realizado', 'Previsto × Realizado'],
]

export default function Relatorios() {
  const [type, setType] = useState('geral')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [event, setEvent] = useState('')

  const { data: events = [] } = useQuery({ queryKey: ['admin', 'events'], queryFn: () => apiGet('/admin/events') })

  const params = new URLSearchParams()
  if (from) params.set('from', from)
  if (to) params.set('to', to)
  if (event) params.set('event', event)
  const qs = params.toString()

  const { data } = useQuery({
    queryKey: ['finance', 'report', type, qs],
    queryFn: () => apiGet(`/finance/reports/${type}${qs ? `?${qs}` : ''}`),
    keepPreviousData: true,
  })

  const exportUrl = (fmt) => `/api/finance/reports/${type}/${fmt}${qs ? `?${qs}` : ''}`

  return (
    <>
      <div className="card mb-3"><div className="card-body">
        <div className="row g-2 align-items-end">
          <div className="col-md-3"><label className="form-label">Relatório</label>
            <select className="form-select" value={type} onChange={(e) => setType(e.target.value)}>
              {TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></div>
          <div className="col-md-3"><label className="form-label">Evento</label>
            <select className="form-select" value={event} onChange={(e) => setEvent(e.target.value)}>
              <option value="">Todos</option>{events.map((ev) => <option key={ev.id} value={ev.id}>{ev.name}</option>)}</select></div>
          <div className="col-md-2"><label className="form-label">De</label>
            <input type="date" className="form-control" value={from} onChange={(e) => setFrom(e.target.value)} /></div>
          <div className="col-md-2"><label className="form-label">Até</label>
            <input type="date" className="form-control" value={to} onChange={(e) => setTo(e.target.value)} /></div>
          <div className="col-md-2 btn-list">
            <a className="btn btn-primary" href={exportUrl('xlsx')}>.xlsx</a>
            <a className="btn" href={exportUrl('pdf')}>PDF</a>
          </div>
        </div>
      </div></div>

      <div className="card">
        <div className="card-header"><h3 className="card-title">Prévia {data ? `— ${data.shown} de ${data.total}` : ''}</h3></div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr>{(data?.columns ?? []).map((c) => <th key={c}>{c}</th>)}</tr></thead>
            <tbody>
              {(data?.rows ?? []).map((row, ri) => <tr key={ri}>{row.map((cell, ci) => <td key={ci}>{cell}</td>)}</tr>)}
              {data && data.rows.length === 0 && <tr><td colSpan={data.columns.length || 1} className="text-secondary">Sem dados no filtro.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
