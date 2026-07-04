import Chart from 'react-apexcharts'

/**
 * Rosca (donut) do painel — visual do protótipo (Tabler/ApexCharts).
 * `series` = [n, n, …]; `labels` = ['Publicado', …]; `colors` opcional.
 */
export default function DonutChart({ series, labels, colors, height = 240 }) {
  const total = series.reduce((sum, n) => sum + n, 0)

  if (total === 0) {
    return (
      <div className="d-flex align-items-center justify-content-center text-secondary"
        style={{ height }}>
        Sem dados no período.
      </div>
    )
  }

  const options = {
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels,
    colors: colors ?? ['#2fb344', '#d63939', '#f59f00', '#1e3a8a', '#4299e1'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
    plotOptions: { pie: { donut: { size: '70%' } } },
    stroke: { width: 2 },
    tooltip: { fillSeriesColor: false },
  }

  return <Chart type="donut" series={series} options={options} height={height} />
}
