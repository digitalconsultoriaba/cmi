import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { Card, ApiErrorAlert, Modal, useApiAction } from '../components'
import { apiGet, apiPost, apiPatch } from '../../lib/api'

const STATUS_LABELS = { available: 'Disponível', distributed: 'Distribuído', redeemed: 'Resgatado' }
const STATUS_BADGE = { available: 'bg-green-lt', distributed: 'bg-blue-lt', redeemed: 'bg-purple-lt' }

const REPORT = [
  { key: 'geradas', label: 'Geradas', color: 'bg-blue text-white' },
  { key: 'distribuidas', label: 'Distribuídas', color: 'bg-green text-white' },
  { key: 'resgatadas', label: 'Resgatadas', color: 'bg-purple text-white' },
]

export default function Cortesias() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [quantity, setQuantity] = useState(10)
  const [filter, setFilter] = useState('')
  const [notes, setNotes] = useState({}) // texto ao distribuir (voucher ainda disponível)
  const [noteEdits, setNoteEdits] = useState({}) // edição da anotação após distribuir
  const [reportCat, setReportCat] = useState(null) // categoria aberta no modal

  const eventId = event?.id
  const { data: vouchers = [] } = useQuery({
    queryKey: ['admin', eventId, 'vouchers', filter],
    queryFn: () => apiGet(`/admin/events/${eventId}/courtesy-vouchers${filter ? `?status=${filter}` : ''}`),
    enabled: !!eventId,
  })
  const { data: stats } = useQuery({
    queryKey: ['admin', eventId, 'courtesy-stats'],
    queryFn: () => apiGet(`/admin/events/${eventId}/courtesy-vouchers/stats`),
    enabled: !!eventId,
  })

  if (!event) return <p>Carregando…</p>

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'vouchers'] })
    queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'courtesy-stats'] })
  }

  const gerar = () => run(
    () => apiPost(`/admin/events/${eventId}/courtesy-vouchers`, { quantity: Number(quantity) }),
    { onSuccess: refresh }
  )

  const distribuir = (voucher) => run(
    () => apiPatch(`/admin/events/${eventId}/courtesy-vouchers/${voucher.id}/distribute`, {
      note: notes[voucher.id] ?? null,
    }),
    { onSuccess: refresh }
  )

  const salvarNota = (voucher) => run(
    () => apiPatch(`/admin/events/${eventId}/courtesy-vouchers/${voucher.id}/note`, {
      note: noteEdits[voucher.id] ?? '',
    }),
    {
      onSuccess: () => {
        refresh()
        setNoteEdits((n) => { const c = { ...n }; delete c[voucher.id]; return c })
      },
    }
  )

  return (
    <>
      <h2>Cortesias</h2>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="row row-cards mb-3">
        {REPORT.map((item) => (
          <div className="col-sm-4" key={item.key}>
            <div className="card card-sm" role="button" onClick={() => setReportCat(item.key)}>
              <div className="card-body d-flex align-items-center">
                <span className={`badge ${item.color} me-3`} style={{ fontSize: '1.5rem', padding: '0.5rem 0.9rem' }}>
                  {stats?.counts?.[item.key] ?? 0}
                </span>
                <div>
                  <div className="fw-bold">{item.label}</div>
                  <div className="text-secondary small">clique para ver a lista</div>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>

      {event.allowCourtesy && (
        <Card title="Cortesia automática (regra do evento)">
          <p className="mb-0">
            A cada <strong>{event.courtesyPaidThreshold ?? '—'}</strong> ingressos pagos,{' '}
            <strong>{event.courtesyGrantPerThreshold}</strong> cortesia(s); limite por conta:{' '}
            <strong>{event.courtesyLimitPerAccount ?? 'sem limite'}</strong>.
          </p>
          <small className="form-hint">A regra é editada na tela Evento (seção Comportamento).</small>
        </Card>
      )}

      {event.allowCourtesyVoucher && (
      <Card title="Cortesia por voucher (códigos)"
        actions={
          <span className="d-flex gap-2">
            <input type="number" className="form-control form-control-sm" style={{ width: 90 }}
              min={1} max={500} value={quantity} onChange={(e) => setQuantity(e.target.value)} />
            <button className="btn btn-primary btn-sm" onClick={gerar} disabled={busy}>Gerar</button>
          </span>
        }>
        <div className="mb-2">
          <select className="form-select form-select-sm w-auto" value={filter} onChange={(e) => setFilter(e.target.value)}>
            <option value="">Todas as situações</option>
            {Object.entries(STATUS_LABELS).map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>
        </div>
        <table className="table table-vcenter">
          <thead><tr><th>Código</th><th>Situação</th><th>Anotação</th><th /></tr></thead>
          <tbody>
            {vouchers.map((voucher) => (
              <tr key={voucher.id}>
                <td><code>{voucher.code}</code></td>
                <td><span className={`badge ${STATUS_BADGE[voucher.status]}`}>{STATUS_LABELS[voucher.status]}</span></td>
                <td>
                  {voucher.status === 'available' ? (
                    <input className="form-control form-control-sm" placeholder="Destinatário / observação"
                      value={notes[voucher.id] ?? ''}
                      onChange={(e) => setNotes({ ...notes, [voucher.id]: e.target.value })} />
                  ) : (
                    <div className="d-flex gap-1 align-items-center">
                      <input className="form-control form-control-sm" placeholder="Destinatário / observação"
                        value={noteEdits[voucher.id] ?? voucher.note ?? ''}
                        onChange={(e) => setNoteEdits({ ...noteEdits, [voucher.id]: e.target.value })} />
                      {noteEdits[voucher.id] !== undefined && noteEdits[voucher.id] !== (voucher.note ?? '') && (
                        <button className="btn btn-sm btn-primary" onClick={() => salvarNota(voucher)} disabled={busy}>
                          Salvar
                        </button>
                      )}
                    </div>
                  )}
                </td>
                <td className="text-end">
                  {voucher.status === 'available' && (
                    <button className="btn btn-sm btn-primary" onClick={() => distribuir(voucher)} disabled={busy}>
                      Distribuir
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      )}

      {reportCat && (
        <Modal title={`Cortesias — ${REPORT.find((r) => r.key === reportCat)?.label}`}
          onClose={() => setReportCat(null)}
          footer={<button className="btn" onClick={() => setReportCat(null)}>Fechar</button>}>
          {(() => {
            const people = stats?.people?.[reportCat] ?? []
            if (people.length === 0) return <p className="text-secondary mb-0">Nenhuma cortesia nesta situação.</p>
            return (
              <table className="table table-vcenter">
                <thead><tr>
                  <th>Nome / destinatário</th><th>Situação</th><th>Código</th>
                  <th>Loja</th><th>Potência</th><th>Cargo</th>
                </tr></thead>
                <tbody>
                  {people.map((p, i) => (
                    <tr key={i}>
                      <td className="fw-bold">{p.name ?? '—'}</td>
                      <td>{p.status ? <span className={`badge ${STATUS_BADGE[p.status]}`}>{STATUS_LABELS[p.status]}</span> : '—'}</td>
                      <td>{p.code ? <code>{p.code}</code> : '—'}</td>
                      <td>{p.loja ?? '—'}</td>
                      <td>{p.potencia ?? '—'}</td>
                      <td>{p.cargoLoja ?? p.cargoPotencia ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )
          })()}
        </Modal>
      )}
    </>
  )
}
