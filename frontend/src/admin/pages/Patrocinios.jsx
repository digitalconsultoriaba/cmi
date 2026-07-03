import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { Card, ApiErrorAlert, useApiAction } from '../components'
import { apiGet, apiPost } from '../../lib/api'
import { parseMoney, formatMoney } from '../../lib/money'

const STATUS_LABELS = { pending: 'Pendente', partial: 'Parcial', paid: 'Pago', cancelled: 'Cancelado' }
const STATUS_BADGE = { pending: 'bg-yellow-lt', partial: 'bg-blue-lt', paid: 'bg-green-lt', cancelled: 'bg-red-lt' }

export default function Patrocinios() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [form, setForm] = useState({ company_name: '', contact: '', total_amount: '', installments_count: 1 })

  const eventId = event?.id
  const { data: sponsorships = [] } = useQuery({
    queryKey: ['admin', eventId, 'sponsorships'],
    queryFn: () => apiGet(`/admin/events/${eventId}/sponsorships`),
    enabled: !!eventId,
  })

  if (!event) return <p>Carregando…</p>

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'sponsorships'] })

  const criar = () => run(() => apiPost(`/admin/events/${eventId}/sponsorships`, {
    ...form,
    total_amount: parseMoney(form.total_amount) ?? form.total_amount,
    installments_count: Number(form.installments_count),
  }), { onSuccess: () => { refresh(); setForm({ company_name: '', contact: '', total_amount: '', installments_count: 1 }) } })

  const pagar = (sponsorship, installment) => run(
    () => apiPost(`/admin/events/${eventId}/sponsorships/${sponsorship.id}/installments/${installment.number}/pay`),
    { onSuccess: refresh }
  )

  const cancelar = (sponsorship) => run(
    () => apiPost(`/admin/events/${eventId}/sponsorships/${sponsorship.id}/cancel`),
    { onSuccess: refresh }
  )

  return (
    <>
      <h2>Patrocínios</h2>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <Card title="Novo patrocínio">
        <div className="row g-2 align-items-end">
          <div className="col-md-4">
            <label className="form-label">Empresa</label>
            <input className="form-control" value={form.company_name}
              onChange={(e) => setForm({ ...form, company_name: e.target.value })} />
          </div>
          <div className="col-md-3">
            <label className="form-label">Contato</label>
            <input className="form-control" value={form.contact}
              onChange={(e) => setForm({ ...form, contact: e.target.value })} />
          </div>
          <div className="col-md-2">
            <label className="form-label">Valor total</label>
            <input className="form-control" placeholder="1.000,00" value={form.total_amount}
              onChange={(e) => setForm({ ...form, total_amount: e.target.value })} />
          </div>
          <div className="col-md-1">
            <label className="form-label">Parcelas</label>
            <input type="number" min={1} max={36} className="form-control" value={form.installments_count}
              onChange={(e) => setForm({ ...form, installments_count: e.target.value })} />
          </div>
          <div className="col-md-2">
            <button className="btn btn-primary" onClick={criar} disabled={busy}>Registrar</button>
          </div>
        </div>
      </Card>

      {sponsorships.map((sponsorship) => (
        <Card key={sponsorship.id}
          title={`${sponsorship.companyName} — ${formatMoney(sponsorship.totalAmount)}`}
          actions={
            <span className="btn-list">
              <span className={`badge ${STATUS_BADGE[sponsorship.status]}`}>{STATUS_LABELS[sponsorship.status]}</span>
              {sponsorship.status !== 'cancelled' && (
                <button className="btn btn-sm btn-outline-danger" onClick={() => cancelar(sponsorship)}>Cancelar</button>
              )}
            </span>
          }>
          <table className="table table-sm table-vcenter">
            <thead><tr><th>#</th><th>Valor</th><th>Situação</th><th>Pago em</th><th /></tr></thead>
            <tbody>
              {sponsorship.installments.map((installment) => (
                <tr key={installment.id}>
                  <td>{installment.number}</td>
                  <td>{formatMoney(installment.amount)}</td>
                  <td>
                    <span className={`badge ${installment.status === 'paid' ? 'bg-green-lt' : 'bg-yellow-lt'}`}>
                      {installment.status === 'paid' ? 'Paga' : 'Pendente'}
                    </span>
                  </td>
                  <td>{installment.paidAt ? new Date(installment.paidAt).toLocaleDateString('pt-BR') : '—'}</td>
                  <td className="text-end">
                    {installment.status === 'pending' && sponsorship.status !== 'cancelled' && (
                      <button className="btn btn-sm btn-primary" onClick={() => pagar(sponsorship, installment)} disabled={busy}>
                        Registrar pagamento
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      ))}
    </>
  )
}
