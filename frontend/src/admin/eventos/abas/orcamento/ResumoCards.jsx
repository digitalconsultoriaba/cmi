import { formatMoney } from '../../../../lib/money'
import Help from '../../../../components/Help'

function Card({ label, value, tone = 'info', hint, help }) {
  const bg = { positive: 'bg-success', danger: 'bg-danger', warning: 'bg-warning', info: 'bg-primary' }[tone]
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card card-sm">
        <div className="card-body">
          <div className="d-flex align-items-center">
            <span className={`badge ${bg} text-white me-2`}>&nbsp;</span>
            <div className="text-secondary text-uppercase small fw-bold">{label}</div>
            {help && <Help text={help} />}
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
        <Card label="Custo total previsto" value={money(summary.totalCost)} tone="danger"
          help="Soma de todos os itens de custo cadastrados, exceto os marcados como cancelados." />
        <Card label="Receita total prevista" value={money(summary.totalRevenue)} tone="positive"
          help="Receita de ingressos (lotes) + patrocínios previstos + outras receitas previstas." />
        <Card label="Resultado previsto" value={money(summary.result)} tone={tone} hint={classLabel}
          help="Receita total prevista menos o custo total previsto. Positivo = superávit; negativo = déficit." />
        <Card label="Investimento próprio" value={money(summary.ownInvestment)} tone="warning"
          help="Quanto falta captar quando a receita prevista não cobre o custo: máximo(0, custo − receita)." />
      </div>
      <div className="row row-cards mb-3">
        <Card label="Receita ingressos" value={money(summary.ticketRevenue)} tone="info"
          help="Soma dos lotes previstos: valor do ingresso × quantidade prevista. Cadastre em 'Simulação de ingressos por lote'." />
        <Card label="Patrocínio previsto" value={money(summary.sponsorshipExpected)} tone="info"
          hint={`Confirmado: ${money(summary.sponsorshipConfirmed)}`}
          help="Soma das cotas de patrocínio previstas (exceto perdidas/canceladas). 'Confirmado' conta só as confirmadas/recebidas." />
        <Card label="Ticket médio previsto" value={summary.avgTicket ? money(summary.avgTicket) : '—'} tone="info"
          hint={`Custo/participante: ${summary.costPerParticipant ? money(summary.costPerParticipant) : '—'}`}
          help="Receita de ingressos ÷ pagantes previstos. Precisa de lotes cadastrados e da quantidade de pagantes previstos (aba Participantes)." />
        <Card label="Ponto de equilíbrio"
          value={summary.breakEvenPaying ?? '—'} tone="info"
          hint={summary.breakEvenPaying ? `pagantes com ticket ${money(summary.avgTicket)}` : 'preencha custo, lotes e pagantes'}
          help="Quantos pagantes são necessários para cobrir o custo do evento: (custo total − patrocínio previsto − outras receitas) ÷ ticket médio. Para calcular, preencha: itens de custo, ao menos um lote de ingresso e a quantidade de pagantes previstos (aba Participantes)." />
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
