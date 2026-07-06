import { useEffect, useRef, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Html5Qrcode } from 'html5-qrcode'
import { apiGet, apiPost } from '../../lib/api'
import { parseApiError } from '../../lib/forms'
import { useAuth } from '../../auth/AuthProvider'
import DiasEvento from '../eventos/abas/checkin/DiasEvento'

const RESULT_MS = 2500
const DEBOUNCE_MS = 5000
const todayISO = () => new Date().toISOString().slice(0, 10)

function ResultOverlay({ result }) {
  if (!result) return null
  const ok = result.ok
  return (
    <div style={{
      position: 'fixed', inset: 0, zIndex: 1050,
      background: ok ? '#2fb344' : '#d63939', color: 'white',
      display: 'flex', flexDirection: 'column', alignItems: 'center',
      justifyContent: 'center', textAlign: 'center', padding: '2rem',
    }}>
      <div style={{ fontSize: '5rem', lineHeight: 1 }}>{ok ? '✓' : '✕'}</div>
      {ok ? (
        <>
          <h1 className="text-white display-6 mb-1">{result.data.participantName}</h1>
          {result.data.companionName && <p className="fs-2 mb-1">+ {result.data.companionName}</p>}
          <p className="fs-3 mb-0">
            {result.data.ticketTypeName}{result.data.seats === 2 && ' — 2 pessoas'}
            {result.data.dayNumber && <> · Dia {result.data.dayNumber}</>}
          </p>
        </>
      ) : (
        <>
          <h1 className="text-white display-6 mb-1">{result.message}</h1>
          {result.context?.checkedInAt && (
            <p className="fs-3 mb-0">
              Check-in em {new Date(result.context.checkedInAt).toLocaleString('pt-BR')}
              {result.context.operator && <> por {result.context.operator}</>}
            </p>
          )}
          {result.context?.transferredToCode && (
            <p className="fs-3 mb-0">Ingresso válido: <code className="text-white">{result.context.transferredToCode}</code></p>
          )}
        </>
      )}
    </div>
  )
}

