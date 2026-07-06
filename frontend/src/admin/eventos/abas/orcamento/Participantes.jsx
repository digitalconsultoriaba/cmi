import { useState } from 'react'
import { Card, useApiAction } from '../../../components'
import { apiPut } from '../../../../lib/api'
import { parseMoney } from '../../../../lib/money'
import MoneyInput from '../../../../components/MoneyInput'

const SEG = [
  ['expectedPaying', 'Pagantes'], ['expectedCourtesy', 'Cortesias'], ['expectedGuests', 'Convidados'],
  ['expectedStaff', 'Equipe'], ['expectedSpeakers', 'Palestrantes'],
]

export default function Participantes({ base, plan, onChange }) {
  const { run, busy } = useApiAction()
  const [f, setF] = useState({
    expectedPaying: plan.expectedPaying, expectedCourtesy: plan.expectedCourtesy,
    expectedGuests: plan.expectedGuests, expectedStaff: plan.expectedStaff,
    expectedSpeakers: plan.expectedSpeakers,
    otherRevenue: plan.otherRevenue ?? '0.00',
    safetyMarginPct: plan.safetyMarginPct ?? '',
    notes: plan.notes ?? '',
  })
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })
  const total = SEG.reduce((s, [k]) => s + Number(f[k] || 0), 0)

  const salvar = () => {
    const payload = {
      ...Object.fromEntries(SEG.map(([k]) => [k, Number(f[k] || 0)])),
      otherRevenue: parseMoney(f.otherRevenue) ?? '0.00',
      safetyMarginPct: f.safetyMarginPct === '' ? null : Number(f.safetyMarginPct),
      notes: f.notes || null,
    }
    run(() => apiPut(base, payload), { onSuccess: onChange })
  }

  return (
    <Card title="Participantes, outras receitas e margem"
      actions={<button className="btn btn-primary btn-sm" disabled={busy} onClick={salvar}>Salvar</button>}>
      <div className="row g-3">
        {SEG.map(([k, label]) => (
          <div className="col-6 col-md-2" key={k}>
            <label className="form-label">{label}</label>
            <input type="number" className="form-control" value={f[k]} onChange={set(k)} />
          </div>
        ))}
        <div className="col-6 col-md-2">
          <label className="form-label">Total geral</label>
          <input className="form-control" value={total} disabled />
        </div>
        <div className="col-md-4">
          <label className="form-label">Outras receitas previstas</label>
          <MoneyInput value={f.otherRevenue} onChange={(v) => setF({ ...f, otherRevenue: v })} />
        </div>
        <div className="col-md-4">
          <label className="form-label">Margem de segurança (%)</label>
          <input type="number" className="form-control" placeholder="ex.: 10" value={f.safetyMarginPct} onChange={set('safetyMarginPct')} />
        </div>
        <div className="col-md-4">
          <label className="form-label">Observações gerais</label>
          <input className="form-control" value={f.notes} onChange={set('notes')} />
        </div>
      </div>
      <div className="text-secondary small mt-2">
        Somente pagantes geram receita de ingresso; todos os segmentos entram no custo por participante.
      </div>
    </Card>
  )
}
