import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { Card, ApiErrorAlert, StatusBadge, Modal, useApiAction } from '../components'
import { apiGet, apiPost, apiPut, apiDelete, apiPatch } from '../../lib/api'
import { parseMoney, formatMoney } from '../../lib/money'

function TypeForm({ initial, onSubmit, onCancel, busy }) {
  const [form, setForm] = useState(initial ?? {
    name: '', price: '', capacity: '', is_couple: false, includes_shirt: false, is_courtesy: false,
  })

  const submit = () => onSubmit({
    ...form,
    price: parseMoney(form.price) ?? form.price,
    capacity: form.capacity === '' ? null : Number(form.capacity),
    seats_per_ticket: form.is_couple ? 2 : 1,
  })

  return (
    <Modal title={initial ? 'Editar tipo de ingresso' : 'Novo tipo de ingresso'} size="md" onClose={onCancel}
      footer={
        <>
          <button className="btn" onClick={onCancel}>Cancelar</button>
          <button className="btn btn-primary" disabled={busy || !form.name.trim()} onClick={submit}>Salvar</button>
        </>
      }>
      <div className="row g-3">
        <div className="col-md-7">
          <label className="form-label required">Nome</label>
          <input className="form-control" autoFocus value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </div>
        <div className="col-md-5">
          <label className="form-label">Preço</label>
          <input className="form-control" placeholder="250,00" value={form.price}
            onChange={(e) => setForm({ ...form, price: e.target.value })} />
        </div>
        <div className="col-md-5">
          <label className="form-label">Capacidade</label>
          <input type="number" className="form-control" placeholder="ilimitada" value={form.capacity ?? ''}
            onChange={(e) => setForm({ ...form, capacity: e.target.value })} />
        </div>
        <div className="col-12">
          <div className="form-label">Opções</div>
          {[['is_couple', 'Casal (2 lugares)'], ['includes_shirt', 'Inclui camisa'], ['is_courtesy', 'Cortesia']].map(([key, label]) => (
            <label className="form-check form-switch" key={key}>
              <input type="checkbox" className="form-check-input" checked={!!form[key]}
                onChange={(e) => setForm({ ...form, [key]: e.target.checked })} />
              <span className="form-check-label">{label}</span>
            </label>
          ))}
        </div>
      </div>
    </Modal>
  )
}

