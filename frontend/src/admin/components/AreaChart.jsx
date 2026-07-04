import Chart from 'react-apexcharts'

/**
 * Curva de área do painel (ex.: inscrições por mês) — visual do protótipo.
 * `categories` = ['08/2025', …]; `data` = [n, n, …]; `name` da série.
 */
export default function AreaChart({ categories, data, name = 'Inscrições', height = 260 }) {
  const options = {
    chart: { type: 'area', fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
    colors: ['#1e3a8a'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] },
    },
    xaxis: { categories, tooltip: { enabled: false }, axisBorder: { show: false } },
    yaxis: { min: 0, forceNiceScale: true },
    grid: { strokeDashArray: 4, borderColor: 'rgba(0,0,0,0.08)' },
    tooltip: { x: { show: true } },
  }

  return <Chart type="area" series={[{ name, data }]} options={options} height={height} />
}
