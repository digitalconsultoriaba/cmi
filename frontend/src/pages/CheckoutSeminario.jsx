import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney, parseMoney } from '../lib/money'
import ParticipanteForm from './checkout/ParticipanteForm'

export default function CheckoutSeminario() {
  const { slug } = useParams()
  const { data: config, isLoading } = useQuery({
    queryKey: ['checkout-config', slug],
    queryFn: () => apiGet(`/public/events/${slug}/checkout-config`),
  })

  const [cart, setCart] = useState([])
  const [adding, setAdding] = useState(true)
  const [editIdx, setEditIdx] = useState(null)
  const [buyer, setBuyer] = useState({ name: '', email: '' })
  const [step, setStep] = useState('cart') // cart | review | done | payment
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  if (isLoading || !config) return <div className="container py-5">Carregando…</div>

  const typeOf = (id) => config.ticketTypes.find((t) => t.id === id)
  const priceOf = (row) => (row.voucherCode ? 0 : parseMoney(typeOf(row.ticketTypeId)?.effectivePrice ?? '0'))
  const catLabel = (key) => config.categories.find((c) => c.key === key)?.label ?? ''

  const subtotal = cart.reduce((s, r) => s + parseMoney(typeOf(r.ticketTypeId)?.effectivePrice ?? '0'), 0)
  const discounts = cart.reduce((s, r) => s + (r.voucherCode ? parseMoney(typeOf(r.ticketTypeId)?.effectivePrice ?? '0') : 0), 0)
  const total = subtotal - discounts

  const addToCart = (data) => {
    if (editIdx != null) { setCart((c) => c.map((r, i) => (i === editIdx ? { ...r, ...data } : r))); setEditIdx(null) }
    else setCart((c) => [...c, data])
    setAdding(false)
  }
  const remove = (i) => setCart((c) => c.filter((_, j) => j !== i))

  const applyVoucher = async (i) => {
    const code = window.prompt('Código do voucher de gratuidade:')
    if (!code) return
    const row = cart[i]
    const res = await apiPost('/public/vouchers/validate', {
      event_slug: slug, code: code.trim(), ticket_type_id: row.ticketTypeId,
    })
    if (res.valid) setCart((c) => c.map((r, j) => (j === i ? { ...r, voucherCode: code.trim() } : r)))
    alert(res.message)
  }

  const finalize = async () => {
    setError(null); setBusy(true)
    try {
      const res = await apiPost('/public/orders', {
        event_slug: slug,
        buyer: { name: buyer.name, email: buyer.email },
        items: cart.map((r) => ({
          ticket_type_id: r.ticketTypeId,
          participant_name: r.participantName,
          participant_email: r.participantEmail,
          category_key: r.categoryKey,
          fields: r.fields,
          voucher_code: r.voucherCode || null,
        })),
      })
      setResult(res)
      setStep(res.payment.required ? 'payment' : 'done')
    } catch (e) {
      setError(e.response?.data?.message || 'Não foi possível finalizar. Verifique os dados.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="container py-4" style={{ maxWidth: 960 }}>
      <h1 className="h3">{config.event.name}</h1>
      <p className="text-secondary">{config.supportText}</p>

      {error && <div className="alert alert-danger">{error}</div>}

      {step === 'cart' && (
        <div className="row g-4">
          <div className="col-lg-7">
            <h2 className="h5">Participantes inscritos</h2>
            {cart.map((r, i) => (
              <div className="card mb-2" key={i}><div className="card-body py-2">
                <div className="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <strong>{r.participantName}</strong> <span className="text-secondary small">· {catLabel(r.categoryKey)}</span>
                    <div className="small text-secondary">{r.fields?.loja || r.fields?.potencia || ''} {r.participantEmail ? `· ${r.participantEmail}` : ''}</div>
                    <div>{r.voucherCode ? <span className="badge bg-green-lt">Voucher aplicado — R$ 0,00</span> : <>R$ {formatMoney(priceOf(r))}</>}</div>
                  </div>
                  <span className="btn-list">
                    <button className="btn btn-sm" onClick={() => { setEditIdx(i); setAdding(true) }}>Editar</button>
                    {r.voucherCode
                      ? <button className="btn btn-sm btn-outline-secondary" onClick={() => setCart((c) => c.map((x, j) => j === i ? { ...x, voucherCode: null } : x))}>Remover voucher</button>
                      : <button className="btn btn-sm btn-outline-success" onClick={() => applyVoucher(i)}>Aplicar voucher</button>}
                    <button className="btn btn-sm btn-outline-danger" onClick={() => remove(i)}>Remover</button>
                  </span>
                </div>
              </div></div>
            ))}

            {adding
              ? <ParticipanteForm config={config} initial={editIdx != null ? cart[editIdx] : null}
                  onSubmit={addToCart} onCancel={() => { setAdding(false); setEditIdx(null) }} />
              : <button className="btn btn-outline-primary" onClick={() => setAdding(true)}>+ Adicionar outro irmão</button>}
          </div>

          <div className="col-lg-5">
            <div className="card"><div className="card-body">
              <h2 className="h5">Resumo da inscrição</h2>
              <div className="d-flex justify-content-between"><span>Inscrições</span><span>{cart.length}</span></div>
              <div className="d-flex justify-content-between"><span>Subtotal</span><span>R$ {formatMoney(subtotal)}</span></div>
              <div className="d-flex justify-content-between"><span>Descontos (voucher)</span><span>− R$ {formatMoney(discounts)}</span></div>
              <hr />
              <div className="d-flex justify-content-between fw-bold fs-4"><span>Total</span><span>R$ {formatMoney(total)}</span></div>
              <button className="btn btn-primary w-100 mt-3" disabled={cart.length === 0} onClick={() => setStep('review')}>
                Revisar inscrição
              </button>
            </div></div>
          </div>
        </div>
      )}

      {step === 'review' && (
        <div className="row g-4">
          <div className="col-lg-7">
            <h2 className="h5">Confira os participantes</h2>
            {cart.map((r, i) => (
              <div className="card mb-2" key={i}><div className="card-body py-2 d-flex justify-content-between align-items-center">
                <div><strong>{r.participantName}</strong> <span className="text-secondary small">· {catLabel(r.categoryKey)}</span>
                  <div>{r.voucherCode ? <span className="badge bg-green-lt">Gratuito por voucher</span> : `R$ ${formatMoney(priceOf(r))}`}</div></div>
                <button className="btn btn-sm btn-outline-danger" onClick={() => remove(i)}>Excluir</button>
              </div></div>
            ))}
            <button className="btn btn-link px-0" onClick={() => setStep('cart')}>← Voltar ao carrinho</button>
          </div>
          <div className="col-lg-5">
            <div className="card"><div className="card-body">
              <h2 className="h5">Dados do comprador</h2>
              <input className="form-control mb-2" placeholder="Nome" value={buyer.name} onChange={(e) => setBuyer({ ...buyer, name: e.target.value })} />
              <input type="email" className="form-control mb-2" placeholder="E-mail" value={buyer.email} onChange={(e) => setBuyer({ ...buyer, email: e.target.value })} />
              <div className="d-flex justify-content-between fw-bold fs-4 mt-2"><span>Total</span><span>R$ {formatMoney(total)}</span></div>
              <button className="btn btn-primary w-100 mt-3" disabled={busy || !buyer.name || !buyer.email} onClick={finalize}>
                {total > 0 ? 'Finalizar e pagar' : 'Confirmar inscrição gratuita'}
              </button>
            </div></div>
          </div>
        </div>
      )}

      {step === 'payment' && result && <PagamentoStep slug={slug} order={result.order} onPaid={() => setStep('done')} />}

      {step === 'done' && (
        <div className="card"><div className="card-body text-center py-5">
          <h2 className="h4">Inscrição confirmada! 🎉</h2>
          <p className="text-secondary">Enviamos os ingressos e o acesso por e-mail. Cada participante recebe o seu; o comprador acessa todos.</p>
          <p>Pedido: <strong>{result?.order?.code}</strong></p>
        </div></div>
      )}
    </div>
  )
}

/** Etapa de pagamento (cartão) — token do gateway (dev: tok_ok_4242). */
function PagamentoStep({ slug, order, onPaid }) {
  const [token, setToken] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  const pay = async () => {
    setError(null); setBusy(true)
    try {
      await apiPost(`/public/orders/${order.code}/checkout/card`, { token: token || 'tok_ok_4242', installments: 1 })
      onPaid()
    } catch (e) {
      setError(e.response?.data?.message || 'Pagamento não aprovado.')
    } finally { setBusy(false) }
  }

  return (
    <div className="card" style={{ maxWidth: 480 }}><div className="card-body">
      <h2 className="h5">Pagamento</h2>
      <p className="text-secondary">Pedido {order.code} — total R$ {order.totalAmount}</p>
      {error && <div className="alert alert-danger">{error}</div>}
      <label className="form-label">Token do cartão</label>
      <input className="form-control mb-2" placeholder="tok_..." value={token} onChange={(e) => setToken(e.target.value)} />
      <button className="btn btn-primary w-100" disabled={busy} onClick={pay}>Pagar agora</button>
    </div></div>
  )
}
