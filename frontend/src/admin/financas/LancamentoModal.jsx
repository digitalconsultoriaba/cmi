import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut } from '../../lib/api'
import { ApiErrorAlert, useApiAction } from '../components'
import { parseMoney } from '../../lib/money'

export default function LancamentoModal({ direction, eventId, entry, onClose, onSaved }) {
  const { run, error, setError, busy } = useApiAction()
  const editing = !!entry
  const [form, setForm] = useState({
    description: entry?.description ?? '', amount: entry?.amount ?? '',
    category_id: entry?.categoryId ?? '', person_id: entry?.personId ?? '',
    payment_method_id: entry?.paymentMethodId ?? '', event_id: entry?.event?.id ?? eventId ?? '',
    due_date: entry?.dueDate ?? new Date().toISOString().slice(0, 10), notes: entry?.notes ?? '',
  })
  const [mode, setMode] = useState('single') // single | installments
  const [installments, setInstallments] = useState(2)
  const [firstDue, setFirstDue] = useState('')

  const set = (f) => (e) => setForm({ ...form, [f]: e.target.value })
  const catDir = direction === 'receivable' ? 'income' : 'expense'

  const { data: categories = [] } = useQuery({ queryKey: ['finance', 'categories', catDir], queryFn: () => apiGet(`/finance/categories?direction=${catDir}`) })
  const { data: people = [] } = useQuery({ queryKey: ['finance', 'people'], queryFn: () => apiGet('/finance/people') })
  const { data: methods = [] } = useQuery({ queryKey: ['finance', 'methods'], queryFn: () => apiGet('/finance/payment-methods') })
  const { data: events = [] } = useQuery({ queryKey: ['admin', 'events'], queryFn: () => apiGet('/admin/events') })

  const salvar = () => {
    if (editing) {
      const payload = {
        description: form.description, amount: parseMoney(form.amount) ?? form.amount,
        category_id: form.category_id || null, person_id: form.person_id || null,
        payment_method_id: form.payment_method_id || null, event_id: form.event_id || null,
        due_date: form.due_date, notes: form.notes || null,
      }
      run(() => apiPut(`/finance/entries/${entry.id}`, payload), { onSuccess: onSaved })
      return
    }
    const payload = {
      direction, description: form.description,
      amount: parseMoney(form.amount) ?? form.amount,
      category_id: form.category_id || null, person_id: form.person_id || null,
      payment_method_id: form.payment_method_id || null, event_id: form.event_id || null,
      due_date: form.due_date, notes: form.notes || null,
      origin: form.event_id ? (direction === 'receivable' ? 'other' : 'event_expense') : 'manual',
    }
    if (mode === 'installments') {
      payload.installments = Number(installments)
      payload.first_due_date = firstDue || form.due_date
    }
    run(() => apiPost('/finance/entries', payload), { onSuccess: onSaved })
  }

  const isReceivable = direction === 'receivable'

  return (
    <div className="modal modal-blur fade show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,.4)' }}>
      <div className="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{editing ? 'Editar' : 'Nova'} conta {isReceivable ? 'a receber' : 'a pagar'}</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <ApiErrorAlert error={error} onClose={() => setError(null)} />
            <div className="row g-3">
              <div className="col-12"><label className="form-label required">Descrição</label>
                <input className="form-control" value={form.description} onChange={set('description')} /></div>
              <div className="col-md-4"><label className="form-label required">Valor</label>
                <div className="input-group"><span className="input-group-text">R$</span>
                  <input className="form-control" placeholder="0,00" value={form.amount} onChange={set('amount')} /></div></div>
              <div className="col-md-4"><label className="form-label required">Vencimento</label>
                <input type="date" className="form-control" value={form.due_date} onChange={set('due_date')} /></div>
              <div className="col-md-4"><label className="form-label">Categoria</label>
                <select className="form-select" value={form.category_id} onChange={set('category_id')}>
                  <option value="">—</option>
                  {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select></div>
              <div className="col-md-6"><label className="form-label">Evento (opcional)</label>
                <select className="form-select" value={form.event_id} onChange={set('event_id')} disabled={!!eventId}>
                  <option value="">Administrativo / Geral</option>
                  {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.name}</option>)}
                </select></div>
              <div className="col-md-3"><label className="form-label">{isReceivable ? 'Cliente' : 'Fornecedor'}</label>
                <select className="form-select" value={form.person_id} onChange={set('person_id')}>
                  <option value="">—</option>
                  {people.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select></div>
              <div className="col-md-3"><label className="form-label">Forma</label>
                <select className="form-select" value={form.payment_method_id} onChange={set('payment_method_id')}>
                  <option value="">—</option>
                  {methods.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                </select></div>

              {!editing && (
              <div className="col-12">
                <div className="btn-group w-100">
                  <button type="button" className={`btn ${mode === 'single' ? 'btn-primary' : 'btn-outline-primary'}`}
                    onClick={() => setMode('single')}>À vista</button>
                  <button type="button" className={`btn ${mode === 'installments' ? 'btn-primary' : 'btn-outline-primary'}`}
                    onClick={() => setMode('installments')}>Parcelado</button>
                </div>
              </div>
              )}
              {!editing && mode === 'installments' && (
                <>
                  <div className="col-md-4"><label className="form-label">Parcelas</label>
                    <input type="number" min={2} max={36} className="form-control" value={installments}
                      onChange={(e) => setInstallments(e.target.value)} /></div>
                  <div className="col-md-4"><label className="form-label">1º vencimento (+30 em 30)</label>
                    <input type="date" className="form-control" value={firstDue} onChange={(e) => setFirstDue(e.target.value)} /></div>
                </>
              )}
              <div className="col-12"><label className="form-label">Observações</label>
                <textarea className="form-control" rows={2} value={form.notes} onChange={set('notes')} /></div>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn" onClick={onClose}>Cancelar</button>
            <button className="btn btn-primary" disabled={busy || !form.description.trim() || !form.amount} onClick={salvar}>Salvar</button>
          </div>
        </div>
      </div>
    </div>
  )
}
