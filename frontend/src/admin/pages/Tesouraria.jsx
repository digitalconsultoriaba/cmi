import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Card, ApiErrorAlert, useApiAction } from '../components'
import { apiGet, apiPost } from '../../lib/api'
import { formatMoney } from '../../lib/money'

const STATUS_LABEL = {
  pending: 'Pendente', paid: 'Pago', failed: 'Falhou',
  expired: 'Expirado', refunded: 'Estornado', chargeback: 'Chargeback',
}
const STATUS_BADGE = {
  pending: 'bg-yellow-lt', paid: 'bg-green-lt', failed: 'bg-red-lt',
  expired: 'bg-secondary-lt', refunded: 'bg-purple-lt', chargeback: 'bg-red-lt',
}
const SOURCE_LABEL = {
  webhook: 'automática (notificação)', reconciliation: 'conciliação',
  gateway: 'gateway (cartão)', manual: 'baixa manual',
}

export default function Tesouraria() {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [filters, setFilters] = useState({ status: '', method: '' })
  const [manualFor, setManualFor] = useState(null)
  const [justification, setJustification] = useState('')
  const [reconcileResult, setReconcileResult] = useState(null)

  const query = new URLSearchParams(
    Object.fromEntries(Object.entries(filters).filter(([, v]) => v))
  ).toString()

  const { data: receivables = [] } = useQuery({
    queryKey: ['treasury', 'receivables', query],
    queryFn: () => apiGet(`/treasury/receivables${query ? `?${query}` : ''}`),
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['treasury'] })

  const reconcile = () => run(
    () => apiPost('/treasury/reconcile'),
    { onSuccess: (result) => { setReconcileResult(result); refresh() } }
  )

  const payManual = () => run(
    () => apiPost(`/treasury/orders/${manualFor}/pay-manual`, { justification }),
    { onSuccess: () => { setManualFor(null); setJustification(''); refresh() } }
  )

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="mb-0">Tesouraria — recebimentos</h2>
        <button className="btn btn-primary" onClick={reconcile} disabled={busy}>
          {busy ? 'Conciliando…' : 'Conciliar agora'}
        </button>
      </div>

      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      {reconcileResult && (
        <div className="alert alert-info alert-dismissible">
          Conciliação: {reconcileResult.checked} verificadas · {reconcileResult.settled} baixadas ·{' '}
          {reconcileResult.expired} expiradas · {reconcileResult.errors} erros
          <button type="button" className="btn-close" onClick={() => setReconcileResult(null)} />
        </div>
      )}

      <Card title="Filtros">
        <div className="row g-2">
          <div className="col-md-3">
            <select className="form-select" value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
              <option value="">Todas as situações</option>
              {Object.entries(STATUS_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </select>
          </div>
          <div className="col-md-3">
            <select className="form-select" value={filters.method}
              onChange={(e) => setFilters({ ...filters, method: e.target.value })}>
              <option value="">Todos os meios</option>
              <option value="pix">Pix</option>
              <option value="boleto">Boleto</option>
              <option value="card">Cartão</option>
              <option value="manual">Manual</option>
            </select>
          </div>
        </div>
      </Card>

      <Card title={`Recebimentos (${receivables.length})`}>
        <table className="table table-vcenter">
          <thead><tr>
            <th>Pedido</th><th>Comprador</th><th>Meio</th><th>Valor</th>
            <th>Situação</th><th>Origem da baixa</th><th /></tr></thead>
          <tbody>
            {receivables.map((payment) => (
              <tr key={payment.id} className={payment.flagged ? 'table-warning' : ''}>
                <td>
                  <code>{payment.orderCode}</code>
                  {payment.flagged && (
                    <div><span className="badge bg-orange-lt">pendência: pedido {payment.orderStatus}</span></div>
                  )}
                </td>
                <td>{payment.buyerName}</td>
                <td>{payment.method}</td>
                <td>{formatMoney(payment.amount)}</td>
                <td>
                  <span className={`badge ${STATUS_BADGE[payment.status]}`}>
                    {STATUS_LABEL[payment.status]}
                  </span>
                </td>
                <td>
                  {payment.source ? SOURCE_LABEL[payment.source] ?? payment.source : '—'}
                  {payment.registeredBy && <div className="text-secondary small">por {payment.registeredBy}</div>}
                </td>
                <td className="text-end">
                  {payment.status === 'pending' && payment.orderStatus === 'pending' && (
                    <button className="btn btn-sm" onClick={() => setManualFor(payment.orderCode)}>
                      Baixa manual
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      {manualFor && (
        <Card title={`Baixa manual — pedido ${manualFor}`}>
          <p className="text-secondary">
            Use apenas em contingência (pagamento comprovado fora do sistema).
            A ação fica registrada com seu nome. Você não pode baixar um pedido seu.
          </p>
          <textarea className="form-control mb-2" rows={2}
            placeholder="Justificativa obrigatória (ex.: transferência comprovada pelo extrato em 03/07)"
            value={justification} onChange={(e) => setJustification(e.target.value)} />
          <div className="btn-list">
            <button className="btn btn-danger" disabled={busy || justification.trim().length < 10}
              onClick={payManual}>
              Confirmar baixa manual
            </button>
            <button className="btn" onClick={() => setManualFor(null)}>Cancelar</button>
          </div>
        </Card>
      )}
    </>
  )
}
