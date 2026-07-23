import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../lib/api'
import Loading from '../components/Loading'

// Domínio oficial (fixo de propósito): a pessoa compara com a barra do
// navegador. Um clone em outro domínio não bate com este texto.
const OFFICIAL_DOMAIN = 'cmi.glmees.org.br'

function Row({ k, v, mono }) {
  if (!v) return null
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, padding: '7px 0', fontSize: 14, borderTop: '1px solid #F0F3F8' }}>
      <span style={{ color: '#6B7A90' }}>{k}</span>
      <span style={{ color: '#1F2A44', fontWeight: 600, fontFamily: mono ? 'monospace' : 'inherit', textAlign: 'right', letterSpacing: mono ? '1px' : 0 }}>{v}</span>
    </div>
  )
}

export default function ValidarIngresso() {
  const { code } = useParams()
  const { data, isLoading, isError } = useQuery({
    queryKey: ['verify-ticket', code],
    queryFn: () => apiGet(`/public/tickets/${encodeURIComponent(code)}/verify`),
    retry: false,
  })

  if (isLoading) return <Loading />

  const t = isError ? null : data
  const valid = !!t?.valid
  const used = !!t?.used

  return (
    <div style={{ minHeight: '100vh', background: '#EEF2F8', paddingBottom: 40, fontFamily: 'system-ui, sans-serif' }}>
      <header style={{ background: '#17357A', color: '#fff', padding: '20px 24px' }}>
        <div style={{ maxWidth: 560, margin: '0 auto', display: 'flex', alignItems: 'center', gap: 12 }}>
          <img src="/logo.png" alt="CMI · GLMEES" style={{ height: 44, width: 'auto' }} />
          <div>
            <div style={{ fontWeight: 800, fontSize: 18 }}>Autenticação de ingresso</div>
            <div style={{ color: '#C6D4EF', fontSize: 13 }}>Grande Loja Maçônica do ES</div>
          </div>
        </div>
      </header>

      <div style={{ maxWidth: 560, margin: '0 auto', padding: '0 16px' }}>
        {/* Aviso anti-clonagem — sempre visível, no topo */}
        <div style={{ background: '#FEF7E6', border: '1px solid #E7D08A', borderRadius: 12, padding: '14px 16px', margin: '18px 0', color: '#6B5410', fontSize: 13.5, lineHeight: 1.55 }}>
          🔒 <b>Confira o endereço no navegador.</b> A validação oficial acontece <b>somente</b> em{' '}
          <b>{OFFICIAL_DOMAIN}</b>. Se a barra de endereço mostrar outro domínio, este ingresso pode ser <b>falsificado</b> — não confie nesta tela.
        </div>

        <div style={{ background: '#fff', borderRadius: 16, padding: '28px 24px', textAlign: 'center', boxShadow: '0 4px 16px rgba(23,53,122,.08)' }}>
          {valid ? (
            <>
              <div style={{ width: 72, height: 72, borderRadius: '50%', background: used ? '#FEF3E2' : '#E7F7EE', color: used ? '#B26A00' : '#16A34A', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 38, fontWeight: 700 }}>✓</div>
              <h2 style={{ color: '#17357A', margin: '14px 0 4px', fontWeight: 800 }}>Ingresso autêntico</h2>
              <p style={{ color: '#6B7A90', margin: '0 0 18px', fontSize: 14 }}>
                {used
                  ? 'Este ingresso já foi validado na entrada do evento.'
                  : 'Ingresso emitido pelo sistema oficial do evento.'}
              </p>
              <div style={{ textAlign: 'left' }}>
                <Row k="Participante" v={t.participantName} />
                <Row k="Evento" v={t.eventName} />
                <Row k="Ingresso" v={[t.ticketType, t.lote && `Lote ${t.lote}`].filter(Boolean).join(' · ')} />
                <Row k="Código" v={t.code} mono />
                <Row k="Situação" v={t.statusLabel} />
              </div>
            </>
          ) : (
            <>
              <div style={{ width: 72, height: 72, borderRadius: '50%', background: '#FDECEC', color: '#DC2626', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 38, fontWeight: 700 }}>✕</div>
              <h2 style={{ color: '#B91C1C', margin: '14px 0 4px', fontWeight: 800 }}>Ingresso não reconhecido</h2>
              <p style={{ color: '#6B7A90', margin: '0 0 8px', fontSize: 14 }}>
                Este código não corresponde a um ingresso válido emitido pelo sistema
                {t?.statusLabel ? ` (situação: ${t.statusLabel})` : ''}.
              </p>
              <p style={{ color: '#6B7A90', fontSize: 13 }}>Código consultado: <b>{code}</b></p>
            </>
          )}
        </div>

        <p style={{ textAlign: 'center', color: '#8A96A8', fontSize: 12, marginTop: 18, lineHeight: 1.5 }}>
          Esta página apenas confirma a autenticidade do ingresso — <b>não</b> realiza o check-in.
          A entrada é registrada exclusivamente pela portaria do evento.
        </p>
      </div>
    </div>
  )
}
