import Chart from 'react-apexcharts'
import { Card } from '../../../components'

/** Gráficos simples do orçamento (spec 011). */
export default function Graficos({ costItems, summary }) {
  // Custos por categoria (itens não cancelados).
  const byCat = {}
  costItems.filter((i) => i.status !== 'cancelled').forEach((i) => {
    byCat[i.category] = (byCat[i.category] ?? 0) + Number(i.totalAmount)
  })
  const catLabels = Object.keys(byCat)
  const catValues = Object.values(byCat)

  const receitas = [
    Number(summary.ticketRevenue), Number(summary.sponsorshipExpected), Number(summary.otherRevenue),
  ]

  return (
    <div className="row row-cards">
      <div className="col-lg-6">
        <Card title="Custos por categoria">
          {catLabels.length === 0
            ? <p className="text-secondary mb-0">Sem itens de custo.</p>
            : <Chart type="bar" height={280}
                series={[{ name: 'Custo', data: catValues }]}
                options={{
                  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
                  colors: ['#1e3a8a'], plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
                  xaxis: { categories: catLabels }, dataLabels: { enabled: false },
                }} />}
        </Card>
      </div>
      <div className="col-lg-6">
        <Card title="Receitas previstas por tipo">
          <Chart type="donut" height={280}
            series={receitas}
            options={{
              labels: ['Ingressos', 'Patrocínios', 'Outras'],
              colors: ['#206bc4', '#2fb344', '#f59f00'],
              legend: { position: 'bottom' }, dataLabels: { enabled: true },
            }} />
        </Card>
      </div>
    </div>
  )
}
