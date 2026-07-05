import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '../../../lib/api'
import { ApiErrorAlert, useApiAction } from '../../components'
import ClienteFicha from './ClienteFicha'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const dateTime = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : null)

// Badges sólidos (cores fortes)
const STATUS_BADGE = {
  paid: 'bg-success text-white', pending: 'bg-warning text-dark',
  partially_paid: 'bg-orange text-white', cancelled: 'bg-danger text-white',
  expired: 'bg-secondary text-white', refunded: 'bg-danger text-white',
}

export default function FinanceiroEvento() {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [status, setStatus] = useState('')
  const [settling, setSettling] = useState(null)
  const [justification, setJustification] = useState('')
  const [selectedUser, setSelectedUser] = useState(null)

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'orders', status],
    queryFn: () => apiGet(`/admin/events/${eventId}/orders${status ? `?status=${status}` : ''}`),
    keepPreviousData: true,
  })

  const items = data?.items ?? []

  if (selectedUser) {
    return <ClienteFicha userId={selectedUser} onClose={() => setSelectedUser(null)} />
  }

  const darBaixa = (code) => run(
    () => apiPost(`/admin/orders/${code}/pay-manual`, { justification }),
    { onSuccess: () => {
      setSettling(null); setJustification('')
      queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId] })
    } }
  )

  return (
    <>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="card mb-3">
        <div className="card-body">
          <label className="form-label">Situação do pedido</label>
          <select className="form-select w-auto" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">Todos</option>
            <option value="pending">Aguardando pagamento</option>
            <option value="paid">Pago</option>
            <option value="partially_paid">Parcialmente pago</option>
            <option value="cancelled">Cancelado</option>
            <option value="expired">Expirado</option>
          </select>
        </div>
      </div>

      <div className="card">
        <div className="card-header"><h3 className="card-title">Pedidos ({data?.total ?? 0})</h3></div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead>
              <tr><th>Pedido</th><th>Comprador</th><th className="text-end">Valor</th><th>Ingressos</th><th>Forma</th><th>Recebido por</th><th>Situação</th><th /></tr>
            </thead>
            <tbody>
              {items.map((o) => (
                <tr key={o.code} role={o.buyerUserId ? 'button' : undefined}
                  onClick={() => o.buyerUserId && settling !== o.code && setSelectedUser(o.buyerUserId)}>
                  <td><code>{o.code}</code></td>
                  <td>{o.buyerName}<div className="small text-secondary">{o.buyerEmail}</div></td>
                  <td className="text-end">{money(o.total)}</td>
                  <td>{o.ticketCount}</td>
                  <td className="text-capitalize">{o.method ?? '—'}</td>
                  <td>
                    {o.receivedBy
                      ? <>{o.receivedBy}{o.paidAt && <div className="small text-secondary">{dateTime(o.paidAt)}</div>}</>
                      : <span className="text-secondary">—</span>}
                  </td>
                  <td><span className={`badge ${STATUS_BADGE[o.status] ?? 'bg-secondary text-white'}`}>{o.statusLabel}</span></td>
                  <td className="text-end" onClick={(e) => e.stopPropagation()}>
                    {o.canSettle && (
                      settling === o.code ? (
                        <div className="d-flex gap-1 align-items-center">
                          <input className="form-control form-control-sm" style={{ minWidth: 200 }}
                            placeholder="Justificativa (mín. 10)" value={justification}
                            onChange={(e) => setJustification(e.target.value)} />
                          <button className="btn btn-sm btn-success" disabled={busy || justification.trim().length < 10}
                            onClick={() => darBaixa(o.code)}>Confirmar</button>
                          <button className="btn btn-sm" onClick={() => { setSettling(null); setJustification('') }}>×</button>
                        </div>
                      ) : (
                        <button className="btn btn-sm btn-success" onClick={() => setSettling(o.code)}>
                          Dar baixa
                        </button>
                      )
                    )}
                  </td>
                </tr>
              ))}
              {items.length === 0 && (
                <tr><td colSpan={8} className="text-secondary">Nenhum pedido no filtro.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
