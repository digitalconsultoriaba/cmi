import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../../AdminLayout'
import { apiGet } from '../../../lib/api'
import { Card } from '../../components'

import ConfigSite from './site/ConfigSite'
import Navbar from './site/Navbar'
import Hero from './site/Hero'
import Estatisticas from './site/Estatisticas'
import Sobre from './site/Sobre'
import Pilares from './site/Pilares'
import Palestrantes from './site/Palestrantes'
import Programacao from './site/Programacao'
import Local from './site/Local'
import Informacoes from './site/Informacoes'
import Patrocinadores from './site/Patrocinadores'
import Depoimentos from './site/Depoimentos'
import Faq from './site/Faq'
import CtaFinal from './site/CtaFinal'
import Rodape from './site/Rodape'
import Legal from './site/Legal'
import Loading from '../../../components/Loading'

// Ordem e rótulos do menu do CMS (espelha as seções do site).
const PANELS = [
  ['config', 'Config', ConfigSite],
  ['navbar', 'Navbar', Navbar],
  ['hero', 'Hero', Hero],
  ['stats', 'Estatísticas', Estatisticas],
  ['about', 'Sobre', Sobre],
  ['pillars', 'Experiência', Pilares],
  ['speakers', 'Palestrantes', Palestrantes],
  ['program', 'Programação', Programacao],
  ['local', 'Local', Local],
  ['info', 'Informações', Informacoes],
  ['sponsors', 'Patrocinadores', Patrocinadores],
  ['testimonials', 'Depoimentos', Depoimentos],
  ['faq', 'FAQ', Faq],
  ['cta', 'CTA final', CtaFinal],
  ['footer', 'Rodapé', Rodape],
  ['legal', 'Legal', Legal],
]

export default function SiteLayout() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const [active, setActive] = useState('config')

  const { data: site, isLoading, refetch } = useQuery({
    queryKey: ['admin', 'site', event?.id],
    queryFn: () => apiGet(`/admin/events/${event.id}/site`),
    enabled: !!event?.id,
  })

  if (!event || isLoading || !site) return <Loading fullscreen={false} />

  const languages = site.activeLanguages || ['pt']
  const section = site.sections.find((s) => s.type === active)
  const [, label, Panel] = PANELS.find(([t]) => t === active) || PANELS[0]
  const reload = () => refetch()

  return (
    <div className="row g-3">
      <div className="col-md-3">
        <div className="list-group">
          {PANELS.map(([type, lbl]) => {
            const sec = site.sections.find((s) => s.type === type)
            const hidden = sec && type !== 'config' && type !== 'legal' && !sec.isActive
            return (
              <button key={type}
                className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${active === type ? 'active' : ''}`}
                onClick={() => setActive(type)}>
                {lbl}
                {hidden && <span className="badge bg-secondary-lt">oculta</span>}
              </button>
            )
          })}
        </div>
      </div>
      <div className="col-md-9">
        <Card title={label}>
          {active === 'config'
            ? <ConfigSite site={site} event={event} eventId={event.id} reload={reload}
                reloadEvent={() => queryClient.invalidateQueries({ queryKey: ['admin', 'event'] })} />
            : <Panel eventId={event.id} section={section} languages={languages} reload={reload} />}
        </Card>
      </div>
    </div>
  )
}