export default function Checkin() {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const isAdmin = user?.roles.includes('admin')
  const [manual, setManual] = useState('')
  const [result, setResult] = useState(null)
  const [cameraOn, setCameraOn] = useState(false)
  const [cameraError, setCameraError] = useState(null)
  const [search, setSearch] = useState('')
  const [selectedDay, setSelectedDay] = useState('')

  const lastScanRef = useRef({ code: null, at: 0 })
  const busyRef = useRef(false)

  const { data: events = [] } = useQuery({ queryKey: ['gate', 'events'], queryFn: () => apiGet('/gate/events') })
  const ev = events.find((e) => String(e.id) === String(eventId))
  const days = ev?.days ?? []

  useEffect(() => {
    if (selectedDay || days.length === 0) return
    setSelectedDay(String((days.find((d) => d.date === todayISO()) ?? days[0]).id))
  }, [days, selectedDay])

  const { data: attendance } = useQuery({
    queryKey: ['gate', 'attendance', eventId, selectedDay, search],
    queryFn: () => {
      const p = new URLSearchParams({ event: eventId, day: selectedDay })
      if (search) p.set('search', search)
      return apiGet(`/gate/attendance?${p}`)
    },
    enabled: !!selectedDay,
    keepPreviousData: true,
  })

  const selStatus = days.find((d) => String(d.id) === String(selectedDay))?.status
  const dayFinished = selStatus === 'finished' || selStatus === 'blocked'
  const dayLockMsg = selStatus === 'blocked'
    ? 'Dia ainda não liberado. O registro de presença será liberado automaticamente 3 horas antes do horário de início do evento, permitindo o check-in dos participantes durante o período de recepção e entrada.'
    : 'Dia finalizado — não aceita novo check-in.'

  const validate = async (rawCode, origin = 'qr') => {
    const code = rawCode.trim().toUpperCase()
    if (!code || !selectedDay || busyRef.current) return
    const now = Date.now()
    if (lastScanRef.current.code === code && now - lastScanRef.current.at < DEBOUNCE_MS) return
    lastScanRef.current = { code, at: now }

    busyRef.current = true
    try {
      const data = await apiPost('/gate/checkin', { code, day: Number(selectedDay), origin })
      setResult({ ok: true, data })
    } catch (err) {
      const parsed = parseApiError(err)
      setResult({ ok: false, message: parsed.status ? parsed.message : 'Falha de rede — nada foi registrado.', context: parsed.fields })
    } finally {
      queryClient.invalidateQueries({ queryKey: ['gate'] })
      setTimeout(() => { setResult(null); busyRef.current = false }, RESULT_MS)
    }
  }

  const finalizar = (day) => {
    if (!window.confirm(`Finalizar o Dia ${day.dayNumber}? Não será possível novo check-in nele.`)) return
    apiPost(`/gate/days/${day.id}/finalize`).then(() => queryClient.invalidateQueries({ queryKey: ['gate'] }))
  }

  useEffect(() => {
    if (!cameraOn) return undefined
    const scanner = new Html5Qrcode('qr-reader')
    setCameraError(null)
    scanner.start({ facingMode: 'environment' }, { fps: 8, qrbox: { width: 240, height: 240 } }, (d) => validate(d), () => {})
      .catch((err) => { setCameraError('Não foi possível acessar a câmera (' + err + '). Use a digitação manual.'); setCameraOn(false) })
    return () => { scanner.stop().then(() => scanner.clear()).catch(() => {}) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cameraOn])

  return (
    <>
      <ResultOverlay result={result} />

      <div className="d-flex align-items-center flex-wrap gap-2 mb-3">
        <Link className="btn btn-sm" to="/painel/checkin">← Eventos</Link>
        <h2 className="mb-0">Check-in{ev ? ` — ${ev.name}` : ''}</h2>
      </div>

      {/* Dias — o operador escolhe onde está operando */}
      <DiasEvento days={days} selectedId={selectedDay} onSelect={(id) => setSelectedDay(String(id))}
        onFinalize={finalizar} canReopen={isAdmin} busy={busyRef.current} />

      {/* Bloco de check-in */}
      <div className="card mb-3"><div className="card-body text-center">
        {dayFinished && <div className="alert alert-secondary">{dayLockMsg}</div>}
        {cameraError && <div className="alert alert-warning">{cameraError}</div>}
        <div id="qr-reader" style={{ maxWidth: 360, margin: '0 auto' }} />
        <button className="btn btn-primary my-3" onClick={() => setCameraOn(!cameraOn)} disabled={!selectedDay || dayFinished}>
          {cameraOn ? 'Fechar câmera' : 'Ler QR-Code'}
        </button>
        <p className="text-secondary mb-1">Ou digite o código do ingresso:</p>
        <form className="d-flex gap-2 justify-content-center"
          onSubmit={(e) => { e.preventDefault(); validate(manual); setManual('') }}>
          <input className="form-control" style={{ maxWidth: 260 }} placeholder="TCK-..."
            value={manual} onChange={(e) => setManual(e.target.value.toUpperCase())} disabled={dayFinished} />
          <button type="submit" className="btn btn-success" disabled={!manual.trim() || !selectedDay || dayFinished}>Validar</button>
        </form>
      </div></div>

      {/* Presença do dia selecionado */}
      {attendance && (
        <>
          <div className="row g-2 mb-3 text-center">
            {[
              ['Esperados', attendance.expectedPeople, 'bg-blue-lt'],
              ['Presentes', attendance.presentPeople, 'bg-green-lt'],
              ['Ausentes', attendance.absentPeople, 'bg-orange-lt'],
            ].map(([label, value, bg]) => (
              <div className="col-4" key={label}>
                <div className={`card ${bg}`}><div className="card-body p-2">
                  <div className="display-6">{value}</div><div>{label}</div>
                </div></div>
              </div>
            ))}
          </div>

          <div className="card">
            <div className="card-header d-flex align-items-center gap-2">
              <input className="form-control" style={{ maxWidth: 320 }} placeholder="Buscar por nome…"
                value={search} onChange={(e) => setSearch(e.target.value)} />
            </div>
            <div className="card-table table-responsive">
              <table className="table table-vcenter mb-0">
                <thead><tr><th>Participante</th><th>Tipo</th><th>Situação</th><th /></tr></thead>
                <tbody>
                  {attendance.tickets.map((t) => (
                    <tr key={t.code} className={t.present ? 'bg-green-lt' : ''}>
                      <td>{t.participantName}
                        {t.companionName && <div className="text-secondary small">+ {t.companionName}</div>}</td>
                      <td>{t.ticketTypeName}{t.seats === 2 && ' (2p)'}</td>
                      <td>{t.present
                        ? <><span className="badge bg-green-lt">presente</span>
                            <div className="text-secondary small">{t.checkedInAt && new Date(t.checkedInAt).toLocaleTimeString('pt-BR')}{t.operator && ` · ${t.operator}`}</div></>
                        : <span className="badge bg-secondary-lt">ausente</span>}</td>
                      <td className="text-end">
                        {!t.present && !dayFinished && (
                          <button className="btn btn-sm btn-success" onClick={() => validate(t.code, 'manual')}>Registrar presença</button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {attendance.tickets.length === 0 && (
                    <tr><td colSpan={4} className="text-secondary">Nenhum participante no filtro.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </>
  )
}
