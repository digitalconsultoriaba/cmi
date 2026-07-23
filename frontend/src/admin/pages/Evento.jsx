import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { Card, ApiErrorAlert, StatusBadge, useApiAction } from '../components'
import { apiPost, apiPut, apiUpload } from '../../lib/api'
import Loading from '../../components/Loading'

const FLAGS = [
  ['allowPix', 'Aceita Pix'],
  ['allowBoleto', 'Aceita boleto'],
  ['allowCard', 'Aceita cartão'],
  ['allowShirtChoice', 'Escolha de camisa'],
  ['requiresShirt', 'Camisa obrigatória'],
  ['allowKit', 'Inclui kit'],
  ['allowTransfer', 'Permite transferência'],
  ['allowUserCancel', 'Inscrito pode cancelar'],
  ['allowRefundRequest', 'Permite pedido de reembolso'],
  ['allowCourtesy', 'Cortesias habilitadas'],
]

const toInput = (iso) => (iso ? iso.slice(0, 16) : '')

export default function Evento() {
  const { data: event, isLoading } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [form, setForm] = useState(null)
  const [cancelReason, setCancelReason] = useState('')

  useEffect(() => {
    if (event) {
      setForm({
        name: event.name ?? '',
        description: event.description ?? '',
        location: event.location ?? '',
        starts_at: toInput(event.startsAt),
        ends_at: toInput(event.endsAt),
        sales_start_at: toInput(event.salesStartAt),
        sales_end_at: toInput(event.salesEndAt),
        total_capacity: event.totalCapacity ?? '',
        reservation_ttl_minutes: event.reservationTtlMinutes ?? 30,
        ...Object.fromEntries(FLAGS.map(([key]) => {
          const snake = key.replace(/[A-Z]/g, (c) => '_'+c.toLowerCase())
          return [snake, event[key]]
        })),
        courtesy_paid_threshold: event.courtesyPaidThreshold ?? '',
        courtesy_limit_per_account: event.courtesyLimitPerAccount ?? '',
      })
    }
  }, [event])

  if (isLoading || !event || !form) return <Loading fullscreen={false} />

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'events'] })
  const set = (field) => (e) => setForm({ ...form, [field]: e.target.value })
  const setFlag = (field) => (e) => setForm({ ...form, [field]: e.target.checked })

  const salvar = () => run(async () => {
    const payload = { ...form }
    for (const key of ['total_capacity', 'courtesy_paid_threshold', 'courtesy_limit_per_account']) {
      payload[key] = payload[key] === '' ? null : Number(payload[key])
    }
    for (const key of ['starts_at', 'ends_at', 'sales_start_at', 'sales_end_at']) {
      if (payload[key] === '') payload[key] = null
    }
    await apiPut(`/admin/events/${event.id}`, payload)
  }, { onSuccess: refresh })

  const publicar = () => run(() => apiPost(`/admin/events/${event.id}/publish`), { onSuccess: refresh })

  const cancelar = () => {
    if (!cancelReason.trim()) return setError({ message: 'Informe o motivo do cancelamento.', fields: {} })
    return run(() => apiPost(`/admin/events/${event.id}/cancel`, { reason: cancelReason }), { onSuccess: refresh })
  }

  const enviarBanner = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const data = new FormData()
    data.append('banner', file)
    run(() => apiUpload(`/admin/events/${event.id}/banner`, data), { onSuccess: refresh })
  }

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="mb-0">
          {event.name}{' '}
          <StatusBadge ok={event.status === 'published'} okLabel="publicado" badLabel={event.status} />
          {' '}
          <StatusBadge ok={event.salesOpen} okLabel="inscrições abertas" badLabel="inscrições fechadas" />
        </h2>
        <div className="btn-list">
          {event.status === 'draft' && (
            <button className="btn btn-primary" onClick={publicar} disabled={busy}>Publicar</button>
          )}
          <button className="btn btn-success" onClick={salvar} disabled={busy}>Salvar alterações</button>
        </div>
      </div>

      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <Card title="Dados do evento">
        <div className="row g-3">
          <div className="col-md-6">
            <label className="form-label">Nome</label>
            <input className="form-control" value={form.name} onChange={set('name')} />
          </div>
          <div className="col-md-6">
            <label className="form-label">Local</label>
            <input className="form-control" value={form.location} onChange={set('location')} />
          </div>
          <div className="col-12">
            <label className="form-label">Descrição</label>
            <textarea className="form-control" rows={3} value={form.description} onChange={set('description')} />
          </div>
          <div className="col-md-3">
            <label className="form-label">Início</label>
            <input type="datetime-local" className="form-control" value={form.starts_at} onChange={set('starts_at')} />
          </div>
          <div className="col-md-3">
            <label className="form-label">Fim</label>
            <input type="datetime-local" className="form-control" value={form.ends_at} onChange={set('ends_at')} />
          </div>
          <div className="col-md-3">
            <label className="form-label">Capacidade total</label>
            <input type="number" className="form-control" value={form.total_capacity} onChange={set('total_capacity')} />
            <small className="form-hint">Vendidos: {event.ticketsSold}</small>
          </div>
          <div className="col-md-3">
            <label className="form-label">Reserva (min)</label>
            <input type="number" className="form-control" value={form.reservation_ttl_minutes} onChange={set('reservation_ttl_minutes')} />
          </div>
          <div className="col-md-3">
            <label className="form-label">Vendas: início</label>
            <input type="datetime-local" className="form-control" value={form.sales_start_at} onChange={set('sales_start_at')} />
          </div>
          <div className="col-md-3">
            <label className="form-label">Vendas: fim</label>
            <input type="datetime-local" className="form-control" value={form.sales_end_at} onChange={set('sales_end_at')} />
          </div>
        </div>
      </Card>

      <Card title="Comportamento">
        <div className="row">
          {FLAGS.map(([key, label]) => {
            const snake = key.replace(/[A-Z]/g, (c) => '_'+c.toLowerCase())
            return (
              <div className="col-md-4" key={key}>
                <label className="form-check">
                  <input type="checkbox" className="form-check-input"
                    checked={!!form[snake]} onChange={setFlag(snake)} />
                  <span className="form-check-label">{label}</span>
                </label>
              </div>
            )
          })}
        </div>
        {form.allow_courtesy && (
          <div className="row g-3 mt-1">
            <div className="col-md-4">
              <label className="form-label">A cada X ingressos pagos…</label>
              <input type="number" className="form-control" value={form.courtesy_paid_threshold} onChange={set('courtesy_paid_threshold')} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Limite por conta</label>
              <input type="number" className="form-control" value={form.courtesy_limit_per_account} onChange={set('courtesy_limit_per_account')} />
            </div>
          </div>
        )}
      </Card>

      <Card title="Banner">
        {event.bannerUrl && (
          <img src={event.bannerUrl} alt="Banner do evento" className="img-fluid rounded mb-2" style={{ maxHeight: 180 }} />
        )}
        <input type="file" className="form-control" accept="image/jpeg,image/png,image/webp" onChange={enviarBanner} />
        <small className="form-hint">JPEG, PNG ou WebP até 5 MB.</small>
      </Card>

      {event.status !== 'cancelled' && (
        <Card title="Cancelar evento">
          <div className="row g-2">
            <div className="col-md-8">
              <input className="form-control" placeholder="Motivo do cancelamento"
                value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} />
            </div>
            <div className="col-md-4">
              <button className="btn btn-danger" onClick={cancelar} disabled={busy}>
                Cancelar evento
              </button>
            </div>
          </div>
          <small className="form-hint">O histórico é preservado; a ação fica registrada.</small>
        </Card>
      )}
    </>
  )
}
