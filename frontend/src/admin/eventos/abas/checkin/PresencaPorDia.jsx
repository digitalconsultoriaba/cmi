import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../../../lib/api'

/** Resumo de presença: por dia (multidia) + consolidado + detalhe (spec 012). */
export default function PresencaPorDia() {
  const { eventId } = useParams()
  const [open, setOpen] = useState(false)
  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'attendance-report'],
    queryFn: () => apiGet(`/admin/events/${eventId}/attendance-report`),
  })

  if (!data) return null

  const total = data.totalRegistered
  const multiDay = data.byDay.length > 1
  const pct = (n) => (total ? Math.round((n / total) * 100) : 0)

  return (
    <div className="card mb-3">
      <div className="card-header">
        <h3 className="card-title">Resumo de presença</h3>
        <div className="card-actions text-secondary">{total} inscrito(s)</div>
      </div>
      <div className="card-body">
        {multiDay ? (
          <table className="table table-vcenter mb-3">
            <thead>
              <tr>
                <th>Dia</th>
                <th className="text-end">Presentes</th>
                <th className="text-end">Ausentes</th>
                <th style={{ width: 180 }}>Presença</th>
              </tr>
            </thead>
            <tbody>
              {data.byDay.map((d) => (
                <tr key={d.dayNumber}>
                  <td><strong>Dia {d.dayNumber}</strong>{d.label ? <span className="text-secondary"> · {d.label}</span> : ''}</td>
                  <td className="text-end text-green fw-bold">{d.present}</td>
                  <td className="text-end text-secondary">{d.absent}</td>
                  <td>
                    <div className="d-flex align-items-center gap-2">
                      <div className="progress flex-fill" style={{ height: 6 }}>
                        <div className="progress-bar bg-green" style={{ width: `${d.presentPct}%` }} />
                      </div>
                      <span className="text-secondary small" style={{ minWidth: 34 }}>{d.presentPct}%</span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div className="mb-3">
            <div className="h2 mb-0 text-green">{data.byDay[0]?.present ?? 0}
              <span className="text-secondary fs-4"> / {total}</span></div>
            <div className="text-secondary small">{data.byDay[0]?.presentPct ?? 0}% de presença · {data.byDay[0]?.absent ?? 0} ausente(s)</div>
          </div>
        )}

        {multiDay && (
          <div className="d-flex flex-wrap gap-2 mb-3">
            <span className="badge bg-green-lt">Todos os dias: {data.consolidated.allDays} ({pct(data.consolidated.allDays)}%)</span>
            <span className="badge bg-orange-lt">Parcial: {data.consolidated.partial}</span>
            <span className="badge bg-secondary-lt">Nenhum dia: {data.consolidated.none}</span>
          </div>
        )}

        <button className="btn btn-sm" onClick={() => setOpen(!open)}>
          {open ? 'Ocultar' : 'Ver'} detalhe por participante
        </button>

        {open && (
          <div className="table-responsive mt-2">
            <table className="table table-vcenter">
              <thead>
                <tr><th>Participante</th><th>Tipo</th>
                  {data.days.map((d) => <th key={d.dayNumber} className="text-center">Dia {d.dayNumber}</th>)}</tr>
              </thead>
              <tbody>
                {data.individual.map((p) => (
                  <tr key={p.code}>
                    <td>{p.participantName}</td>
                    <td className="text-secondary">{p.ticketTypeName}</td>
                    {p.days.map((d) => (
                      <td key={d.dayNumber} className="text-center">
                        {d.present
                          ? <span className="badge bg-green-lt" title={`${d.checkedInAt ? new Date(d.checkedInAt).toLocaleString('pt-BR') : ''}${d.operator ? ' · ' + d.operator : ''}`}>Presente</span>
                          : <span className="badge bg-secondary-lt">—</span>}
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
