import { Fragment, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '../../../lib/api'
import { ApiErrorAlert, useApiAction } from '../../components'
import ClienteFicha from './ClienteFicha'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

// Badges sólidos (cores fortes) — texto branco
const STATUS_BADGE = {
  paid: 'bg-success text-white', confirmed: 'bg-success text-white',
  courtesy: 'bg-purple text-white', used: 'bg-primary text-white',
  reserved: 'bg-warning text-dark', awaiting_payment: 'bg-warning text-dark',
  cancelled: 'bg-danger text-white', transferred: 'bg-secondary text-white',
  refunded: 'bg-danger text-white',
}

export default function Inscritos() {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [perPage, setPerPage] = useState(25)
  const [page, setPage] = useState(1)
  const [settling, setSettling] = useState(null) // ticket em baixa (mostra form)
  const [selectedUser, setSelectedUser] = useState(null) // ficha do cliente aberta

  const params = new URLSearchParams()
  if (search) params.set('search', search)
  if (status) params.set('status', status)
  params.set('perPage', perPage)
  params.set('page', page)

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'attendees', search, status, perPage, page],
    queryFn: () => apiGet(`/admin/events/${eventId}/attendees?${params}`),
    keepPreviousData: true,
  })

  const items = data?.items ?? []

  // Ficha do cliente aberta → mostra a ficha no lugar da lista
  if (selectedUser) {
    return <ClienteFicha userId={selectedUser} onClose={() => setSelectedUser(null)} />
  }

  const confirmarPagamento = (orderCode, justification) => run(
    () => apiPost(`/admin/orders/${orderCode}/pay-manual`, { justification }),
    { onSuccess: () => {
      setSettling(null)
      queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId] })
    } }
  )

  return (
    <>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="card mb-3">
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-5">
              <label className="form-label">Buscar (irmão/convidado)</label>
              <input className="form-control" placeholder="Nome…"
                value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Situação</label>
              <select className="form-select" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
                <option value="">Todas</option>
                <option value="paid">Pago</option>
                <option value="confirmed">Confirmado</option>
                <option value="courtesy">Cortesia</option>
                <option value="used">Usado</option>
                <option value="reserved">Reservado</option>
                <option value="awaiting_payment">Aguardando pagamento</option>
                <option value="cancelled">Cancelado</option>
                <option value="transferred">Transferido</option>
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Por página</label>
              <select className="form-select" value={perPage}
                onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1) }}>
                <option value={25}>25</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">{data?.total ?? 0} cadastro(s)</h3>
        </div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead>
              <tr><th>Participante</th><th>Ingresso</th><th>Camisa</th><th className="text-end">Valor</th><th>Situação</th><th /></tr>
            </thead>
            <tbody>
              {items.map((i) => (
                <Fragment key={i.code}>
                  <tr role={i.buyerUserId ? 'button' : undefined}
                    onClick={() => i.buyerUserId && setSelectedUser(i.buyerUserId)}>
                    <td>
                      <span className="fw-bold">{i.participantName}</span>
                      {i.orderCode && <div className="small text-secondary">Pedido {i.orderCode}</div>}
                      {i.companionName && (
                        <div className="small text-secondary">+ {i.companionName}
                          {i.isCouple && <span className="badge bg-purple text-white ms-1">casal</span>}
                        </div>
                      )}
                    </td>
                    <td>{i.ticketTypeName}</td>
                    <td className="small">
                      {i.shirt ?? '—'}
                      {i.companionShirt && <div className="text-secondary">acomp.: {i.companionShirt}</div>}
                    </td>
                    <td className="text-end">{money(i.amount)}</td>
                    <td><span className={`badge ${STATUS_BADGE[i.status] ?? 'bg-secondary text-white'}`}>{i.statusLabel}</span></td>
                    <td className="text-end">
                      {i.paymentPending && (
                        <button className="btn btn-sm btn-success" disabled={busy}
                          onClick={(e) => { e.stopPropagation(); setSettling(settling === i.code ? null : i.code) }}>
                          Confirmar pagamento
                        </button>
                      )}
                    </td>
                  </tr>
                  {settling === i.code && (
                    <tr>
                      <td colSpan={6} className="bg-light">
                        <SettleForm order={i.orderCode} busy={busy}
                          onConfirm={(j) => confirmarPagamento(i.orderCode, j)}
                          onCancel={() => setSettling(null)} />
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
              {items.length === 0 && (
                <tr><td colSpan={6} className="text-secondary">Nenhum inscrito no filtro.</td></tr>
              )}
            </tbody>
          </table>
        </div>
        {data && data.lastPage > 1 && (
          <div className="card-footer d-flex align-items-center">
            <p className="m-0 text-secondary">
              Página {data.page} de {data.lastPage} · {data.total} cadastro(s)
            </p>
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

function SettleForm({ order, busy, onConfirm, onCancel }) {
  const [justification, setJustification] = useState('')
  return (
    <div className="d-flex gap-2 align-items-center flex-wrap">
      <span className="text-secondary">Baixa do pedido <code>{order}</code>:</span>
      <input className="form-control form-control-sm" style={{ maxWidth: 360 }}
        placeholder="Justificativa (ex.: dinheiro na secretaria)"
        value={justification} onChange={(e) => setJustification(e.target.value)} />
      <button className="btn btn-sm btn-success" disabled={busy || justification.trim().length < 10}
        onClick={() => onConfirm(justification)}>Confirmar baixa</button>
      <button className="btn btn-sm" onClick={onCancel}>Cancelar</button>
      <small className="text-secondary">Mín. 10 caracteres.</small>
    </div>
  )
}
