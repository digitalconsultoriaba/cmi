import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney } from '../lib/money'
import { parseApiError } from '../lib/forms'

const METHOD_LABEL = { pix: 'Pix', boleto: 'Boleto', card: 'Cartão' }

/** Casca com header da marca (estilo Tabler). */
function Shell({ children }) {
  return (
    <div className="page" data-bs-theme="light">
      <header className="navbar navbar-expand-md d-print-none" style={{ background: '#fff', borderBottom: '1px solid #e6e7e9' }}>
        <div className="container-xl">
          <Link to="/"><img src="/logo.png" alt="CMI · GLMEES" height="40"
            style={{ background: '#fff', borderRadius: 8, padding: 3 }} /></Link>
        </div>
      </header>
      <div className="page-body"><div className="container-xl">{children}</div></div>
    </div>
  )
}

function CopyButton({ value, label = 'Copiar' }) {
  const [copied, setCopied] = useState(false)

  return (
    <button type="button" className="btn btn-sm"
      onClick={async () => {
        await navigator.clipboard.writeText(value)
        setCopied(true)
        setTimeout(() => setCopied(false), 2000)
      }}>
      {copied ? 'Copiado!' : label}
    </button>
  )
}

/** Tokenização local do fake: o número NUNCA sai do navegador. */
function tokenizeFake(number) {
  const digits = number.replace(/\D/g, '')
  if (digits === '4000000000000002') return 'tok_declined_test'
  return `tok_ok_${digits.slice(-4) || '4242'}`
}

