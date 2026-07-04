import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'

const ACTION_LABEL = {
  'payment.registered': 'Baixa de pagamento',
  'payment.refunded': 'Estorno',
  'ticket.cancelled': 'Ingresso cancelado',
  'order.cancelled': 'Pedido cancelado',
  'order.expired': 'Reserva expirada',
  'ticket.transferred': 'Transferência',
  'ticket.checked_in': 'Check-in',
  'courtesy.issued': 'Cortesias emitidas',
  'courtesy.redeemed': 'Cortesia resgatada',
  'sponsorship.installment_paid': 'Parcela de patrocínio',
  'event.updated': 'Evento alterado',
  'event.cancelled': 'Evento cancelado',
}

export default function Auditoria() {
  const [action, setAction] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)

  const params = new URLSearchParams()
  if (action) params.set('action', action)
  if (from) params.set('from', from)
  if (to) params.set('to', to)
  params.set('page', page)

  const { data, isFetching } = useQuery({
    queryKey: ['admin', 'audit', action, from, to, page],
    queryFn: () => apiGet(`/admin/audit?${params}`),
    keepPreviousData: true,
  })

  const items = data?.items ?? []
  const meta = data?.meta

  return (
    <>
      <div className="page-header d-print-none">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">Governança</div>
            <h1 className="page-title">Auditoria</h1>
          </div>
        </div>
        <div className="text-secondary mt-1">
          Trilha imutável das ações sensíveis — quem fez o quê e quando.
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">{meta ? `Registros (${meta.total})` : 'Registros'}</h3>
          <div className="card-actions">
            <div className="d-flex gap-2 flex-wrap">
              <select className="form-select form-select-sm w-auto" value={action}
                onChange={(e) => { setAction(e.target.value); setPage(1) }}>
                <option value="">Todas as ações</option>
                {Object.entries(ACTION_LABEL).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              <input type="date" className="form-control form-control-sm w-auto"
                value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
              <input type="date" className="form-control form-control-sm w-auto"
                value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
            </div>
          </div>
        </div>

        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead>
              <tr><th>Quando</th><th>Ação</th><th>Descrição</th><th>Referência</th><th>Autor</th></tr>
            </thead>
            <tbody>
              {items.map((log) => (
                <tr key={log.id}>
                  <td className="text-nowrap text-secondary">
                    {new Date(log.createdAt).toLocaleString('pt-BR')}
                  </td>
                  <td>
                    <span className="badge bg-blue-lt">
                      {ACTION_LABEL[log.action] ?? log.action}
                    </span>
                  </td>
                  <td>{log.description}</td>
                  <td>{log.subject?.reference && <code>{log.subject.reference}</code>}</td>
                  <td>
                    {log.causer
                      ? log.causer.name
                      : <span className="badge bg-secondary-lt">sistema</span>}
                  </td>
                </tr>
              ))}
              {items.length === 0 && !isFetching && (
                <tr><td colSpan={5} className="text-secondary">Nenhum registro no filtro.</td></tr>
              )}
            </tbody>
          </table>
        </div>

        {meta && meta.lastPage > 1 && (
          <div className="card-footer d-flex align-items-center">
            <p className="m-0 text-secondary">
              página {meta.currentPage} de {meta.lastPage}
            </p>
            <ul className="pagination m-0 ms-auto">
              <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}>
                <button className="page-link" onClick={() => setPage((p) => p - 1)}>
                  Anteriores
                </button>
              </li>
              <li className={`page-item ${page >= meta.lastPage ? 'disabled' : ''}`}>
                <button className="page-link" onClick={() => setPage((p) => p + 1)}>
                  Próximos
                </button>
              </li>
            </ul>
          </div>
        )}
      </div>
    </>
  )
}
