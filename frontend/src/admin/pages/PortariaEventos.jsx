import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'

/** Lista de eventos da portaria — clicar leva ao check-in daquele evento. */
export default function PortariaEventos() {
  const navigate = useNavigate()
  const { data: events = [], isLoading } = useQuery({
    queryKey: ['gate', 'events'],
    queryFn: () => apiGet('/gate/events'),
  })

  return (
    <>
      <div className="page-header d-print-none mb-3">
        <div className="page-pretitle">Portaria</div>
        <h2 className="page-title mb-0">Eventos</h2>
        <div className="text-secondary">Escolha o evento para fazer o check-in.</div>
      </div>

      {isLoading && <p className="text-secondary">Carregando…</p>}

      <div className="row row-cards">
        {events.map((ev) => (
          <div className="col-md-6 col-lg-4" key={ev.id}>
            <div className="card" role="button" onClick={() => navigate(`/painel/checkin/${ev.id}`)}>
              <div className="card-body">
                <div className="h3 mb-1">{ev.name}</div>
                <div className="text-secondary">
                  {ev.startsAt ? new Date(ev.startsAt).toLocaleString('pt-BR') : '—'}
                </div>
                <div className="mt-3">
                  <span className="btn btn-primary btn-sm">Iniciar check-in →</span>
                </div>
              </div>
            </div>
          </div>
        ))}
        {!isLoading && events.length === 0 && (
          <p className="text-secondary">Nenhum evento disponível para check-in.</p>
        )}
      </div>
    </>
  )
}