export default function PagarPedido() {
  const { code } = useParams()
  const [tab, setTab] = useState(null)
  const [payment, setPayment] = useState(null)
  const [error, setError] = useState(null)
  const [pending, setPending] = useState(false)
  const [card, setCard] = useState({ number: '', name: '', expiry: '', cvv: '', installments: 1 })

  const { data: order } = useQuery({
    queryKey: ['order', code],
    queryFn: () => apiGet(`/orders/${code}`),
  })

  const { data: event } = useQuery({
    queryKey: ['public', 'event', order?.event?.slug],
    queryFn: () => apiGet(`/public/events/${order.event.slug}`),
    enabled: !!order?.event?.slug,
  })

  // Polling: enquanto pendente com cobrança criada, verifica a cada 3s
  const { data: status } = useQuery({
    queryKey: ['payment-status', code],
    queryFn: () => apiGet(`/orders/${code}/payment-status`),
    enabled: !!payment || order?.status === 'pending',
    refetchInterval: (query) => (query.state.data?.status === 'pending' ? 3000 : false),
  })

  if (!order || !event) return <Shell><p className="text-secondary">Carregando…</p></Shell>

  const isPaid = status?.status === 'paid' || order.status === 'paid'

  if (isPaid) {
    return (
      <Shell>
        <div className="empty">
          <div className="empty-icon" style={{ fontSize: '3rem' }}>🎉</div>
          <p className="empty-title">Pagamento confirmado!</p>
          <p className="empty-subtitle text-secondary">
            Seus ingressos estão confirmados — enviamos um e-mail com os detalhes.
          </p>
          <div className="empty-action">
            <Link className="btn btn-primary btn-lg" to="/minha-conta/ingressos">Ver meus ingressos</Link>
          </div>
        </div>
      </Shell>
    )
  }

  if (order.status !== 'pending') {
    return (
      <Shell>
        <div className="empty">
          <p className="empty-title">Este pedido não está aguardando pagamento.</p>
          <div className="empty-action"><Link className="btn" to="/minha-conta/pedidos">Ver meus pedidos</Link></div>
        </div>
      </Shell>
    )
  }

  const methods = [
    event.allowPix !== false && 'pix',
    event.allowBoleto !== false && 'boleto',
    event.allowCard !== false && 'card',
  ].filter(Boolean)

  const createCharge = async (method, body = {}) => {
    setError(null)
    setPending(true)
    try {
      const data = await apiPost(`/orders/${code}/checkout/${method}`, body)
      setPayment(data)
      return data
    } catch (err) {
      setError(parseApiError(err))
      return null
    } finally {
      setPending(false)
    }
  }

  const payCard = async () => {
    const token = tokenizeFake(card.number) // número nunca vai ao backend
    const result = await createCharge('card', { token, installments: Number(card.installments) })
    if (result?.status === 'paid') setPayment(result)
  }

  return (
    <Shell>
      <div className="row row-cards">
        {/* Resumo do pedido (estilo pay.html) */}
        <div className="col-lg-4 order-lg-2">
          <div className="card">
            <div className="card-header"><h3 className="card-title">Resumo do pedido</h3></div>
            <div className="card-body">
              <div className="d-flex justify-content-between mb-2">
                <span className="text-secondary">Pedido</span><code>{order.code}</code>
              </div>
              <div className="d-flex justify-content-between mb-2">
                <span className="text-secondary">Evento</span><span>{event.name}</span>
              </div>
              <hr />
              <div className="d-flex justify-content-between align-items-center">
                <span className="h3 mb-0">Total</span>
                <span className="h2 mb-0 text-primary">{formatMoney(order.totalAmount)}</span>
              </div>
              {order.reservedUntil && (
                <div className="alert alert-warning mt-3 mb-0 py-2 small">
                  Reserva garantida até {new Date(order.reservedUntil).toLocaleString('pt-BR')}.
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Métodos de pagamento */}
        <div className="col-lg-8 order-lg-1">
          <h2 className="mb-3">Pagamento</h2>

          <div className="btn-group mb-3">
            {methods.map((method) => (
              <button key={method} type="button"
                className={`btn ${tab === method ? 'btn-primary' : 'btn-outline-primary'}`}
                onClick={() => { setTab(method); setPayment(null); setError(null) }}>
                {METHOD_LABEL[method]}
              </button>
            ))}
          </div>

          {error && <div className="alert alert-danger">{error.message}</div>}
          {!tab && <div className="card"><div className="card-body text-secondary">Escolha uma forma de pagamento acima.</div></div>}

      {tab === 'pix' && (
        <div className="card"><div className="card-body text-center">
          {!payment ? (
            <button className="btn btn-primary btn-lg" disabled={pending}
              onClick={() => createCharge('pix')}>
              {pending ? 'Gerando…' : 'Gerar QR code Pix'}
            </button>
          ) : (
            <>
              <div dangerouslySetInnerHTML={{ __html: payment.pixQrCodeSvg }} />
              <p className="mt-3 mb-1">Ou copie o código:</p>
              <div className="d-flex gap-2 justify-content-center align-items-center">
                <code className="text-truncate" style={{ maxWidth: 380 }}>{payment.pixQrCode}</code>
                <CopyButton value={payment.pixQrCode} />
              </div>
              <p className="text-secondary mt-3">
                <span className="spinner-border spinner-border-sm me-2" />
                Aguardando o pagamento — a tela atualiza sozinha.
              </p>
            </>
          )}
        </div></div>
      )}

      {tab === 'boleto' && (
        <div className="card"><div className="card-body">
          {!payment ? (
            <button className="btn btn-primary btn-lg" disabled={pending}
              onClick={() => createCharge('boleto')}>
              {pending ? 'Gerando…' : 'Gerar boleto'}
            </button>
          ) : (
            <>
              <h3>Linha digitável</h3>
              <div className="d-flex gap-2 align-items-center mb-3">
                <code>{payment.boletoLine}</code>
                <CopyButton value={payment.boletoLine} />
              </div>
              {payment.pixQrCodeSvg && (
                <>
                  <h3>Ou pague na hora pelo Pix</h3>
                  <div className="text-center" dangerouslySetInnerHTML={{ __html: payment.pixQrCodeSvg }} />
                </>
              )}
              <p className="text-secondary mt-3">
                Vencimento: {payment.dueDate ? new Date(payment.dueDate).toLocaleDateString('pt-BR') : '—'}.
                Enviamos os dados por e-mail; a confirmação do boleto acontece na compensação.
              </p>
            </>
          )}
        </div></div>
      )}

      {tab === 'card' && (
        <div className="card"><div className="card-body">
          <p className="text-secondary">
            Ambiente de testes: use <code>4242 4242 4242 4242</code> para aprovar ou{' '}
            <code>4000 0000 0000 0002</code> para simular recusa. Os dados do cartão
            não saem do seu navegador.
          </p>
          <div className="row g-2">
            <div className="col-md-6">
              <label className="form-label">Número do cartão</label>
              <input className="form-control" inputMode="numeric" value={card.number}
                onChange={(e) => setCard({ ...card, number: e.target.value })} />
            </div>
            <div className="col-md-6">
              <label className="form-label">Nome impresso</label>
              <input className="form-control" value={card.name}
                onChange={(e) => setCard({ ...card, name: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Validade</label>
              <input className="form-control" placeholder="MM/AA" value={card.expiry}
                onChange={(e) => setCard({ ...card, expiry: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label">CVV</label>
              <input className="form-control" inputMode="numeric" maxLength={4} value={card.cvv}
                onChange={(e) => setCard({ ...card, cvv: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Parcelas</label>
              <select className="form-select" value={card.installments}
                onChange={(e) => setCard({ ...card, installments: e.target.value })}>
                {Array.from({ length: 12 }, (_, i) => i + 1).map((n) => (
                  <option key={n} value={n}>{n}× de {formatMoney(String(Number(order.totalAmount) / n))}</option>
                ))}
              </select>
            </div>
          </div>
          <button className="btn btn-primary btn-lg mt-3" disabled={pending || !card.number}
            onClick={payCard}>
            {pending ? 'Processando…' : `Pagar ${formatMoney(order.totalAmount)}`}
          </button>
        </div></div>
      )}

          <p className="mt-3"><Link to="/minha-conta/pedidos">← Meus pedidos</Link></p>
        </div>
      </div>
    </Shell>
  )
}
