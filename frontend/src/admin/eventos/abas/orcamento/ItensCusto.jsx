import { useState } from 'react'
import { Card, Modal, ApiErrorAlert, useApiAction } from '../../../components'
import { apiPost, apiPut, apiDelete } from '../../../../lib/api'
import { formatMoney, parseMoney } from '../../../../lib/money'

const CATEGORIES = [
  'Espaço', 'Hospedagem', 'Alimentação', 'Bebidas', 'Som e iluminação', 'Infraestrutura',
  'Gráfica', 'Comunicação', 'Marketing', 'Palestrantes', 'Transporte', 'Logística',
  'Brindes', 'Cerimonial', 'Equipe', 'Segurança', 'Fotografia e filmagem', 'Taxas', 'Outros',
]
const STATUS = [
  ['planned', 'Previsto'], ['quoted', 'Cotado'], ['approved', 'Aprovado'],
  ['contracted', 'Contratado'], ['cancelled', 'Cancelado'],
]
const STATUS_LABEL = Object.fromEntries(STATUS)
const STATUS_BADGE = {
  planned: 'bg-secondary text-white', quoted: 'bg-info text-white', approved: 'bg-primary text-white',
  contracted: 'bg-success text-white', cancelled: 'bg-danger text-white',
}

function ItemForm({ base, initial, onDone, onClose }) {
  const { run, busy } = useApiAction()
  const [f, setF] = useState(initial ?? {
    description: '', category: 'Outros', quantity: '', unitPrice: '', totalAmount: '',
    supplierName: '', status: 'planned', notes: '',
  })
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })

  const salvar = () => {
    const payload = {
      description: f.description, category: f.category, status: f.status,
      supplierName: f.supplierName || null, notes: f.notes || null,
    }
    if (f.quantity !== '' && f.unitPrice !== '') {
      payload.quantity = Number(f.quantity)
      payload.unitPrice = parseMoney(f.unitPrice)
    } else if (f.totalAmount !== '') {
      payload.totalAmount = parseMoney(f.totalAmount)
    }
    const req = initial ? apiPut(`${base}/cost-items/${initial.id}`, payload) : apiPost(`${base}/cost-items`, payload)
    run(() => req, { onSuccess: () => { onDone(); onClose() } })
  }

  return (
    <Modal title={initial ? 'Editar item de custo' : 'Novo item de custo'} size="md" onClose={onClose}
      footer={<>
        <button className="btn" onClick={onClose}>Cancelar</button>
        <button className="btn btn-primary" disabled={busy || !f.description.trim()} onClick={salvar}>Salvar</button>
      </>}>
      <div className="row g-3">
        <div className="col-md-7">
          <label className="form-label required">Descrição</label>
          <input className="form-control" autoFocus value={f.description} onChange={set('description')} />
        </div>
        <div className="col-md-5">
          <label className="form-label">Categoria</label>
          <select className="form-select" value={f.category} onChange={set('category')}>
            {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
        <div className="col-md-4">
          <label className="form-label">Quantidade</label>
          <input type="number" className="form-control" value={f.quantity} onChange={set('quantity')} />
        </div>
        <div className="col-md-4">
          <label className="form-label">Valor unitário</label>
          <input className="form-control" placeholder="0,00" value={f.unitPrice} onChange={set('unitPrice')} />
        </div>
        <div className="col-md-4">
          <label className="form-label">Valor total</label>
          <input className="form-control" placeholder="ou informe só o total" value={f.totalAmount} onChange={set('totalAmount')} />
        </div>
        <div className="col-md-6">
          <label className="form-label">Fornecedor previsto</label>
          <input className="form-control" value={f.supplierName} onChange={set('supplierName')} />
        </div>
        <div className="col-md-6">
          <label className="form-label">Status</label>
          <select className="form-select" value={f.status} onChange={set('status')}>
            {STATUS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </div>
        <div className="col-12">
          <label className="form-label">Observação</label>
          <textarea className="form-control" rows={2} value={f.notes} onChange={set('notes')} />
        </div>
      </div>
    </Modal>
  )
}

export default function ItensCusto({ base, items, onChange }) {
  const { run, busy, error, setError } = useApiAction()
  const [editing, setEditing] = useState(null) // 'new' | item

  const remover = (item) => {
    if (!window.confirm(`Excluir "${item.description}"?`)) return
    run(() => apiDelete(`${base}/cost-items/${item.id}`), { onSuccess: onChange })
  }
  const duplicar = (item) => run(() => apiPost(`${base}/cost-items/${item.id}/duplicate`), { onSuccess: onChange })
  const gerarConta = (item) => run(
    () => apiPost(`${base}/cost-items/${item.id}/generate-payable`),
    { onSuccess: onChange },
  )

  return (
    <Card title="Itens de custo previstos"
      actions={<button className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>Novo item</button>}>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      <table className="table table-vcenter">
        <thead><tr>
          <th>Descrição</th><th>Categoria</th><th className="text-end">Total</th><th>Status</th><th /></tr></thead>
        <tbody>
          {items.length === 0 && <tr><td colSpan={5} className="text-secondary">Nenhum item cadastrado.</td></tr>}
          {items.map((item) => (
            <tr key={item.id}>
              <td>{item.description}{item.supplierName && <div className="text-secondary small">{item.supplierName}</div>}</td>
              <td>{item.category}</td>
              <td className="text-end">{formatMoney(item.totalAmount)}</td>
              <td><span className={`badge ${STATUS_BADGE[item.status]}`}>{STATUS_LABEL[item.status]}</span></td>
              <td className="text-end">
                <span className="btn-list justify-content-end">
                  {item.convertible ? (
                    <button className="btn btn-sm btn-success" disabled={busy} onClick={() => gerarConta(item)}>Gerar conta a pagar</button>
                  ) : (
                    <span className="badge bg-teal text-white align-self-center">conta gerada</span>
                  )}
                  <button className="btn btn-sm" onClick={() => setEditing(item)}>Editar</button>
                  <button className="btn btn-sm" disabled={busy} onClick={() => duplicar(item)}>Duplicar</button>
                  <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => remover(item)}>Excluir</button>
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {editing && (
        <ItemForm base={base} initial={editing === 'new' ? null : editing}
          onDone={onChange} onClose={() => setEditing(null)} />
      )}
    </Card>
  )
}
