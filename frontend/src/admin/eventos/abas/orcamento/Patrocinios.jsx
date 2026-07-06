import { useState } from 'react'
import { Card, Modal, ApiErrorAlert, useApiAction } from '../../../components'
import { apiPost, apiPut, apiDelete } from '../../../../lib/api'
import { formatMoney, parseMoney } from '../../../../lib/money'
import MoneyInput from '../../../../components/MoneyInput'

const STATUS = [
  ['planned', 'Previsto'], ['negotiating', 'Em negociação'], ['confirmed', 'Confirmado'],
  ['received', 'Recebido'], ['lost', 'Perdido'], ['cancelled', 'Cancelado'],
]
const STATUS_LABEL = Object.fromEntries(STATUS)
const STATUS_BADGE = {
  planned: 'bg-secondary text-white', negotiating: 'bg-warning text-dark', confirmed: 'bg-primary text-white',
  received: 'bg-success text-white', lost: 'bg-danger text-white', cancelled: 'bg-danger text-white',
}

function PatForm({ base, initial, onDone, onClose }) {
  const { run, busy } = useApiAction()
  const [f, setF] = useState(initial ?? { name: '', unitValue: '', quantity: 1, status: 'planned', notes: '' })
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })

  const salvar = () => {
    const payload = {
      name: f.name, unitValue: parseMoney(f.unitValue),
      quantity: Number(f.quantity || 1), status: f.status, notes: f.notes || null,
    }
    const req = initial ? apiPut(`${base}/sponsorships/${initial.id}`, payload) : apiPost(`${base}/sponsorships`, payload)
    run(() => req, { onSuccess: () => { onDone(); onClose() } })
  }

  return (
    <Modal title={initial ? 'Editar cota de patrocínio' : 'Nova cota de patrocínio'} size="md" onClose={onClose}
      footer={<>
        <button className="btn" onClick={onClose}>Cancelar</button>
        <button className="btn btn-primary" disabled={busy || !f.name.trim()} onClick={salvar}>Salvar</button>
      </>}>
      <div className="row g-3">
        <div className="col-md-6"><label className="form-label required">Nome da cota</label>
          <input className="form-control" autoFocus value={f.name} onChange={set('name')} /></div>
        <div className="col-md-6"><label className="form-label required">Valor</label>
          <MoneyInput value={f.unitValue} onChange={(v) => setF({ ...f, unitValue: v })} /></div>
        <div className="col-md-6"><label className="form-label">Quantidade de cotas</label>
          <input type="number" className="form-control" value={f.quantity} onChange={set('quantity')} /></div>
        <div className="col-md-6"><label className="form-label">Status</label>
          <select className="form-select" value={f.status} onChange={set('status')}>
            {STATUS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select></div>
        <div className="col-12"><label className="form-label">Observação</label>
          <input className="form-control" value={f.notes} onChange={set('notes')} /></div>
      </div>
    </Modal>
  )
}

export default function Patrocinios({ base, sponsorships, onChange }) {
  const { run, busy, error, setError } = useApiAction()
  const [editing, setEditing] = useState(null)

  const remover = (s) => {
    if (!window.confirm(`Excluir "${s.name}"?`)) return
    run(() => apiDelete(`${base}/sponsorships/${s.id}`), { onSuccess: onChange })
  }
  const gerarConta = (s) => run(() => apiPost(`${base}/sponsorships/${s.id}/generate-receivable`), { onSuccess: onChange })

  return (
    <Card title="Simulação de patrocínios"
      actions={<button className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>Nova cota</button>}>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      <table className="table table-vcenter">
        <thead><tr><th>Cota</th><th className="text-end">Valor</th><th className="text-end">Qtd</th><th>Status</th><th className="text-end">Receita prevista</th><th /></tr></thead>
        <tbody>
          {sponsorships.length === 0 && <tr><td colSpan={6} className="text-secondary">Nenhuma cota prevista.</td></tr>}
          {sponsorships.map((s) => (
            <tr key={s.id}>
              <td>{s.name}</td>
              <td className="text-end">{formatMoney(s.unitValue)}</td>
              <td className="text-end">{s.quantity}</td>
              <td><span className={`badge ${STATUS_BADGE[s.status]}`}>{STATUS_LABEL[s.status]}</span></td>
              <td className="text-end fw-bold">{formatMoney(s.expectedRevenue)}</td>
              <td className="text-end">
                <span className="btn-list justify-content-end">
                  {s.convertible ? (
                    <button className="btn btn-sm btn-success" disabled={busy} onClick={() => gerarConta(s)}>Gerar conta a receber</button>
                  ) : s.financialEntryId ? (
                    <span className="badge bg-teal text-white align-self-center">conta gerada</span>
                  ) : null}
                  <button className="btn btn-sm" onClick={() => setEditing(s)}>Editar</button>
                  <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => remover(s)}>Excluir</button>
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {editing && <PatForm base={base} initial={editing === 'new' ? null : editing} onDone={onChange} onClose={() => setEditing(null)} />}
    </Card>
  )
}
