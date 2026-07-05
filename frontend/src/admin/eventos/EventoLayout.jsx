import { createContext, useContext, useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { useApiAction, ApiErrorAlert } from '../components'
import { apiPost, apiUpload } from '../../lib/api'
import EventoModal from '../components/EventoModal'

// Contexto para telas internas (ficha do cliente) esconderem as abas do evento
const EventoUI = createContext(null)
export const useEventoUI = () => useContext(EventoUI)

// `when` opcional: só entra na barra quando a condição é verdadeira.
const TABS = [
  { to: '', label: 'Painel', end: true },
  { to: 'inscritos', label: 'Inscritos' }, // Financeiro foi agrupado aqui
  { to: 'ingressos', label: 'Ingressos' },
  { to: 'camisas', label: 'Camisas', when: (e) => e.allowShirtChoice || e.requiresShirt },
  { to: 'cortesias', label: 'Cortesias' },
  { to: 'patrocinio', label: 'Patrocínio' },
  { to: 'orcamento', label: 'Orçamento' },
  { to: 'relatorios', label: 'Relatórios' },
  { to: 'checkin', label: 'Check-in' },
  { to: 'trilha', label: 'Logs' },
]

const STATUS_BADGE = {
  published: 'bg-green-lt', draft: 'bg-secondary-lt',
  cancelled: 'bg-red-lt', finished: 'bg-blue-lt',
}
const STATUS_LABEL = {
  published: 'Publicado', draft: 'Rascunho', cancelled: 'Cancelado', finished: 'Encerrado',
}

/** Segunda camada: cabeçalho fixo do evento + abas (spec 009). */
export default function EventoLayout() {
  const { data: event, isLoading } = useAdminEvent()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { run, error, setError, busy } = useApiAction()
  const [editing, setEditing] = useState(false)
  const [hideChrome, setHideChrome] = useState(false) // ficha do cliente esconde as abas

  if (isLoading || !event) return <p className="text-secondary">Carregando…</p>

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'event'] })

  const enviarBanner = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const data = new FormData()
    data.append('banner', file)
    run(() => apiUpload(`/admin/events/${event.id}/banner`, data), { onSuccess: refresh })
  }

  const cancelar = () => {
    const reason = window.prompt('Motivo do cancelamento do evento:')
    if (!reason) return
    run(() => apiPost(`/admin/events/${event.id}/cancel`, { reason }), { onSuccess: refresh })
  }

  const toggleVisibilidade = () => run(
    () => apiPost(`/admin/events/${event.id}/visibility`, { visible: !event.visibleOnSite }),
    { onSuccess: refresh },
  )

  return (
    <EventoUI.Provider value={{ hideChrome, setHideChrome }}>
      {!hideChrome && (
        <>
          <div className="card mb-3">
            <div className="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div className="d-flex align-items-center gap-3">
                <button className="btn btn-sm" onClick={() => navigate('/painel/eventos')}>← Voltar</button>
                <h2 className="mb-0">{event.name}</h2>
                <span className={`badge ${STATUS_BADGE[event.status] ?? 'bg-secondary'} text-white`}>
                  {STATUS_LABEL[event.status] ?? event.status}
                </span>
              </div>
              <div className="btn-list">
                <button className={`btn btn-sm ${event.visibleOnSite ? 'btn-success' : 'btn-outline-secondary'}`}
                  disabled={busy} onClick={toggleVisibilidade}>
                  {event.visibleOnSite ? '● Mostrando no site' : '○ Oculto do site'}
                </button>
                <button className="btn btn-sm" onClick={() => setEditing(true)}>Editar</button>
                <label className="btn btn-sm mb-0">
                  Adicionar banner
                  <input type="file" hidden accept="image/jpeg,image/png,image/webp" onChange={enviarBanner} />
                </label>
                {event.status !== 'cancelled' && (
                  <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={cancelar}>
                    Cancelar evento
                  </button>
                )}
              </div>
            </div>
            {event.bannerUrl
              ? <img src={event.bannerUrl} alt="Banner" className="card-img-bottom" style={{ maxHeight: 160, objectFit: 'cover' }} />
              : (
                <div className="card-footer text-center text-secondary">
                  Adicionar banner — imagem recomendada 1200 × 480 px (JPG/PNG/WEBP, até 20 MB)
                </div>
              )}
          </div>

          <ApiErrorAlert error={error} onClose={() => setError(null)} />

          <ul className="nav nav-tabs mb-3">
            {TABS.filter((tab) => !tab.when || tab.when(event)).map((tab) => (
              <li className="nav-item" key={tab.to || 'painel'}>
                <NavLink className="nav-link" end={tab.end}
                  to={tab.to ? `/painel/eventos/${event.id}/${tab.to}` : `/painel/eventos/${event.id}`}>
                  {tab.label}
                </NavLink>
              </li>
            ))}
          </ul>
        </>
      )}

      <Outlet />

      {editing && (
        <EventoModal event={event} onClose={() => setEditing(false)}
          onSaved={() => { setEditing(false); refresh() }} />
      )}
    </EventoUI.Provider>
  )
}
