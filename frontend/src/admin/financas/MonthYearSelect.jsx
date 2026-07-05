const MONTHS = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
]

const now = new Date()
export const CURRENT_MONTH = now.getMonth() + 1
export const CURRENT_YEAR = now.getFullYear()

/** Faixa de dias (YYYY-MM-DD) do mês/ano. */
export function monthRange(year, month) {
  const mm = String(month).padStart(2, '0')
  const lastDay = new Date(year, month, 0).getDate()
  return { from: `${year}-${mm}-01`, to: `${year}-${mm}-${String(lastDay).padStart(2, '0')}` }
}

/**
 * Selects de mês (Jan–Dez) e ano. `allowAll` adiciona "Todos os meses" (month = '').
 */
export default function MonthYearSelect({ month, year, onChange, allowAll = false }) {
  const years = []
  for (let y = CURRENT_YEAR + 1; y >= CURRENT_YEAR - 4; y--) years.push(y)

  return (
    <div className="row g-2">
      <div className="col">
        <label className="form-label">Mês</label>
        <select className="form-select" value={month}
          onChange={(e) => onChange({ month: e.target.value === '' ? '' : Number(e.target.value), year })}>
          {allowAll && <option value="">Todos os meses</option>}
          {MONTHS.map((label, i) => <option key={i + 1} value={i + 1}>{label}</option>)}
        </select>
      </div>
      <div className="col">
        <label className="form-label">Ano</label>
        <select className="form-select" value={year}
          onChange={(e) => onChange({ month, year: Number(e.target.value) })}>
          {years.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
      </div>
    </div>
  )
}
