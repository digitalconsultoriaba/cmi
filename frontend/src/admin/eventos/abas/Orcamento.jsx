import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { apiGet } from '../../../lib/api'
import ResumoCards from './orcamento/ResumoCards'
import ItensCusto from './orcamento/ItensCusto'
import Lotes from './orcamento/Lotes'
import Patrocinios from './orcamento/Patrocinios'
import Participantes from './orcamento/Participantes'
import Comparativo from './orcamento/Comparativo'
import Simuladores from './orcamento/Simuladores'
import Graficos from './orcamento/Graficos'

/** Aba Orçamento do evento (spec 011) — planejamento e previsão financeira. */
export default function Orcamento() {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const base = `/admin/events/${eventId}/budget`

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'event', eventId, 'budget'],
    queryFn: () => apiGet(base),
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId] })

  if (isLoading || !data) return <p className="text-secondary">Carregando…</p>

  return (
    <>
      <div className="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <h2 className="mb-0">Orçamento</h2>
          <div className="text-secondary">Planejamento e previsão — não substitui o Financeiro.</div>
        </div>
        <div className="btn-list">
          <a className="btn btn-sm" href={`/api${base}/export.xlsx`} target="_blank" rel="noopener">Exportar Excel</a>
          <a className="btn btn-sm" href={`/api${base}/export.pdf`} target="_blank" rel="noopener">Exportar PDF</a>
        </div>
      </div>

      <ResumoCards summary={data.summary} />

      <Lotes base={base} lots={data.ticketLots} onChange={refresh} />
      <Patrocinios base={base} sponsorships={data.sponsorships} onChange={refresh} />
      <ItensCusto base={base} items={data.costItems} onChange={refresh} />
      <Participantes base={base} plan={data.plan} onChange={refresh} />

      <Graficos costItems={data.costItems} summary={data.summary} />
      <Comparativo base={base} eventId={eventId} />
      <Simuladores base={base} scenarios={data.scenarios} onChange={refresh} />
    </>
  )
}
