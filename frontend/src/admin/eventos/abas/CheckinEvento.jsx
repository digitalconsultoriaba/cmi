import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Html5Qrcode } from 'html5-qrcode'
import { apiGet, apiPost } from '../../../lib/api'
import { ApiErrorAlert, Modal, useApiAction } from '../../components'
import { useAuth } from '../../../auth/AuthProvider'
import DiasEvento from './checkin/DiasEvento'

function Stat({ label, value, className = '' }) {
  return (
    <div className="col-6 col-lg-3">
      <div className="card card-sm"><div className="card-body">
        <div className="subheader">{label}</div>
        <div className={`h1 mb-0 ${className}`}>{value}</div>
      </div></div>
    </div>
  )
}

const todayISO = () => new Date().toISOString().slice(0, 10)

export default function CheckinEvento() {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const { run, error, setError, busy } = useApiAction()
  const isAdmin = user?.roles.includes('admin')

  const [code, setCode] = useState('')
  const [search, setSearch] = useState('')
  const [cameraOn, setCameraOn] = useState(false)
  const [cameraError, setCameraError] = useState(null)
  const [selectedDay, setSelectedDay] = useState('')
  const [reopening, setReopening] = useState(null)
  const [reason, setReason] = useState('')
  const lastScanRef = useRef({ code: null, at: 0 })

  const base = `/admin/events/${eventId}`

  const { data: days = [] } = useQuery({
    queryKey: ['admin', 'event', eventId, 'days'],
    queryFn: () => apiGet(`${base}/days`),
  })

  useEffect(() => {
    if (selectedDay || days.length === 0) return
    setSelectedDay(String((days.find((d) => d.date === todayISO()) ?? days[0]).id))
  }, [days, selectedDay])

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'attendance', selectedDay, search],
    queryFn: () => apiGet(`${base}/attendance?day=${selectedDay}${search ? `&search=${encodeURIComponent(search)}` : ''}`),
    enabled: !!selectedDay,
    keepPreviousData: true,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId] })

  const validate = (value, origin = 'qr') => run(
    () => apiPost('/gate/checkin', { code: value, day: Number(selectedDay), origin }),
    { onSuccess: () => { setCode(''); refresh() } }
  )

  const finalizar = (day) => {
    if (!window.confirm(`Finalizar o Dia ${day.dayNumber}? Não será possível novo check-in nele.`)) return
    run(() => apiPost(`${base}/days/${day.id}/finalize`), { onSuccess: refresh })
  }
  const reabrir = () => run(
    () => apiPost(`${base}/days/${reopening.id}/reopen`, { reason }),
    { onSuccess: () => { setReopening(null); setReason(''); refresh() } }
  )

  const onDecoded = (decoded) => {
    const now = Date.now()
    if (lastScanRef.current.code === decoded && now - lastScanRef.current.at < 4000) return
    lastScanRef.current = { code: decoded, at: now }
    validate(decoded)
  }

  useEffect(() => {
    if (!cameraOn) return undefined
    const scanner = new Html5Qrcode('qr-reader-evento')
    setCameraError(null)
    scanner.start({ facingMode: 'environment' }, { fps: 8, qrbox: { width: 240, height: 240 } }, onDecoded, () => {})
      .catch((err) => { setCameraError('Não foi possível acessar a câmera (' + err + '). Use a digitação manual.'); setCameraOn(false) })
    return () => { scanner.stop().then(() => scanner.clear()).catch(() => {}) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cameraOn])

  const counters = data?.counters
  const items = data?.items ?? []

  return (
    <>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      {/* Dias do evento */}
      <DiasEvento days={days} selectedId={selectedDay} onSelect={(id) => setSelectedDay(String(id))}
        onFinalize={finalizar} onReopen={(d) => setReopening(d)} canReopen={isAdmin} busy={busy} />

      {/* Números do dia */}
      {counters && (
        <div className="row row-deck row-cards mb-3">
          <Stat label="Comprados" value={counters.purchased} />
          <Stat label="Presentes" value={counters.present} className="text-green" />
          <Stat label="Ausentes" value={counters.absent} className="text-orange" />
          <Stat label="% presença" value={`${counters.presentPct}%`} className="text-blue" />
        </div>
      )}

      {/* Participantes + validação no cabeçalho */}
      <div className="card">
        <div className="card-header d-flex align-items-center flex-wrap gap-2">
          <h3 className="card-title mb-0 me-2">Participantes</h3>
          <input className="form-control" style={{ flex: '1 1 260px', maxWidth: 460 }}
            placeholder="Buscar por nome ou código…"
            value={search} onChange={(e) => setSearch(e.target.value)} />
          <form className="d-flex ms-auto" onSubmit={(e) => { e.preventDefault(); if (code.trim()) validate(code.trim()) }}>
            <div className="input-group" style={{ minWidth: 320 }}>
              <input className="form-control" placeholder="Código do ingresso"
                value={code} onChange={(e) => setCode(e.target.value)} />
              <button type="button" className="btn" onClick={() => setCameraOn(!cameraOn)}>
                {cameraOn ? 'Fechar' : 'Ler QR'}
              </button>
              <button type="submit" className="btn btn-primary" disabled={busy || !code.trim()}>Validar</button>
            </div>
          </form>
        </div>

        {cameraOn && (
          <div className="card-body text-center border-bottom">
            {cameraError && <div className="alert alert-warning">{cameraError}</div>}
            <div id="qr-reader-evento" style={{ maxWidth: 320, margin: '0 auto' }} />
          </div>
        )}

        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr><th>Participante</th><th>Presença</th><th>Registro</th><th /></tr></thead>
            <tbody>
              {items.map((i) => (
                <tr key={i.code} className={i.present ? 'bg-green-lt' : ''}>
                  <td>{i.participantName}
                    {i.companionName && <span className="text-secondary small"> + {i.companionName}</span>}</td>
                  <td>{i.present
                    ? <span className="badge bg-green-lt">✓ Presente</span>
                    : <span className="badge bg-secondary-lt">Ausente</span>}</td>
                  <td className="small text-secondary">
                    {i.usedAt ? `${new Date(i.usedAt).toLocaleString('pt-BR')}${i.validatedBy ? ` · ${i.validatedBy}` : ''}` : '—'}
                  </td>
                  <td className="text-end">
                    {!i.present && (
                      <button className="btn btn-sm btn-success" disabled={busy}
                        onClick={() => validate(i.code, 'manual')}>Registrar presença</button>
                    )}
                  </td>
                </tr>
              ))}
              {items.length === 0 && <tr><td colSpan={4} className="text-secondary">Nenhum participante no filtro.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {reopening && (
        <Modal title={`Reabrir Dia ${reopening.dayNumber}`} size="sm" onClose={() => setReopening(null)}
          footer={<>
            <button className="btn" onClick={() => setReopening(null)}>Cancelar</button>
            <button className="btn btn-warning" disabled={busy || !reason.trim()} onClick={reabrir}>Reabrir</button>
          </>}>
          <label className="form-label required">Justificativa</label>
          <textarea className="form-control" rows={3} autoFocus value={reason}
            onChange={(e) => setReason(e.target.value)} placeholder="Motivo da reabertura…" />
          <div className="form-hint mt-1">Registra usuário, data/hora e a justificativa no histórico.</div>
        </Modal>
      )}
    </>
  )
}
