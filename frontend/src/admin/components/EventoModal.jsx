import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ApiErrorAlert, useApiAction } from '../components'
import { apiGet, apiPost, apiPut } from '../../lib/api'

const FLAGS = [
  ['allow_pix', 'Aceita Pix'],
  ['allow_boleto', 'Aceita boleto'],
  ['allow_card', 'Aceita cartão'],
  ['allow_shirt_choice', 'Escolha de camisa'],
  ['requires_shirt', 'Camisa obrigatória'],
  ['allow_kit', 'Retirada de kit'],
  ['allow_transfer', 'Transferência de ingresso'],
  ['allow_user_cancel', 'Cancelamento pelo usuário'],
  ['allow_refund_request', 'Solicitação de reembolso'],
  ['allow_courtesy', 'Cortesia / gratuidade'],
]

const toInput = (iso) => (iso ? iso.slice(0, 16) : '')
const snake = (obj) => obj

/** Modal criar/editar evento (spec 009, imagens 12–13). */
export default function EventoModal({ event, onClose, onSaved }) {
  const isEdit = !!event
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [newType, setNewType] = useState(null) // nome do novo tipo (null = fechado)

  const { data: types = [] } = useQuery({
    queryKey: ['admin', 'event-types'],
    queryFn: () => apiGet('/admin/event-types'),
  })

  const salvarTipo = () => run(async () => {
    const created = await apiPost('/admin/event-types', { name: newType })
    await queryClient.invalidateQueries({ queryKey: ['admin', 'event-types'] })
    setForm((f) => ({ ...f, event_type_id: created.id }))
    setNewType(null)
  })

  const [form, setForm] = useState(() => ({
    name: event?.name ?? '',
    event_type_id: event?.eventTypeId ?? '',
    description: event?.description ?? '',
    starts_at: toInput(event?.startsAt),
    ends_at: toInput(event?.endsAt),
    location: event?.location ?? '',
    total_capacity: event?.totalCapacity ?? '',
    sales_start_at: toInput(event?.salesStartAt),
    sales_end_at: toInput(event?.salesEndAt),
    participation_rules: event?.participationRules ?? '',
    internal_notes: event?.internalNotes ?? '',
    pricing_mode: event?.pricingMode ?? 'paid',
    courtesy_paid_threshold: event?.courtesyPaidThreshold ?? '',
    courtesy_grant_per_threshold: event?.courtesyGrantPerThreshold ?? 1,
    courtesy_limit_per_account: event?.courtesyLimitPerAccount ?? '',
    ...Object.fromEntries(FLAGS.map(([k]) => [k, event ? !!event[k.replace(/_([a-z])/g, (_, c) => c.toUpperCase())] : k !== 'requires_shirt' && k !== 'allow_kit'])),
  }))

  const set = (f) => (e) => setForm({ ...form, [f]: e.target.value })
  const setFlag = (f) => (e) => setForm({ ...form, [f]: e.target.checked })

  const salvar = () => run(async () => {
    const payload = snake({ ...form })
    for (const k of ['total_capacity', 'courtesy_paid_threshold', 'courtesy_limit_per_account', 'courtesy_grant_per_threshold']) {
      payload[k] = payload[k] === '' ? null : Number(payload[k])
    }
    for (const k of ['ends_at', 'sales_start_at', 'sales_end_at']) {
      if (payload[k] === '') payload[k] = null
    }
    if (isEdit) await apiPut(`/admin/events/${event.id}`, payload)
    else await apiPost('/admin/events', payload)
  }, { onSuccess: onSaved })

  return (
    <div className="modal modal-blur fade show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,.4)' }}>
      <div className="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{isEdit ? 'Editar evento' : 'Novo evento'}</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <ApiErrorAlert error={error} onClose={() => setError(null)} />

            <div className="row g-3">
              <div className="col-md-8">
                <label className="form-label required">Nome</label>
                <input className="form-control" value={form.name} onChange={set('name')} />
              </div>
              <div className="col-md-4">
                <label className="form-label required">Tipo</label>
                {newType === null ? (
                  <div className="input-group">
                    <select className="form-select" value={form.event_type_id} onChange={set('event_type_id')}>
                      <option value="">Selecione…</option>
                      {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                    <button type="button" className="btn" title="Cadastrar novo tipo" onClick={() => setNewType('')}>+ Novo</button>
                  </div>
                ) : (
                  <div className="input-group">
                    <input className="form-control" autoFocus placeholder="Nome do novo tipo"
                      value={newType} onChange={(e) => setNewType(e.target.value)} />
                    <button type="button" className="btn btn-primary" disabled={busy || !newType.trim()} onClick={salvarTipo}>Salvar</button>
                    <button type="button" className="btn" onClick={() => setNewType(null)}>×</button>
                  </div>
                )}
              </div>
              <div className="col-12">
                <label className="form-label">Descrição</label>
                <textarea className="form-control" rows={2} value={form.description} onChange={set('description')} />
              </div>
              <div className="col-md-6">
                <label className="form-label required">Data/hora</label>
                <input type="datetime-local" className="form-control" value={form.starts_at} onChange={set('starts_at')} />
              </div>
              <div className="col-md-6">
                <label className="form-label">Local</label>
                <input className="form-control" value={form.location} onChange={set('location')} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Capacidade total</label>
                <input type="number" className="form-control" value={form.total_capacity} onChange={set('total_capacity')} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Início das vendas</label>
                <input type="datetime-local" className="form-control" value={form.sales_start_at} onChange={set('sales_start_at')} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Fim das vendas</label>
                <input type="datetime-local" className="form-control" value={form.sales_end_at} onChange={set('sales_end_at')} />
              </div>
              <div className="col-12">
                <label className="form-label">Observações / Regras de participação</label>
                <textarea className="form-control" rows={2} value={form.participation_rules} onChange={set('participation_rules')} />
              </div>

              <div className="col-md-4">
                <label className="form-label">Modo de preço</label>
                <select className="form-select" value={form.pricing_mode} onChange={set('pricing_mode')}>
                  <option value="paid">Pago</option>
                  <option value="free">Gratuito</option>
                  <option value="mixed">Misto</option>
                </select>
              </div>

              <div className="col-12">
                <div className="form-label">Regras do evento</div>
                <div className="row">
                  {FLAGS.map(([key, label]) => (
                    <div className="col-md-4" key={key}>
                      <label className="form-check form-switch">
                        <input type="checkbox" className="form-check-input"
                          checked={!!form[key]} onChange={setFlag(key)} />
                        <span className="form-check-label">{label}</span>
                      </label>
                    </div>
                  ))}
                </div>
              </div>

              {form.allow_courtesy && (
                <>
                  <div className="col-12">
                    <div className="form-label mb-0">Gratuidade
                      <span className="text-secondary small ms-1">a cada X inscrições pagas → Y gratuita(s)</span>
                    </div>
                  </div>
                  <div className="col-md-3">
                    <label className="form-label">X (pagas)</label>
                    <input type="number" className="form-control" value={form.courtesy_paid_threshold} onChange={set('courtesy_paid_threshold')} />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label">Y (grátis)</label>
                    <input type="number" className="form-control" value={form.courtesy_grant_per_threshold} onChange={set('courtesy_grant_per_threshold')} />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label">Limite/Conta</label>
                    <input type="number" className="form-control" value={form.courtesy_limit_per_account} onChange={set('courtesy_limit_per_account')} />
                  </div>
                </>
              )}

              <div className="col-12">
                <label className="form-label">Observações internas</label>
                <textarea className="form-control" rows={2} value={form.internal_notes} onChange={set('internal_notes')} />
              </div>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn" onClick={onClose}>Cancelar</button>
            <button className="btn btn-primary" disabled={busy} onClick={salvar}>Salvar</button>
          </div>
        </div>
      </div>
    </div>
  )
}
