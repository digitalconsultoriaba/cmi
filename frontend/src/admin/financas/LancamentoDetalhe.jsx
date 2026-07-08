import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiUpload } from '../../lib/api'
import { ApiErrorAlert, useApiAction } from '../components'
import LancamentoModal from './LancamentoModal'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const STATUS_BADGE = {
  open: 'bg-secondary text-white', partial: 'bg-primary text-white',
  settled: 'bg-success text-white', overdue: 'bg-danger text-white', cancelled: 'bg-dark text-white',
}

export default function LancamentoDetalhe({ id, onClose }) {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [baixa, setBaixa] = useState({ amount: '', settled_on: new Date().toISOString().slice(0, 10), payment_method_id: '', note: '' })
  const [showBaixa, setShowBaixa] = useState(false)
  const [editing, setEditing] = useState(false)

  const { data: e } = useQuery({ queryKey: ['finance', 'entry', id], queryFn: () => apiGet(`/finance/entries/${id}`) })
  const { data: methods = [] } = useQuery({ queryKey: ['finance', 'methods'], queryFn: () => apiGet('/finance/payment-methods') })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['finance', 'entry', id] })

  if (!e) return <p className="text-secondary">Carregando…</p>
  const isReceivable = e.direction === 'receivable'

  const darBaixa = () => run(() => apiPost(`/finance/entries/${id}/settle`, {
    amount: baixa.amount, settled_on: baixa.settled_on,
    payment_method_id: baixa.payment_method_id || null, note: baixa.note || null,
  }), { onSuccess: () => { setShowBaixa(false); setBaixa({ ...baixa, amount: '', note: '' }); refresh() } })

  const cancelar = () => {
    const reason = window.prompt('Motivo do cancelamento:')
    if (reason) run(() => apiPost(`/finance/entries/${id}/cancel`, { reason }), { onSuccess: refresh })
  }
  const estornar = () => {
    const reason = window.prompt('Motivo do estorno:')
    const amount = window.prompt('Valor a estornar (ex.: 100.00):', e.settledAmount)
    if (reason && amount) run(() => apiPost(`/finance/entries/${id}/reverse`, { amount, reason }), { onSuccess: refresh })
  }
  const anexar = (ev) => {
    const file = ev.target.files?.[0]
    if (!file) return
    const fd = new FormData(); fd.append('file', file)
    run(() => apiUpload(`/finance/entries/${id}/attachments`, fd), { onSuccess: refresh })
  }

  return (
    <>
      <div className="card mb-3"><div className="card-body d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <button className="btn btn-sm mb-2" onClick={onClose}>← Voltar</button>
          <h2 className="mb-0">{e.description}</h2>
          <div className="text-secondary">
            {isReceivable ? 'A receber' : 'A pagar'} · {e.event?.name ?? 'Geral'} · {e.category ?? 'sem categoria'}
            {e.person && <> · {e.person}</>}
          </div>
        </div>
        <span className={`badge ${STATUS_BADGE[e.status]}`} style={{ fontSize: '.9rem' }}>{e.statusLabel}</span>
      </div></div>

      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="row row-deck row-cards mb-3">
        <div className="col-4"><div className="card card-sm"><div className="card-body"><div className="subheader">Valor original</div><div className="h2 mb-0">{money(e.amount)}</div></div></div></div>
        <div className="col-4"><div className="card card-sm"><div className="card-body"><div className="subheader">{isReceivable ? 'Recebido' : 'Pago'}</div><div className="h2 mb-0 text-green">{money(e.settledAmount)}</div></div></div></div>
        <div className="col-4"><div className="card card-sm"><div className="card-body"><div className="subheader">Saldo restante</div><div className="h2 mb-0 text-orange">{money(e.balance)}</div></div></div></div>
      </div>

      {!e.readonly && e.status !== 'cancelled' && (
        <div className="mb-3 btn-list">
          {e.status !== 'settled' && (
            showBaixa ? null : <button className="btn btn-success" onClick={() => { setShowBaixa(true); setBaixa({ ...baixa, amount: e.balance }) }}>Dar baixa</button>
          )}
          <button className="btn" onClick={() => setEditing(true)}>Editar</button>
          {Number(e.settledAmount) > 0 && <button className="btn" onClick={estornar}>Estornar</button>}
          <label className="btn mb-0">Anexar comprovante<input type="file" hidden accept="application/pdf,image/*" onChange={anexar} /></label>
          <button className="btn btn-outline-danger" onClick={cancelar}>Cancelar lançamento</button>
        </div>
      )}

      {editing && (
        <LancamentoModal direction={e.direction} entry={e}
          onClose={() => setEditing(false)}
          onSaved={() => { setEditing(false); refresh() }} />
      )}
      {e.readonly && <div className="alert alert-info">Lançamento automático (espelho de ingresso/patrocínio) — a baixa é feita na venda.</div>}

      {showBaixa && (
        <div className="card mb-3"><div className="card-body">
          <h3 className="card-title">Registrar baixa</h3>
          <div className="row g-2 align-items-end">
            <div className="col-md-3"><label className="form-label">Valor</label>
              <input className="form-control" value={baixa.amount} onChange={(ev) => setBaixa({ ...baixa, amount: ev.target.value })} /></div>
            <div className="col-md-3"><label className="form-label">Data</label>
              <input type="date" className="form-control" value={baixa.settled_on} onChange={(ev) => setBaixa({ ...baixa, settled_on: ev.target.value })} /></div>
            <div className="col-md-3"><label className="form-label">Forma</label>
              <select className="form-select" value={baixa.payment_method_id} onChange={(ev) => setBaixa({ ...baixa, payment_method_id: ev.target.value })}>
                <option value="">—</option>{methods.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
              </select></div>
            <div className="col-md-3"><label className="form-label">Observação</label>
              <input className="form-control" value={baixa.note} onChange={(ev) => setBaixa({ ...baixa, note: ev.target.value })} /></div>
            <div className="col-12"><button className="btn btn-success" disabled={busy || !baixa.amount} onClick={darBaixa}>Confirmar baixa</button>
              <button className="btn ms-2" onClick={() => setShowBaixa(false)}>Cancelar</button></div>
          </div>
        </div></div>
      )}

      <div className="row row-cards">
        <div className="col-lg-6">
          <div className="card"><div className="card-header"><h3 className="card-title">Movimentações</h3></div>
            <div className="card-table table-responsive"><table className="table table-vcenter">
              <thead><tr><th>Tipo</th><th>Data</th><th className="text-end">Valor</th><th>Forma</th></tr></thead>
              <tbody>
                {(e.settlements ?? []).map((s) => (
                  <tr key={s.id}>
                    <td>{s.kind === 'reversal' ? 'Estorno' : (isReceivable ? 'Recebimento' : 'Pagamento')}</td>
                    <td>{s.settledOn ? new Date(s.settledOn + 'T00:00').toLocaleDateString('pt-BR') : '—'}</td>
                    <td className={`text-end ${s.kind === 'reversal' ? 'text-red' : ''}`}>{money(s.amount)}</td>
                    <td>{s.method ?? '—'}</td>
                  </tr>
                ))}
                {(e.settlements ?? []).length === 0 && <tr><td colSpan={4} className="text-secondary">Sem baixas.</td></tr>}
              </tbody>
            </table></div>
          </div>
        </div>
        <div className="col-lg-6">
          <div className="card"><div className="card-header"><h3 className="card-title">Anexos</h3></div>
            <div className="card-body">
              {(e.attachments ?? []).length === 0 && <p className="text-secondary mb-0">Nenhum anexo.</p>}
              {(e.attachments ?? []).map((a) => (
                <div key={a.id} className="d-flex justify-content-between align-items-center mb-1">
                  <a href={`/api/finance/entries/${id}/attachments/${a.id}`} target="_blank" rel="noopener">{a.name}</a>
                  <span className="badge bg-secondary-lt">{a.kind}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
