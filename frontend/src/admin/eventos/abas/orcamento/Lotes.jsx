import { useState } from 'react'
import { Card, Modal, useApiAction } from '../../../components'
import { apiPost, apiPut, apiDelete } from '../../../../lib/api'
import { formatMoney, parseMoney } from '../../../../lib/money'

function LoteForm({ base, initial, onDone, onClose }) {
  const { run, busy } = useApiAction()
  const [f, setF] = useState(initial ?? { name: '', unitPrice: '', expectedQuantity: '', expectedPaying: '', notes: '' })
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })

  const salvar = () => {
    const payload = {
      name: f.name, unitPrice: parseMoney(f.unitPrice),
      expectedQuantity: Number(f.expectedQuantity || 0),
      expectedPaying: f.expectedPaying === '' ? null : Number(f.expectedPaying),
      notes: f.notes || null,
    }
    const req = initial ? apiPut(`${base}/ticket-lots/${initial.id}`, payload) : apiPost(`${base}/ticket-lots`, payload)
    run(() => req, { onSuccess: () => { onDone(); onClose() } })
  }

  return (
    <Modal title={initial ? 'Editar lote previsto' : 'Novo lote previsto'} size="md" onClose={onClose}
      footer={<>
        <button className="btn" onClick={onClose}>Cancelar</button>
        <button className="btn btn-primary" disabled={busy || !f.name.trim()} onClick={salvar}>Salvar</button>
      </>}>
      <div className="row g-3">
        <div className="col-md-6"><label className="form-label required">Nome do lote</label>
          <input className="form-control" autoFocus value={f.name} onChange={set('name')} /></div>
        <div className="col-md-6"><label className="form-label required">Valor do ingresso</label>
          <input className="form-control" placeholder="0,00" value={f.unitPrice} onChange={set('unitPrice')} /></div>
        <div className="col-md-6"><label className="form-label">Quantidade prevista</label>
          <input type="number" className="form-control" value={f.expectedQuantity} onChange={set('expectedQuantity')} /></div>
        <div className="col-md-6"><label className="form-label">Pagantes estimados</label>
          <input type="number" className="form-control" placeholder="= quantidade" value={f.expectedPaying} onChange={set('expectedPaying')} /></div>
        <div className="col-12"><label className="form-label">Observação</label>
          <input className="form-control" value={f.notes} onChange={set('notes')} /></div>
      </div>
    </Modal>
  )
}

export default function Lotes({ base, lots, onChange }) {
  const { run, busy } = useApiAction()
  const [editing, setEditing] = useState(null)

  const remover = (lot) => {
    if (!window.confirm(`Excluir "${lot.name}"?`)) return
    run(() => apiDelete(`${base}/ticket-lots/${lot.id}`), { onSuccess: onChange })
  }

  return (
    <Card title="Simulação de ingressos por lote"
      actions={<button className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>Novo lote</button>}>
      <table className="table table-vcenter">
        <thead><tr><th>Lote</th><th className="text-end">Valor</th><th className="text-end">Qtd prevista</th><th className="text-end">Receita prevista</th><th /></tr></thead>
        <tbody>
          {lots.length === 0 && <tr><td colSpan={5} className="text-secondary">Nenhum lote previsto.</td></tr>}
          {lots.map((lot) => (
            <tr key={lot.id}>
              <td>{lot.name}</td>
              <td className="text-end">{formatMoney(lot.unitPrice)}</td>
              <td className="text-end">{lot.expectedQuantity}</td>
              <td className="text-end fw-bold">{formatMoney(lot.expectedRevenue)}</td>
              <td className="text-end">
                <button className="btn btn-sm" onClick={() => setEditing(lot)}>Editar</button>{' '}
                <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => remover(lot)}>Excluir</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {editing && <LoteForm base={base} initial={editing === 'new' ? null : editing} onDone={onChange} onClose={() => setEditing(null)} />}
    </Card>
  )
}
