import { useEffect, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Card, ApiErrorAlert, useApiAction } from '../components'
import { apiGet, apiPut } from '../../lib/api'
import SuporteFila from './SuporteFila'

/** Configura o contato de suporte (WhatsApp/e-mail) de um evento. */
function ContatoSuporte() {
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [eventId, setEventId] = useState('')
  const [form, setForm] = useState({ support_whatsapp: '', support_email: '' })
  const [ok, setOk] = useState(false)

  const { data: events = [] } = useQuery({ queryKey: ['admin', 'events'], queryFn: () => apiGet('/admin/events') })

  const selected = events.find((e) => String(e.id) === String(eventId))

  useEffect(() => {
    if (selected) {
      setForm({
        support_whatsapp: selected.supportWhatsapp ?? '',
        support_email: selected.supportEmail ?? '',
      })
      setOk(false)
    }
  }, [eventId]) // eslint-disable-line react-hooks/exhaustive-deps

  const salvar = () => run(
    () => apiPut(`/events/${eventId}`, form),
    { onSuccess: () => { setOk(true); queryClient.invalidateQueries({ queryKey: ['admin', 'events'] }) } }
  )

  return (
    <Card title="Contato de suporte do evento">
      <p className="text-secondary">WhatsApp e e-mail exibidos ao inscrito na área de Suporte.</p>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      {ok && <div className="alert alert-success">Contato de suporte salvo.</div>}
      <div className="row g-2">
        <div className="col-md-4">
          <label className="form-label">Evento</label>
          <select className="form-select" value={eventId} onChange={(e) => setEventId(e.target.value)}>
            <option value="">Selecione…</option>
            {events.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
          </select>
        </div>
        <div className="col-md-4">
          <label className="form-label">WhatsApp</label>
          <input className="form-control" placeholder="+55 27 90000-0000" disabled={!eventId}
            value={form.support_whatsapp}
            onChange={(e) => setForm({ ...form, support_whatsapp: e.target.value })} />
        </div>
        <div className="col-md-4">
          <label className="form-label">E-mail</label>
          <input type="email" className="form-control" placeholder="suporte@evento.org" disabled={!eventId}
            value={form.support_email}
            onChange={(e) => setForm({ ...form, support_email: e.target.value })} />
        </div>
      </div>
      <div className="mt-3">
        <button className="btn btn-primary" disabled={!eventId || busy} onClick={salvar}>Salvar contato</button>
      </div>
    </Card>
  )
}

/** Atendimento centralizado — todos os casos de todos os eventos (spec 010, ajuste). */
export default function Atendimentos() {
  return (
    <>
      <div className="page-header d-print-none mb-3">
        <h2 className="page-title">Atendimento</h2>
        <div className="text-secondary">Todas as solicitações de suporte, de todos os eventos.</div>
      </div>
      <ContatoSuporte />
      <div className="mt-3">
        <SuporteFila />
      </div>
    </>
  )
}
