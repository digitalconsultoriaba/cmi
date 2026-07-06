import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../../../lib/api'

/** Relatório de presença por dia + consolidado + individual (spec 012). */
export default function PresencaPorDia() {
  const { eventId } = useParams()
  const [open, setOpen] = useState(false)
  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'attendance-report'],
    queryFn: () => apiGet(`/admin/events/${eventId}/attendance-report`),
  })

  if (!data) return null

  return (
    <div className="card mb-3">
      <div className="card-header">
        <h3 className="card-title">Presença por dia</h3>
        <div className="card-actions text-secondary">{data.totalRegistered} inscrito(s)</div>
      </div>
      <div className="card-body">
        <div className="row row-cards mb-3">
          {data.byDay.map((d) => (
            <div className="col-sm-6 col-lg-3" key={d.dayNumber}>
              <div className="card card-sm"><div className="card-body">
                <div className="subheader">Dia {d.dayNumber}{d.label ? ` · ${d.label}` : ''}</div>
                <div className="h2 mb-0 text-green">{d.present} <span className="text-secondary fs-4">/ {data.totalRegistered}</span></div>
                <div className="text-secondary small">{d.presentPct}% presença · {d.absent} ausente(s)</div>
              </div></div>
            </div>
          ))}
        </div>

        <div className="d-flex gap-4 mb-2">
          <div><div className="subheader">Todos os dias</div><div className="h3 text-green">{data.consolidated.allDays}</div></div>
          <div><div className="subheader">Parcial</div><div className="h3 text-orange">{data.consolidated.partial}</div></div>
          <div><div className="subheader">Nenhum dia</div><div className="h3 text-red">{data.consolidated.none}</div></div>
        </div>

        <button className="btn btn-sm" onClick={() => setOpen(!open)}>
          {open ? 'Ocultar' : 'Ver'} detalhe por participante
        </button>

        {open && (
          <div className="table-responsive mt-2">
            <table className="table table-vcenter">
              <thead>
                <tr><th>Participante</th><th>Tipo</th>
                  {data.days.map((d) => <th key={d.dayNumber}>Dia {d.dayNumber}</th>)}</tr>
              </thead>
              <tbody>
                {data.individual.map((p) => (
                  <tr key={p.code}>
                    <td>{p.participantName}</td>
                    <td className="text-secondary">{p.ticketTypeName}</td>
                    {p.days.map((d) => (
                      <td key={d.dayNumber}>
                        {d.present
                          ? <span className="badge bg-green-lt" title={`${d.checkedInAt ? new Date(d.checkedInAt).toLocaleString('pt-BR') : ''}${d.operator ? ' · ' + d.operator : ''}`}>Sim</span>
                          : <span className="badge bg-secondary-lt">Não</span>}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
