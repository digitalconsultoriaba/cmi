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
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)

  // filtros de exportação (sem paginação)
  const exportParams = new URLSearchParams({ type })
  if (search) exportParams.set('search', search)
  if (from) exportParams.set('from', from)
  if (to) exportParams.set('to', to)

  const params = new URLSearchParams(exportParams)
  params.set('page', page)
  params.set('perPage', perPage)
  const qs = params.toString()

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'report', qs],
    queryFn: () => apiGet(`/admin/events/${eventId}/reports/preview?${qs}`),
    keepPreviousData: true,
  })

  const exportUrl = `/api/admin/events/${eventId}/reports/${type}.xlsx?${exportParams}`

  return (
    <>
      <div className="card mb-3">
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-3">
              <label className="form-label">Relatório</label>
              <select className="form-select" value={type} onChange={(e) => { setType(e.target.value); setPage(1) }}>
                {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Buscar</label>
              <input className="form-control" placeholder="Nome…" value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1) }} />
            </div>
            <div className="col-md-2">
              <label className="form-label">De</label>
              <input type="date" className="form-control" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
            </div>
            <div className="col-md-2">
              <label className="form-label">Até</label>
              <input type="date" className="form-control" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
            </div>
            <div className="col-md-2">
              <a className="btn btn-primary w-100" href={exportUrl}>Exportar .xlsx</a>
            </div>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card-header d-flex align-items-center">
          <h3 className="card-title mb-0">
            {data ? `${data.total} linha(s)` : 'Prévia'}
          </h3>
          <div className="ms-auto">
            <select className="form-select form-select-sm w-auto" value={perPage}
              onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1) }}>
              <option value={25}>25 por página</option>
              <option value={50}>50 por página</option>
              <option value={100}>100 por página</option>
            </select>
          </div>
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
        {data && data.lastPage > 1 && (
          <div className="card-footer d-flex align-items-center">
            <p className="m-0 text-secondary">Página {data.page} de {data.lastPage}</p>
            <ul className="pagination m-0 ms-auto">
              <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}>
                <button className="page-link" onClick={() => setPage((p) => Math.max(1, p - 1))}>Anteriores</button>
              </li>
              <li className={`page-item ${page >= data.lastPage ? 'disabled' : ''}`}>
                <button className="page-link" onClick={() => setPage((p) => p + 1)}>Próximos</button>
              </li>
            </ul>
          </div>
        )}
      </div>
    </>
  )
}
