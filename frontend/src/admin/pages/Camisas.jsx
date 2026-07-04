import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { Card, ApiErrorAlert, StatusBadge, useApiAction } from '../components'
import { apiGet, apiPost, apiPut, apiDelete } from '../../lib/api'

export default function Camisas() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError } = useApiAction()
  const [newModel, setNewModel] = useState('')
  const [newSize, setNewSize] = useState({})

  const eventId = event?.id
  const { data: models = [] } = useQuery({
    queryKey: ['admin', eventId, 'shirt-models'],
    queryFn: () => apiGet(`/admin/events/${eventId}/shirt-models`),
    enabled: !!eventId,
  })

  if (!event) return <p>Carregando…</p>

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'shirt-models'] })

  const addModel = () => run(
    () => apiPost(`/admin/events/${eventId}/shirt-models`, { label: newModel }),
    { onSuccess: () => { refresh(); setNewModel('') } }
  )

  const addSize = (model) => {
    const draft = newSize[model.id] ?? { label: '', stock: '' }
    return run(() => apiPost(`/admin/events/${eventId}/shirt-models/${model.id}/sizes`, {
      label: draft.label,
      stock_quantity: draft.stock === '' ? null : Number(draft.stock),
    }), { onSuccess: () => { refresh(); setNewSize({ ...newSize, [model.id]: { label: '', stock: '' } }) } })
  }

  const updateStock = (model, size, stock) => run(
    () => apiPut(`/admin/events/${eventId}/shirt-models/${model.id}/sizes/${size.id}`, {
      label: size.label,
      stock_quantity: stock === '' ? null : Number(stock),
    }), { onSuccess: refresh }
  )

  const removeSize = (model, size) => run(
    () => apiDelete(`/admin/events/${eventId}/shirt-models/${model.id}/sizes/${size.id}`),
    { onSuccess: refresh }
  )

  const removeModel = (model) => run(
    () => apiDelete(`/admin/events/${eventId}/shirt-models/${model.id}`),
    { onSuccess: refresh }
  )

  return (
    <>
      <h2>Camisas</h2>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="row g-2 mb-3">
        <div className="col-md-4">
          <input className="form-control" placeholder="Novo modelo (ex.: Unissex)"
            value={newModel} onChange={(e) => setNewModel(e.target.value)} />
        </div>
        <div className="col-md-2">
          <button className="btn btn-primary" onClick={addModel} disabled={!newModel.trim()}>
            Adicionar modelo
          </button>
        </div>
      </div>

      {models.map((model) => {
        const stockTotal = model.sizes.reduce((s, z) => s + (z.stockQuantity ?? 0), 0)
        const soldTotal = model.sizes.reduce((s, z) => s + z.soldCount, 0)
        const hasUnlimited = model.sizes.some((z) => z.stockQuantity === null)
        return (
        <Card key={model.id} title={model.label}
          actions={
            <span className="btn-list">
              <a className="btn btn-sm" href={`/api/admin/events/${eventId}/reports/camisas.xlsx`}>Relatório</a>
              <button className="btn btn-sm btn-outline-danger" onClick={() => removeModel(model)}>Excluir modelo</button>
            </span>
          }>
          <div className="alert alert-secondary py-2">
            <strong>Estoque total:</strong> {hasUnlimited ? 'ilimitado' : stockTotal}
            {' · '}<strong>Vendidas:</strong> {soldTotal}
            {' · '}<strong>Disponível:</strong> {hasUnlimited ? 'ilimitado' : Math.max(0, stockTotal - soldTotal)}
          </div>
          <table className="table table-vcenter">
            <thead><tr><th>Tamanho</th><th>Estoque</th><th>Vendidas</th><th>Disponível</th><th /></tr></thead>
            <tbody>
              {model.sizes.map((size) => (
                <tr key={size.id}>
                  <td className="fw-bold">{size.label}</td>
                  <td style={{ maxWidth: 140 }}>
                    <input type="number" className="form-control form-control-sm"
                      defaultValue={size.stockQuantity ?? ''}
                      placeholder="ilim."
                      onBlur={(e) => e.target.value !== String(size.stockQuantity ?? '') &&
                        updateStock(model, size, e.target.value)} />
                  </td>
                  <td>{size.soldCount}</td>
                  <td>
                    {size.available === null
                      ? <span className="text-secondary">ilimitado</span>
                      : <span className={size.available === 0 ? 'text-red' : ''}>{size.available}</span>}
                  </td>
                  <td className="text-end">
                    <button className="btn btn-sm btn-outline-danger" onClick={() => removeSize(model, size)}>Excluir</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="row g-2">
            <div className="col-md-3">
              <input className="form-control form-control-sm" placeholder="Tamanho (ex.: M)"
                value={newSize[model.id]?.label ?? ''}
                onChange={(e) => setNewSize({ ...newSize, [model.id]: { ...(newSize[model.id] ?? {}), label: e.target.value } })} />
            </div>
            <div className="col-md-3">
              <input type="number" className="form-control form-control-sm" placeholder="Estoque (vazio = ilimitado)"
                value={newSize[model.id]?.stock ?? ''}
                onChange={(e) => setNewSize({ ...newSize, [model.id]: { ...(newSize[model.id] ?? {}), stock: e.target.value } })} />
            </div>
            <div className="col-md-2">
              <button className="btn btn-sm btn-primary" onClick={() => addSize(model)}>Adicionar tamanho</button>
            </div>
          </div>
        </Card>
        )
      })}
    </>
  )
}
