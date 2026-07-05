import { useQuery } from '@tanstack/react-query'
import { Card } from '../../../components'
import { apiGet } from '../../../../lib/api'
import { formatMoney } from '../../../../lib/money'

const COST_STATUS = { under: ['bg-success text-white', 'abaixo do orçamento'], over: ['bg-danger text-white', 'acima do orçamento'], on: ['bg-secondary text-white', 'no orçamento'] }

export default function Comparativo({ base, eventId }) {
  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'budget-comparison'],
    queryFn: () => apiGet(`${base}/comparison`),
  })
  if (!data) return null

  const row = (label, budgeted, actual, extra) => (
    <tr>
      <td>{label}</td>
      <td className="text-end">{budgeted}</td>
      <td className="text-end">{actual}</td>
      <td className="text-end">{extra}</td>
    </tr>
  )
  const [badge, txt] = COST_STATUS[data.cost.status] ?? COST_STATUS.on

  return (
    <Card title="Comparativo orçado × realizado">
      <table className="table table-vcenter">
        <thead><tr><th>Indicador</th><th className="text-end">Orçado</th><th className="text-end">Realizado</th><th className="text-end">Situação</th></tr></thead>
        <tbody>
          {row('Despesa', formatMoney(data.cost.budgeted), formatMoney(data.cost.actual), <span className={`badge ${badge}`}>{txt}</span>)}
          {row('Receita', formatMoney(data.revenue.budgeted), formatMoney(data.revenue.actual), `dif. ${formatMoney(data.revenue.diff)}`)}
          {row('Patrocínio', formatMoney(data.sponsorship.budgeted), formatMoney(data.sponsorship.actual), `dif. ${formatMoney(data.sponsorship.diff)}`)}
          {row('Ingressos', data.tickets.budgeted, data.tickets.actual,
            data.tickets.attainmentPct !== null ? `${data.tickets.attainmentPct}%` : '—')}
          {row('Resultado', formatMoney(data.result.budgeted), formatMoney(data.result.actual), '')}
        </tbody>
      </table>
      {data.tickets.attainmentPct !== null && (
        <div>
          <div className="text-secondary small mb-1">Atingimento da meta de ingressos</div>
          <div className="progress" style={{ height: 20 }}>
            <div className="progress-bar bg-primary" style={{ width: `${Math.min(100, data.tickets.attainmentPct)}%` }}>
              {data.tickets.attainmentPct}%
            </div>
          </div>
        </div>
      )}
    </Card>
  )
}
