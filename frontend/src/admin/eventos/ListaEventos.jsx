import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet } from '../../lib/api'
import EventoModal from '../components/EventoModal'
import TiposEventoModal from './TiposEventoModal'

const STATUS_BADGE = {
  published: 'bg-green-lt', draft: 'bg-secondary-lt',
  cancelled: 'bg-red-lt', finished: 'bg-blue-lt',
}
const STATUS_LABEL = {
  published: 'Publicado', draft: 'Rascunho', cancelled: 'Cancelado', finished: 'Encerrado',
}

export default function ListaEventos() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [creating, setCreating] = useState(false)
  const [showTipos, setShowTipos] = useState(false)

  const { data: events = [] } = useQuery({
    queryKey: ['admin', 'events'],
    queryFn: () => apiGet('/admin/events'),
  })

  return (
    <>
    <div className="page-header d-print-none mb-3">
      <div className="page-pretitle">Gestão</div>
      <h2 className="page-title mb-0">Eventos</h2>
    </div>
    <div className="card">
      <div className="card-header">
        <h3 className="card-title">Eventos ({events.length})</h3>
        <div className="card-actions btn-list">
          <button className="btn" onClick={() => setShowTipos(true)}>Tipos</button>
          <button className="btn btn-primary" onClick={() => setCreating(true)}>Novo evento</button>
        </div>
      </div>
      <div className="card-table table-responsive">
        <table className="table table-vcenter">
          <thead>
            <tr><th>Evento</th><th>Tipo</th><th>Data</th><th>Situação</th><th /></tr>
          </thead>
          <tbody>
            {events.map((event) => (
              <tr key={event.id} role="button" onClick={() => navigate(`/painel/eventos/${event.id}`)}>
                <td className="fw-bold">{event.name}</td>
                <td>{event.eventTypeName ?? '—'}</td>
                <td>{event.startsAt ? new Date(event.startsAt).toLocaleString('pt-BR') : '—'}</td>
                <td>
                  <span className={`badge ${STATUS_BADGE[event.status] ?? 'bg-secondary-lt'}`}>
                    {STATUS_LABEL[event.status] ?? event.status}
                  </span>
                </td>
                <td className="text-end text-secondary">›</td>
              </tr>
            ))}
            {events.length === 0 && (
              <tr><td colSpan={5} className="text-secondary">Nenhum evento. Crie o primeiro.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {creating && (
        <EventoModal onClose={() => setCreating(false)}
          onSaved={(res) => {
            setCreating(false)
            queryClient.invalidateQueries({ queryKey: ['admin', 'events'] })
            if (res?.id) navigate(`/painel/eventos/${res.id}`)
          }} />
      )}
    </div>

    {showTipos && <TiposEventoModal onClose={() => setShowTipos(false)} />}
    </>
  )
}
