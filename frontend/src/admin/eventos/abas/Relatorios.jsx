import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../../lib/api'

const TYPES = [
  { value: 'inscritos', label: 'Inscritos' },
  { value: 'financeiro', label: 'Financeiro' },
  { value: 'presencas', label: 'Presenças' },
  { value: 'camisas', label: 'Camisas' },
]

export default function Relatorios() {
  const { eventId } = useParams()
  const [type, setType] = useState('inscritos')
  const [search, setSearch] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const params = new URLSearchParams({ type })
  if (search) params.set('search', search)
  if (from) params.set('from', from)
  if (to) params.set('to', to)
  const qs = params.toString()

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'report', qs],
    queryFn: () => apiGet(`/admin/events/${eventId}/reports/preview?${qs}`),
    keepPreviousData: true,
  })

  const exportUrl = `/api/admin/events/${eventId}/reports/${type}.xlsx?${qs}`

  return (
    <>
      <div className="card mb-3">
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-3">
              <label className="form-label">Relatório</label>
              <select className="form-select" value={type} onChange={(e) => setType(e.target.value)}>
                {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Buscar</label>
              <input className="form-control" placeholder="Nome…" value={search}
                onChange={(e) => setSearch(e.target.value)} />
            </div>
            <div className="col-md-2">
              <label className="form-label">De</label>
              <input type="date" className="form-control" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div className="col-md-2">
              <label className="form-label">Até</label>
              <input type="date" className="form-control" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
            <div className="col-md-2">
              <a className="btn btn-primary w-100" href={exportUrl}>Exportar .xlsx</a>
            </div>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">
            Prévia {data ? `— ${data.shown} de ${data.total} linha(s)` : ''}
          </h3>
        </div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead>
              <tr>{(data?.columns ?? []).map((c) => <th key={c}>{c}</th>)}</tr>
            </thead>
            <tbody>
              {(data?.rows ?? []).map((row, ri) => (
                <tr key={ri}>{row.map((cell, ci) => <td key={ci}>{cell}</td>)}</tr>
              ))}
              {data && data.rows.length === 0 && (
                <tr><td colSpan={data.columns.length || 1} className="text-secondary">0 linhas no filtro.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
