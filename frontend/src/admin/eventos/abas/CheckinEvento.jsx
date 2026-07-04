import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Html5Qrcode } from 'html5-qrcode'
import { apiGet, apiPost } from '../../../lib/api'
import { ApiErrorAlert, useApiAction } from '../../components'
import DonutChart from '../../components/DonutChart'

function Stat({ label, value, className = '' }) {
  return (
    <div className="col-6 col-lg-3">
      <div className="card card-sm">
        <div className="card-body">
          <div className="subheader">{label}</div>
          <div className={`h1 mb-0 ${className}`}>{value}</div>
        </div>
      </div>
    </div>
  )
}

export default function CheckinEvento() {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [code, setCode] = useState('')
  const [search, setSearch] = useState('')
  const [cameraOn, setCameraOn] = useState(false)
  const [cameraError, setCameraError] = useState(null)
  const lastScanRef = useRef({ code: null, at: 0 })

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'attendance', search],
    queryFn: () => apiGet(`/admin/events/${eventId}/attendance${search ? `?search=${encodeURIComponent(search)}` : ''}`),
    keepPreviousData: true,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId, 'attendance'] })

  // Presença manual = MESMO ponto de check-in da portaria (spec 009, FR-009)
  const validate = (value) => run(
    () => apiPost('/gate/checkin', { code: value }),
    { onSuccess: () => { setCode(''); refresh() } }
  )

  // Leitura por câmera (html5-qrcode) com debounce de 4s por código repetido
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
    scanner.start(
      { facingMode: 'environment' },
      { fps: 8, qrbox: { width: 240, height: 240 } },
      onDecoded,
      () => {}
    ).catch((err) => {
      setCameraError('Não foi possível acessar a câmera (' + err + '). Use a digitação manual.')
      setCameraOn(false)
    })
    return () => { scanner.stop().then(() => scanner.clear()).catch(() => {}) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cameraOn])

  if (!data) return <p className="text-secondary">Carregando…</p>

  const { counters, presence, items } = data

  return (
    <>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <div className="row row-deck row-cards mb-3">
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Validação de entrada</h3></div>
            <div className="card-body">
              <p className="text-secondary">Leia o QR do ingresso (câmera) ou digite o código e valide a entrada.</p>
              {cameraError && <div className="alert alert-warning">{cameraError}</div>}
              <div id="qr-reader-evento" style={{ maxWidth: 320, margin: cameraOn ? '0 auto 1rem' : 0 }} />
              <form onSubmit={(e) => { e.preventDefault(); if (code.trim()) validate(code.trim()) }}>
                <input className="form-control mb-2" placeholder="Código do ingresso"
                  value={code} onChange={(e) => setCode(e.target.value)} />
                <button type="button" className="btn w-100 mb-2" onClick={() => setCameraOn(!cameraOn)}>
                  📷 {cameraOn ? 'Desligar câmera' : 'Ler QR'}
                </button>
                <button type="submit" className="btn btn-primary w-100" disabled={busy || !code.trim()}>
                  Validar ingresso
                </button>
              </form>
            </div>
          </div>
        </div>
        <div className="col-lg-6">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Presença</h3></div>
            <div className="card-body">
              <DonutChart series={[presence.present, presence.absent]}
                labels={['Presentes', 'Ausentes']} colors={['#2fb344', '#f59f00']} />
            </div>
          </div>
        </div>
      </div>

      <div className="row row-deck row-cards mb-3">
        <Stat label="Comprados" value={counters.purchased} />
        <Stat label="Presentes" value={counters.present} className="text-green" />
        <Stat label="Ausentes" value={counters.absent} className="text-orange" />
        <Stat label="% presença" value={`${counters.presentPct}%`} className="text-blue" />
      </div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">Participantes</h3>
          <div className="card-actions">
            <input className="form-control form-control-sm" placeholder="Buscar por nome…"
              value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
        </div>
        <div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead>
              <tr><th>Participante</th><th>Presença</th><th>Registro</th><th /></tr>
            </thead>
            <tbody>
              {items.map((i) => (
                <tr key={i.code} className={i.present ? 'bg-green-lt' : ''}>
                  <td>
                    {i.participantName}
                    {i.companionName && <span className="text-secondary small"> + {i.companionName}</span>}
                  </td>
                  <td>
                    {i.present
                      ? <span className="badge bg-green-lt">✓ Presente</span>
                      : <span className="badge bg-secondary-lt">Ausente</span>}
                  </td>
                  <td className="small text-secondary">
                    {i.usedAt ? `${new Date(i.usedAt).toLocaleString('pt-BR')}${i.validatedBy ? ` · ${i.validatedBy}` : ''}` : '—'}
                  </td>
                  <td className="text-end">
                    {!i.present && (
                      <button className="btn btn-sm btn-success" disabled={busy}
                        onClick={() => validate(i.code)}>
                        Registrar presença
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {items.length === 0 && (
                <tr><td colSpan={4} className="text-secondary">Nenhum participante no filtro.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
