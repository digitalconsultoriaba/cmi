import { formatMoney } from '../../../../lib/money'

function Card({ label, value, tone = 'info', hint }) {
  const bg = { positive: 'bg-success', danger: 'bg-danger', warning: 'bg-warning', info: 'bg-primary' }[tone]
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card card-sm">
        <div className="card-body">
          <div className="d-flex align-items-center">
            <span className={`badge ${bg} text-white me-2`}>&nbsp;</span>
            <div className="text-secondary text-uppercase small fw-bold">{label}</div>
          </div>
          <div className="h2 mb-0 mt-1">{value}</div>
          {hint && <div className="text-secondary small">{hint}</div>}
        </div>
      </div>
    </div>
  )
}

const money = (v) => (v === null || v === undefined ? '—' : formatMoney(v))

export default function ResumoCards({ summary }) {
  if (!summary) return null
  const tone = summary.classification === 'surplus' ? 'positive'
    : summary.classification === 'deficit' ? 'danger' : 'warning'
  const classLabel = { surplus: 'Superávit previsto', deficit: 'Déficit previsto', breakeven: 'Ponto de equilíbrio' }[summary.classification]

  return (
    <>
      <div className="row row-cards mb-2">
        <Card label="Custo total previsto" value={money(summary.totalCost)} tone="danger" />
        <Card label="Receita total prevista" value={money(summary.totalRevenue)} tone="positive" />
        <Card label="Resultado previsto" value={money(summary.result)} tone={tone} hint={classLabel} />
        <Card label="Investimento próprio" value={money(summary.ownInvestment)} tone="warning" />
      </div>
      <div className="row row-cards mb-3">
        <Card label="Receita ingressos" value={money(summary.ticketRevenue)} tone="info" />
        <Card label="Patrocínio previsto" value={money(summary.sponsorshipExpected)} tone="info"
          hint={`Confirmado: ${money(summary.sponsorshipConfirmed)}`} />
        <Card label="Ticket médio previsto" value={summary.avgTicket ? money(summary.avgTicket) : '—'} tone="info"
          hint={`Custo/participante: ${summary.costPerParticipant ? money(summary.costPerParticipant) : '—'}`} />
        <Card label="Ponto de equilíbrio"
          value={summary.breakEvenPaying ?? '—'} tone="info"
          hint={summary.breakEvenPaying ? `pagantes com ticket ${money(summary.avgTicket)}` : 'informe lotes/pagantes'} />
      </div>

      {summary.alerts?.length > 0 && (
        <div className="mb-3">
          {summary.alerts.map((a, i) => (
            <div key={i} className={`alert alert-${a.level === 'danger' ? 'danger' : a.level === 'warning' ? 'warning' : 'info'} py-2 mb-2`}>
              {a.message}
            </div>
          ))}
        </div>
      )}
    </>
  )
}