function LotForm({ types, initial, onSubmit, onCancel, busy }) {
  const [form, setForm] = useState(initial ?? { name: '', price_override: '', starts_at: '', ends_at: '', quantity: '', ticket_type_id: '' })

  const submit = () => onSubmit({
    name: form.name,
    price_override: form.price_override ? parseMoney(form.price_override) : null,
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
    quantity: form.quantity === '' ? null : Number(form.quantity),
    ticket_type_id: form.ticket_type_id === '' ? null : Number(form.ticket_type_id),
  })

  return (
    <Modal title={initial ? 'Editar lote' : 'Novo lote'} size="md" onClose={onCancel}
      footer={
        <>
          <button className="btn" onClick={onCancel}>Cancelar</button>
          <button className="btn btn-primary" disabled={busy || !form.name.trim()} onClick={submit}>Salvar</button>
        </>
      }>
      <div className="row g-3">
        <div className="col-md-7">
          <label className="form-label required">Nome</label>
          <input className="form-control" autoFocus value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </div>
        <div className="col-md-5">
          <label className="form-label">Preço promocional</label>
          <input className="form-control" placeholder="opcional" value={form.price_override}
            onChange={(e) => setForm({ ...form, price_override: e.target.value })} />
        </div>
        <div className="col-md-6">
          <label className="form-label">Início</label>
          <input type="datetime-local" className="form-control" value={form.starts_at}
            onChange={(e) => setForm({ ...form, starts_at: e.target.value })} />
        </div>
        <div className="col-md-6">
          <label className="form-label">Fim</label>
          <input type="datetime-local" className="form-control" value={form.ends_at}
            onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
        </div>
        <div className="col-md-6">
          <label className="form-label">Quantidade</label>
          <input type="number" className="form-control" placeholder="ilimitada" value={form.quantity}
            onChange={(e) => setForm({ ...form, quantity: e.target.value })} />
        </div>
        <div className="col-md-6">
          <label className="form-label">Escopo</label>
          <select className="form-select" value={form.ticket_type_id}
            onChange={(e) => setForm({ ...form, ticket_type_id: e.target.value })}>
            <option value="">Evento todo</option>
            {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
          </select>
        </div>
      </div>
    </Modal>
  )
}

export default function TiposLotes() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [showTypeForm, setShowTypeForm] = useState(false)
  const [showLotForm, setShowLotForm] = useState(false)
  const [editingType, setEditingType] = useState(null)
  const [editingLot, setEditingLot] = useState(null)

  const eventId = event?.id
  const { data: types = [] } = useQuery({
    queryKey: ['admin', eventId, 'ticket-types'],
    queryFn: () => apiGet(`/admin/events/${eventId}/ticket-types`),
    enabled: !!eventId,
  })
  const { data: lots = [] } = useQuery({
    queryKey: ['admin', eventId, 'lots'],
    queryFn: () => apiGet(`/admin/events/${eventId}/lots`),
    enabled: !!eventId,
  })

  if (!event) return <p>Carregando…</p>

  const refreshTypes = () => queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'ticket-types'] })
  const refreshLots = () => queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'lots'] })

  const toggleType = (type) => run(() => apiPut(`/admin/events/${eventId}/ticket-types/${type.id}`, {
    name: type.name, price: type.price, is_active: !type.isActive,
  }), { onSuccess: refreshTypes })

  const removeType = (type) => run(() => apiDelete(`/admin/events/${eventId}/ticket-types/${type.id}`), { onSuccess: refreshTypes })

  const salvarType = (payload) => {
    const url = editingType
      ? `/admin/events/${eventId}/ticket-types/${editingType.id}`
      : `/admin/events/${eventId}/ticket-types`
    const call = editingType ? apiPut : apiPost
    return run(() => call(url, payload), {
      onSuccess: () => { refreshTypes(); setShowTypeForm(false); setEditingType(null) },
    })
  }

  const editarType = (type) => setEditingType({
    id: type.id, name: type.name, price: type.price, capacity: type.capacity ?? '',
    is_couple: type.isCouple, includes_shirt: type.includesShirt, is_courtesy: type.isCourtesy,
  })

  const salvarLot = (payload) => {
    const url = editingLot
      ? `/admin/events/${eventId}/lots/${editingLot.id}`
      : `/admin/events/${eventId}/lots`
    const call = editingLot ? apiPut : apiPost
    return run(() => call(url, payload), {
      onSuccess: () => { refreshLots(); setShowLotForm(false); setEditingLot(null) },
    })
  }

  const editarLot = (lot) => setEditingLot({
    id: lot.id, name: lot.name, price_override: lot.priceOverride ?? '',
    starts_at: lot.startsAt ? lot.startsAt.slice(0, 16) : '',
    ends_at: lot.endsAt ? lot.endsAt.slice(0, 16) : '',
    quantity: lot.quantity ?? '', ticket_type_id: lot.ticketTypeId ?? '',
  })

  const moveType = (index, delta) => {
    const ids = types.map((t) => t.id)
    const [moved] = ids.splice(index, 1)
    ids.splice(index + delta, 0, moved)
    return run(() => apiPatch(`/admin/events/${eventId}/ticket-types/reorder`, { ids }), { onSuccess: refreshTypes })
  }

  const toggleLot = (lot) => run(() => apiPut(`/admin/events/${eventId}/lots/${lot.id}`, {
    name: lot.name, is_active: !lot.isActive,
  }), { onSuccess: refreshLots })

  const removeLot = (lot) => run(() => apiDelete(`/admin/events/${eventId}/lots/${lot.id}`), { onSuccess: refreshLots })

  return (
    <>
      <h2>Tipos & Lotes</h2>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <Card title="Tipos de ingresso"
        actions={<button className="btn btn-primary btn-sm" onClick={() => setShowTypeForm(!showTypeForm)}>Novo tipo</button>}>
        {(showTypeForm || editingType) && (
          <TypeForm key={editingType?.id ?? 'new'} initial={editingType} busy={busy}
            onSubmit={salvarType}
            onCancel={() => { setShowTypeForm(false); setEditingType(null) }} />
        )}
        <table className="table table-vcenter">
          <thead><tr>
            <th>Nome</th><th>Preço</th><th>Vendidos</th><th>Situação</th><th>Ordem</th><th /></tr></thead>
          <tbody>
            {types.map((type, index) => (
              <tr key={type.id}>
                <td>{type.name} {type.isCouple && <span className="badge bg-blue-lt">casal</span>}</td>
                <td>{formatMoney(type.price)}</td>
                <td>{type.soldCount}{type.capacity !== null && ` / ${type.capacity}`}</td>
                <td>
                  <StatusBadge ok={type.isActive} okLabel="ativo" badLabel="inativo" />{' '}
                  {type.soldOut && <span className="badge bg-orange-lt">esgotado</span>}
                </td>
                <td>
                  <button className="btn btn-sm" disabled={index === 0 || busy} onClick={() => moveType(index, -1)}>↑</button>
                  <button className="btn btn-sm" disabled={index === types.length - 1 || busy} onClick={() => moveType(index, 1)}>↓</button>
                </td>
                <td className="text-end">
                  <button className="btn btn-sm" onClick={() => editarType(type)}>Editar</button>{' '}
                  <button className="btn btn-sm" onClick={() => toggleType(type)}>{type.isActive ? 'Desativar' : 'Ativar'}</button>{' '}
                  <button className="btn btn-sm btn-outline-danger" onClick={() => removeType(type)}>Excluir</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Card title="Lotes"
        actions={<button className="btn btn-primary btn-sm" onClick={() => setShowLotForm(!showLotForm)}>Novo lote</button>}>
        {(showLotForm || editingLot) && (
          <LotForm key={editingLot?.id ?? 'new'} types={types} initial={editingLot} busy={busy}
            onSubmit={salvarLot}
            onCancel={() => { setShowLotForm(false); setEditingLot(null) }} />
        )}
        <table className="table table-vcenter">
          <thead><tr>
            <th>Nome</th><th>Escopo</th><th>Janela</th><th>Preço efetivo</th><th>Vendidos</th><th>Situação</th><th /></tr></thead>
          <tbody>
            {lots.map((lot) => (
              <tr key={lot.id}>
                <td>{lot.name}</td>
                <td>{lot.ticketTypeName ?? 'Evento todo'}</td>
                <td>
                  {lot.startsAt ? new Date(lot.startsAt).toLocaleDateString('pt-BR') : '—'}
                  {' → '}
                  {lot.endsAt ? new Date(lot.endsAt).toLocaleDateString('pt-BR') : '—'}
                </td>
                <td>{lot.effectivePrice ? formatMoney(lot.effectivePrice) : 'preço do tipo'}</td>
                <td>{lot.soldCount}{lot.quantity !== null && ` / ${lot.quantity}`}</td>
                <td>
                  {lot.isCurrent && <span className="badge bg-green-lt">vigente</span>}{' '}
                  {lot.soldOut && <span className="badge bg-orange-lt">esgotado</span>}{' '}
                  <StatusBadge ok={lot.isActive} okLabel="ativo" badLabel="inativo" />
                </td>
                <td className="text-end">
                  <button className="btn btn-sm" onClick={() => editarLot(lot)}>Editar</button>{' '}
                  <button className="btn btn-sm" onClick={() => toggleLot(lot)}>{lot.isActive ? 'Desativar' : 'Ativar'}</button>{' '}
                  <button className="btn btn-sm btn-outline-danger" onClick={() => removeLot(lot)}>Excluir</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  )
}
