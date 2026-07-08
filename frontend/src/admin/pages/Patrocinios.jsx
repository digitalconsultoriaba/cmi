import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { ApiErrorAlert, useApiAction } from '../components'
import { apiGet, apiPost, apiPut } from '../../lib/api'
import { parseMoney, formatMoney } from '../../lib/money'

const STATUS_LABELS = { pending: 'Pendente', partial: 'Parcial', paid: 'Pago', cancelled: 'Cancelado' }
const STATUS_BADGE = {
  pending: 'bg-warning text-dark', partial: 'bg-primary text-white',
  paid: 'bg-success text-white', cancelled: 'bg-danger text-white',
}
const dt = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—')

export default function Patrocinios() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [modal, setModal] = useState(null) // null | { } (novo) | sponsorship (editar)
  const [paying, setPaying] = useState(null) // { sponsorship, installment } em baixa

  const eventId = event?.id
  const { data: sponsorships = [] } = useQuery({
    queryKey: ['admin', eventId, 'sponsorships'],
    queryFn: () => apiGet(`/admin/events/${eventId}/sponsorships`),
    enabled: !!eventId,
  })

  if (!event) return <p className="text-secondary">Carregando…</p>

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'sponsorships'] })
    // Receber/cancelar patrocínio espelha no contas a receber e no painel do
    // evento — invalida essas queries para a baixa aparecer sem recarregar.
    queryClient.invalidateQueries({ queryKey: ['finance'] })
    queryClient.invalidateQueries({ queryKey: ['admin', 'event', String(eventId)] })
  }

  const pagar = (payload) => run(
    () => apiPost(`/admin/events/${eventId}/sponsorships/${paying.sponsorship.id}/installments/${paying.installment.number}/pay`, payload),
    { onSuccess: () => { setPaying(null); refresh() } }
  )
  const cancelar = (s) => run(
    () => apiPost(`/admin/events/${eventId}/sponsorships/${s.id}/cancel`),
    { onSuccess: refresh }
  )

  return (
    <>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">Patrocínios ({sponsorships.length})</h3>
          <div className="card-actions">
            <button className="btn btn-primary" onClick={() => setModal({})}>+ Novo patrocínio</button>
          </div>
        </div>
        <div className="card-body">
          {sponsorships.length === 0 && <p className="text-secondary mb-0">Nenhum patrocinador cadastrado.</p>}

          {sponsorships.map((s) => {
            const paidCount = s.installments.filter((i) => i.status === 'paid').length
            return (
              <div className="card mb-3" key={s.id}>
                <div className="card-body d-flex justify-content-between align-items-start">
                  <div>
                    <div className="d-flex align-items-center gap-2">
                      <strong className="fs-3">{s.companyName}</strong>
                      <span className={`badge ${STATUS_BADGE[s.status]}`}>{STATUS_LABELS[s.status]}</span>
                    </div>
                    <div className="text-secondary">
                      {formatMoney(s.totalAmount)} · {s.installmentsCount}× · {s.paymentMethod ?? '—'}
                      {' · '}{paidCount}/{s.installments.length} parcelas pagas
                      {s.contact && <> · {s.contact}</>}
                    </div>
                    {s.notes && <div className="text-secondary small mt-1">{s.notes}</div>}
                  </div>
                  <span className="btn-list">
                    <button className="btn btn-icon btn-sm" title="Editar" onClick={() => setModal(s)}>✎</button>
                    {s.status !== 'cancelled' && (
                      <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => cancelar(s)}>Cancelar</button>
                    )}
                  </span>
                </div>
                <div className="card-table table-responsive">
                  <table className="table table-vcenter mb-0">
                    <thead><tr><th>Parcela</th><th>Valor</th><th>Vencimento</th><th>Situação</th><th>Recebimento</th><th /></tr></thead>
                    <tbody>
                      {s.installments.map((i) => (
                        <tr key={i.id}>
                          <td>{i.number}</td>
                          <td>{formatMoney(i.amount)}</td>
                          <td>{dt(i.dueDate)}</td>
                          <td><span className={`badge ${i.status === 'paid' ? 'bg-success text-white' : 'bg-warning text-dark'}`}>
                            {i.status === 'paid' ? 'Paga' : 'Pendente'}</span></td>
                          <td className="small text-secondary">
                            {i.paidAt ? `${i.method ?? ''} · ${dt(i.paidAt)}` : '—'}
                          </td>
                          <td className="text-end">
                            {i.status === 'pending' && s.status !== 'cancelled' && (
                              <button className="btn btn-sm btn-success" onClick={() => setPaying({ sponsorship: s, installment: i })}>
                                Registrar baixa
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )
          })}
        </div>
      </div>

      {modal && (
        <PatrocinioModal eventId={eventId} sponsorship={modal.id ? modal : null}
          onClose={() => setModal(null)}
          onSaved={() => { setModal(null); refresh() }} />
      )}

      {paying && (
        <BaixaModal paying={paying} busy={busy}
          onClose={() => setPaying(null)} onConfirm={pagar} />
      )}
    </>
  )
}

function BaixaModal({ paying, busy, onClose, onConfirm }) {
  const { installment, sponsorship } = paying
  const [method, setMethod] = useState('PIX')
  const [paidAt, setPaidAt] = useState(new Date().toISOString().slice(0, 10))
  const [note, setNote] = useState('')

  return (
    <div className="modal modal-blur fade show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,.4)' }}>
      <div className="modal-dialog modal-sm" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Registrar baixa — parcela {installment.number}</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <p className="text-secondary mb-3">
              {sponsorship.companyName} · {formatMoney(installment.amount)}
            </p>
            <div className="mb-3">
              <label className="form-label required">Forma de pagamento</label>
              <select className="form-select" value={method} onChange={(e) => setMethod(e.target.value)}>
                <option value="PIX">PIX</option>
                <option value="Boleto">Boleto</option>
                <option value="Transferência">Transferência</option>
                <option value="Dinheiro">Dinheiro</option>
                <option value="Cartão">Cartão</option>
              </select>
            </div>
            <div className="mb-3">
              <label className="form-label">Data do recebimento</label>
              <input type="date" className="form-control" value={paidAt} onChange={(e) => setPaidAt(e.target.value)} />
            </div>
            <div className="mb-1">
              <label className="form-label">Observação (opcional)</label>
              <input className="form-control" value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn" onClick={onClose}>Cancelar</button>
            <button className="btn btn-success" disabled={busy}
              onClick={() => onConfirm({ method, paid_at: paidAt || null, note: note || null })}>
              Confirmar baixa
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function PatrocinioModal({ eventId, sponsorship, onClose, onSaved }) {
  const isEdit = !!sponsorship
  const { run, error, setError, busy } = useApiAction()
  const [form, setForm] = useState({
    company_name: sponsorship?.companyName ?? '',
    contact: sponsorship?.contact ?? '',
    total_amount: sponsorship ? formatMoney(sponsorship.totalAmount).replace('R$', '').trim() : '',
    installments_count: sponsorship?.installmentsCount ?? 1,
    payment_method: sponsorship?.paymentMethod ?? '',
    notes: sponsorship?.notes ?? '',
  })
  const [mode, setMode] = useState('auto') // auto | custom
  const [firstDue, setFirstDue] = useState('')
  const [dueDates, setDueDates] = useState([])

  const set = (f) => (e) => setForm({ ...form, [f]: e.target.value })
  const count = Math.max(1, Number(form.installments_count) || 1)

  const salvar = () => {
    const payload = {
      company_name: form.company_name,
      contact: form.contact || null,
      payment_method: form.payment_method || null,
      notes: form.notes || null,
    }
    if (isEdit) {
      run(() => apiPut(`/admin/events/${eventId}/sponsorships/${sponsorship.id}`, payload), { onSuccess: onSaved })
      return
    }
    payload.total_amount = parseMoney(form.total_amount) ?? form.total_amount
    payload.installments_count = count
    if (mode === 'auto') payload.first_due_date = firstDue || null
    else payload.due_dates = Array.from({ length: count }, (_, i) => dueDates[i] || null)
    run(() => apiPost(`/admin/events/${eventId}/sponsorships`, payload), { onSuccess: onSaved })
  }

  return (
    <div className="modal modal-blur fade show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,.4)' }}>
      <div className="modal-dialog modal-dialog-scrollable" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{isEdit ? 'Editar patrocínio' : 'Novo patrocínio'}</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <ApiErrorAlert error={error} onClose={() => setError(null)} />

            <div className="mb-3">
              <label className="form-label required">Empresa</label>
              <input className="form-control" value={form.company_name} onChange={set('company_name')} />
            </div>
            <div className="mb-3">
              <label className="form-label">Contato</label>
              <input className="form-control" placeholder="Responsável, telefone, e-mail…"
                value={form.contact} onChange={set('contact')} />
            </div>

            {!isEdit && (
              <div className="row">
                <div className="col-8 mb-3">
                  <label className="form-label required">Valor total</label>
                  <div className="input-group">
                    <span className="input-group-text">R$</span>
                    <input className="form-control" placeholder="0,00" value={form.total_amount} onChange={set('total_amount')} />
                  </div>
                </div>
                <div className="col-4 mb-3">
                  <label className="form-label">Parcelas</label>
                  <input type="number" min={1} max={36} className="form-control"
                    value={form.installments_count} onChange={set('installments_count')} />
                </div>
              </div>
            )}

            <div className="mb-3">
              <label className="form-label">Forma de pagamento</label>
              <select className="form-select" value={form.payment_method} onChange={set('payment_method')}>
                <option value="">Selecione…</option>
                <option value="Boleto">Boleto</option>
                <option value="PIX">PIX</option>
                <option value="Transferência">Transferência</option>
                <option value="Dinheiro">Dinheiro</option>
                <option value="Cartão">Cartão</option>
              </select>
            </div>

            {!isEdit && (
              <>
                <label className="form-label">Vencimentos</label>
                <div className="btn-group w-100 mb-3">
                  <button type="button" className={`btn ${mode === 'auto' ? 'btn-primary' : 'btn-outline-primary'}`}
                    onClick={() => setMode('auto')}>1ª + 30 em 30 dias</button>
                  <button type="button" className={`btn ${mode === 'custom' ? 'btn-primary' : 'btn-outline-primary'}`}
                    onClick={() => setMode('custom')}>Personalizado</button>
                </div>

                {mode === 'auto' ? (
                  <div className="mb-3">
                    <label className="form-label">Vencimento da 1ª parcela</label>
                    <input type="date" className="form-control" value={firstDue} onChange={(e) => setFirstDue(e.target.value)} />
                  </div>
                ) : (
                  <div className="mb-3">
                    <label className="form-label">Vencimento de cada parcela</label>
                    {Array.from({ length: count }, (_, i) => (
                      <div className="d-flex align-items-center gap-2 mb-1" key={i}>
                        <span className="text-secondary" style={{ width: 80 }}>Parcela {i + 1}</span>
                        <input type="date" className="form-control" value={dueDates[i] ?? ''}
                          onChange={(e) => { const d = [...dueDates]; d[i] = e.target.value; setDueDates(d) }} />
                      </div>
                    ))}
                  </div>
                )}
              </>
            )}

            <div className="mb-1">
              <label className="form-label">Observações</label>
              <textarea className="form-control" rows={2} value={form.notes} onChange={set('notes')} />
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn" onClick={onClose}>Cancelar</button>
            <button className="btn btn-primary" disabled={busy || !form.company_name.trim()} onClick={salvar}>Salvar</button>
          </div>
        </div>
      </div>
    </div>
  )
}
