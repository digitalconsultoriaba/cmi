import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'
import LancamentoModal from './LancamentoModal'
import LancamentoDetalhe from './LancamentoDetalhe'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const STATUS_BADGE = {
  open: 'bg-secondary text-white', partial: 'bg-primary text-white',
  settled: 'bg-success text-white', overdue: 'bg-danger text-white', cancelled: 'bg-dark text-white',
}

export default function Contas({ direction }) {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [perPage, setPerPage] = useState(25)
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [selected, setSelected] = useState(null)

  const params = new URLSearchParams({ direction, perPage, page })
  if (search) params.set('search', search)
  if (status) params.set('status', status)

  const { data } = useQuery({
    queryKey: ['finance', 'entries', direction, search, status, perPage, page],
    queryFn: () => apiGet(`/finance/entries?${params}`),
    keepPreviousData: true,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['finance'] })

  if (selected) {
    return <LancamentoDetalhe id={selected} onClose={() => { setSelected(null); refresh() }} />
  }

  const items = data?.items ?? []
  const isReceivable = direction === 'receivable'

  return (
    <>
      <div className="card mb-3"><div className="card-body">
        <div className="row g-2 align-items-end">
          <div className="col-md-5"><label className="form-label">Buscar</label>
            <input className="form-control" placeholder="Descrição…" value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }} /></div>
          <div className="col-md-4"><label className="form-label">Situação</label>
            <select className="form-select" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">Todas</option>
              <option value="open">Em aberto</option>
              <option value="partial">Parcial</option>
              <option value="settled">{isReceivable ? 'Recebido' : 'Pago'}</option>
              <option value="overdue">Vencido</option>
              <option value="cancelled">Cancelado</option>
            </select></div>
          <div className="col-md-3"><label className="form-label">Por página</label>
            <select className="form-select" value={perPage} onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1) }}>
              <option value={25}>25</option><option value={50}>50</option><option value={100}>100</option>
            </select></div>
        </div>
      </div></div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">{data?.total ?? 0} conta(s) — {isReceivable ? 'a receber' : 'a pagar'}</h3>
          <div className="card-actions">
            <button className="btn btn-primary" onClick={() => setCreating(true)}>Nova conta</button>
          </div>
        </div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr>
              <th>Descrição</th><th>Evento</th><th>{isReceivable ? 'Cliente' : 'Fornecedor'}</th>
              <th>Categoria</th><th className="text-end">Valor</th><th className="text-end">Saldo</th>
              <th>Vencimento</th><th>Situação</th>
            </tr></thead>
            <tbody>
              {items.map((e) => (
                <tr key={e.id} role="button" onClick={() => setSelected(e.id)}>
                  <td className="fw-bold">{e.description}
                    {e.installment && <span className="badge bg-secondary-lt ms-1">{e.installment.number}/{e.installment.total}</span>}
                    {e.readonly && <span className="badge bg-blue-lt ms-1">auto</span>}
                  </td>
                  <td>{e.event?.name ?? <span className="text-secondary">Geral</span>}</td>
                  <td>{e.person ?? '—'}</td>
                  <td>{e.category ?? '—'}</td>
                  <td className="text-end">{money(e.amount)}</td>
                  <td className="text-end">{money(e.balance)}</td>
                  <td>{e.dueDate ? new Date(e.dueDate + 'T00:00').toLocaleDateString('pt-BR') : '—'}</td>
                  <td><span className={`badge ${STATUS_BADGE[e.status] ?? 'bg-secondary text-white'}`}>{e.statusLabel}</span></td>
                </tr>
              ))}
              {items.length === 0 && <tr><td colSpan={8} className="text-secondary">Nenhuma conta no filtro.</td></tr>}
            </tbody>
          </table>
        </div>
        {data && data.lastPage > 1 && (
          <div className="card-footer d-flex align-items-center">
            <p className="m-0 text-secondary">Página {data.page} de {data.lastPage} · {data.total} conta(s)</p>
            <ul className="pagination m-0 ms-auto">
              <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}>
                <button className="page-link" onClick={() => setPage((p) => Math.max(1, p - 1))}>Anteriores</button></li>
              <li className={`page-item ${page >= data.lastPage ? 'disabled' : ''}`}>
                <button className="page-link" onClick={() => setPage((p) => p + 1)}>Próximos</button></li>
            </ul>
          </div>
        )}
      </div>

      {creating && (
        <LancamentoModal direction={direction}
          onClose={() => setCreating(false)}
          onSaved={() => { setCreating(false); refresh() }} />
      )}
    </>
  )
}
