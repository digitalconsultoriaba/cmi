import { useState } from 'react'
import { Card, useApiAction } from '../../../components'
import { apiPut } from '../../../../lib/api'
import { parseMoney, formatMoney } from '../../../../lib/money'

const CENARIOS = [['conservative', 'Conservador'], ['realistic', 'Realista'], ['optimistic', 'Otimista']]

function num(v) { return Number(String(v).replace('.', '').replace(',', '.')) || 0 }

/** Simulador de preço mínimo (client-side, não persiste). */
function PrecoMinimo() {
  const [f, setF] = useState({ cost: '', sponsorship: '', other: '', paying: '' })
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })
  const paying = Number(f.paying || 0)
  const toCover = Math.max(0, num(f.cost) - num(f.sponsorship) - num(f.other))
  const min = paying > 0 ? toCover / paying : null

  return (
    <Card title="Simulador de preço do ingresso">
      <div className="row g-2">
        <div className="col-md-3"><label className="form-label">Custo total</label>
          <input className="form-control" placeholder="0,00" value={f.cost} onChange={set('cost')} /></div>
        <div className="col-md-3"><label className="form-label">Patrocínio</label>
          <input className="form-control" placeholder="0,00" value={f.sponsorship} onChange={set('sponsorship')} /></div>
        <div className="col-md-3"><label className="form-label">Outras receitas</label>
          <input className="form-control" placeholder="0,00" value={f.other} onChange={set('other')} /></div>
        <div className="col-md-3"><label className="form-label">Pagantes</label>
          <input type="number" className="form-control" value={f.paying} onChange={set('paying')} /></div>
      </div>
      <div className="mt-3">
        {min === null
          ? <span className="text-secondary">Informe a quantidade de pagantes.</span>
          : <div className="alert alert-info mb-0">
              Ingresso mínimo: <strong>{formatMoney(min.toFixed(2))}</strong>.{' '}
              Para cobrir {formatMoney(toCover.toFixed(2))} com {paying} pagantes.
            </div>}
      </div>
    </Card>
  )
}

function Cenarios({ base, scenarios, onChange }) {
  const { run, busy } = useApiAction()
  const byKey = Object.fromEntries(scenarios.map((s) => [s.key, s]))
  const [draft, setDraft] = useState(() => Object.fromEntries(CENARIOS.map(([k]) => {
    const s = byKey[k]
    return [k, {
      paying: s?.paying ?? '', avgTicket: s?.avgTicket ?? '', sponsorship: s?.sponsorship ?? '',
      cost: s?.cost ?? '', otherRevenue: s?.otherRevenue ?? '0.00',
    }]
  })))

  const set = (k, field) => (e) => setDraft({ ...draft, [k]: { ...draft[k], [field]: e.target.value } })
  const salvar = (k) => {
    const d = draft[k]
    run(() => apiPut(`${base}/scenarios/${k}`, {
      paying: Number(d.paying || 0), avgTicket: parseMoney(d.avgTicket) ?? '0.00',
      sponsorship: parseMoney(d.sponsorship) ?? '0.00', cost: parseMoney(d.cost) ?? '0.00',
      otherRevenue: parseMoney(d.otherRevenue) ?? '0.00',
    }), { onSuccess: onChange })
  }

  const fecha = (k) => {
    const d = draft[k]
    const rev = Number(d.paying || 0) * num(d.avgTicket) + num(d.sponsorship) + num(d.otherRevenue)
    return rev >= num(d.cost)
  }

  return (
    <Card title="Simulador de cenários">
      <div className="row">
        {CENARIOS.map(([k, label]) => (
          <div className="col-md-4" key={k}>
            <div className={`card mb-2 ${fecha(k) ? 'border-success' : 'border-danger'}`}>
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>{label}</strong>
                <span className={`badge ${fecha(k) ? 'bg-success' : 'bg-danger'} text-white`}>
                  {fecha(k) ? 'fecha' : 'não fecha'}
                </span>
              </div>
              <div className="card-body">
                {[['paying', 'Pagantes'], ['avgTicket', 'Ticket médio'], ['sponsorship', 'Patrocínio'], ['cost', 'Custo'], ['otherRevenue', 'Outras receitas']].map(([field, lbl]) => (
                  <div className="mb-2" key={field}>
                    <label className="form-label small mb-0">{lbl}</label>
                    <input className="form-control form-control-sm" value={draft[k][field]} onChange={set(k, field)} />
                  </div>
                ))}
                <button className="btn btn-sm btn-primary w-100" disabled={busy} onClick={() => salvar(k)}>Salvar cenário</button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </Card>
  )
}

export default function Simuladores({ base, scenarios, onChange }) {
  return (
    <>
      <PrecoMinimo />
      <Cenarios base={base} scenarios={scenarios} onChange={onChange} />
    </>
  )
}
