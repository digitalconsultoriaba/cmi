import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Html5Qrcode } from 'html5-qrcode'
import { apiGet, apiPost } from '../../lib/api'
import { parseApiError } from '../../lib/forms'
import { useAuth } from '../../auth/AuthProvider'

const RESULT_MS = 2500
const DEBOUNCE_MS = 5000

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
          {result.data.companionName && (
            <p className="fs-2 mb-1">+ {result.data.companionName}</p>
          )}
          <p className="fs-3 mb-0">
            {result.data.ticketTypeName}
            {result.data.seats === 2 && ' — 2 pessoas'}
          </p>
        </>
      ) : (
        <>
          <h1 className="text-white display-6 mb-1">{result.message}</h1>
          {result.context?.usedAt && (
            <p className="fs-3 mb-0">
              Utilizado em {new Date(result.context.usedAt).toLocaleString('pt-BR')}
              {result.context.validatedBy && <> por {result.context.validatedBy}</>}
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
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const [tab, setTab] = useState('leitor')
  const [manual, setManual] = useState('')
  const [result, setResult] = useState(null)
  const [cameraOn, setCameraOn] = useState(false)
  const [cameraError, setCameraError] = useState(null)
  const [search, setSearch] = useState('')

  const scannerRef = useRef(null)
  const lastScanRef = useRef({ code: null, at: 0 })
  const busyRef = useRef(false)

  const { data: attendance } = useQuery({
    queryKey: ['gate', 'attendance', search],
    queryFn: () => apiGet(`/gate/attendance${search ? `?search=${encodeURIComponent(search)}` : ''}`),
    enabled: tab === 'presencas',
  })

  const validate = async (rawCode) => {
    const code = rawCode.trim().toUpperCase()
    if (!code || busyRef.current) return

    // Debounce: o mesmo QR enquadrado não dispara rajada
    const now = Date.now()
    if (lastScanRef.current.code === code && now - lastScanRef.current.at < DEBOUNCE_MS) return
    lastScanRef.current = { code, at: now }

    busyRef.current = true
    try {
      const data = await apiPost('/gate/checkin', { code })
      setResult({ ok: true, data })
    } catch (err) {
      const parsed = parseApiError(err)
      setResult({
        ok: false,
        message: parsed.status ? parsed.message : 'Falha de rede — nada foi registrado.',
        context: parsed.fields,
      })
    } finally {
      queryClient.invalidateQueries({ queryKey: ['gate'] })
      setTimeout(() => {
        setResult(null)
        busyRef.current = false
      }, RESULT_MS)
    }
  }

  useEffect(() => {
    if (!cameraOn) return undefined

    const scanner = new Html5Qrcode('qr-reader')
    scannerRef.current = scanner
    setCameraError(null)

    scanner.start(
      { facingMode: 'environment' },
      { fps: 8, qrbox: { width: 240, height: 240 } },
      (decoded) => validate(decoded),
      () => {} // erros de frame são normais — ignorar
    ).catch((err) => {
      setCameraError('Não foi possível acessar a câmera ('+err+'). Use a digitação manual.')
      setCameraOn(false)
    })

    return () => {
      scanner.stop().then(() => scanner.clear()).catch(() => {})
      scannerRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cameraOn])

  return (
    <>
      <ResultOverlay result={result} />

      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="mb-0">Check-in</h2>
        <div className="btn-group">
          <button className={`btn btn-sm ${tab === 'leitor' ? 'btn-primary' : 'btn-outline-primary'}`}
            onClick={() => setTab('leitor')}>Leitor</button>
          <button className={`btn btn-sm ${tab === 'presencas' ? 'btn-primary' : 'btn-outline-primary'}`}
            onClick={() => setTab('presencas')}>Presenças</button>
        </div>
      </div>

      {tab === 'leitor' && (
        <div className="card"><div className="card-body text-center">
          {cameraError && <div className="alert alert-warning">{cameraError}</div>}

          <div id="qr-reader" style={{ maxWidth: 360, margin: '0 auto' }} />

          <button className="btn btn-primary my-3" onClick={() => setCameraOn(!cameraOn)}>
            {cameraOn ? 'Desligar câmera' : 'Ligar câmera'}
          </button>

          <p className="text-secondary mb-1">Ou digite o código do ingresso:</p>
          <form className="d-flex gap-2 justify-content-center"
            onSubmit={(e) => { e.preventDefault(); validate(manual); setManual('') }}>
            <input className="form-control" style={{ maxWidth: 260 }}
              placeholder="TCK-..." value={manual}
              onChange={(e) => setManual(e.target.value.toUpperCase())} />
            <button type="submit" className="btn btn-success" disabled={!manual.trim()}>
              Validar
            </button>
          </form>
        </div></div>
      )}

      {tab === 'presencas' && attendance && (
        <>
          <div className="row g-2 mb-3 text-center">
            {[
              ['Esperados', attendance.expectedPeople, 'bg-blue-lt'],
              ['Presentes', attendance.presentPeople, 'bg-green-lt'],
              ['Ausentes', attendance.absentPeople, 'bg-orange-lt'],
            ].map(([label, value, bg]) => (
              <div className="col-4" key={label}>
                <div className={`card ${bg}`}><div className="card-body p-2">
                  <div className="display-6">{value}</div>
                  <div>{label}</div>
                </div></div>
              </div>
            ))}
          </div>

          <div className="d-flex gap-2 mb-2">
            <input className="form-control" placeholder="Buscar por nome, acompanhante ou código…"
              value={search} onChange={(e) => setSearch(e.target.value)} />
            {user?.roles.includes('admin') && (
              <a className="btn" href="/api/admin/reports/attendance.xlsx">.xlsx</a>
            )}
          </div>

          <div className="card"><div className="card-body p-0">
            <table className="table table-vcenter mb-0">
              <thead><tr><th>Participante</th><th>Tipo</th><th>Situação</th></tr></thead>
              <tbody>
                {attendance.tickets.map((ticket) => (
                  <tr key={ticket.code}>
                    <td>
                      {ticket.participantName}
                      {ticket.companionName && (
                        <div className="text-secondary small">+ {ticket.companionName}</div>
                      )}
                      <code className="small">{ticket.code}</code>
                    </td>
                    <td>{ticket.ticketTypeName}{ticket.seats === 2 && ' (2p)'}</td>
                    <td>
                      {ticket.status === 'used' ? (
                        <>
                          <span className="badge bg-green-lt">presente</span>
                          <div className="text-secondary small">
                            {new Date(ticket.usedAt).toLocaleTimeString('pt-BR')}
                            {ticket.validatedBy && <> · {ticket.validatedBy}</>}
                          </div>
                        </>
                      ) : (
                        <span className="badge bg-secondary-lt">ausente</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        </>
      )}
    </>
  )
}
