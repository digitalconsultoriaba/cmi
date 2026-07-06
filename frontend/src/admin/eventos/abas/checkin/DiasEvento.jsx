const STATUS = {
  open: ['Aberto', 'bg-secondary text-white'],
  in_progress: ['Em andamento', 'bg-primary text-white'],
  finished: ['Finalizado', 'bg-dark text-white'],
  blocked: ['Bloqueado', 'bg-warning text-dark'],
}

const isToday = (date) => date === new Date().toISOString().slice(0, 10)

/**
 * Cards dos dias do evento (spec 012). Deixa claro o dia selecionado; destaca
 * o dia de hoje. Ações de finalizar/reabrir opcionais (por papel).
 */
export default function DiasEvento({ days = [], selectedId, onSelect, onFinalize, onReopen, canReopen, busy }) {
  if (days.length === 0) return null

  return (
    <div className="row row-cards mb-3">
      {days.map((d) => {
        const [label, badge] = STATUS[d.status] ?? ['—', 'bg-secondary text-white']
        const selected = String(d.id) === String(selectedId)
        return (
          <div className="col-sm-6 col-lg-4" key={d.id}>
            <div className={`card ${selected ? 'border-primary border-2' : ''}`}
              role="button" onClick={() => onSelect?.(d.id)}>
              <div className="card-body">
                <div className="d-flex align-items-center justify-content-between">
                  <div className="h3 mb-0">
                    Dia {d.dayNumber}
                    {isToday(d.date) && <span className="badge bg-green text-white ms-2">hoje</span>}
                  </div>
                  <span className={`badge ${badge}`}>{label}</span>
                </div>
                <div className="text-secondary">
                  {d.date ? new Date(d.date + 'T00:00').toLocaleDateString('pt-BR') : '—'}
                  {d.label && <> · {d.label}</>}
                </div>
                <div className="text-secondary small mt-1">{d.checkinCount ?? 0} check-in(s)</div>

                <div className="btn-list mt-2" onClick={(e) => e.stopPropagation()}>
                  {selected && <span className="badge bg-primary text-white align-self-center">operando</span>}
                  {onFinalize && d.status !== 'finished' && (
                    <button className="btn btn-sm btn-outline-dark" disabled={busy}
                      onClick={() => onFinalize(d)}>Finalizar dia</button>
                  )}
                  {onReopen && d.status === 'finished' && canReopen && (
                    <button className="btn btn-sm btn-outline-warning" disabled={busy}
                      onClick={() => onReopen(d)}>Reabrir</button>
                  )}
                </div>
              </div>
            </div>
          </div>
        )
      })}
    </div>
  )
}
