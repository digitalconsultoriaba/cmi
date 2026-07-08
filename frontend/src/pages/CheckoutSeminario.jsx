import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney, parseMoney } from '../lib/money'
import ParticipanteForm from './checkout/ParticipanteForm'
import './checkout/checkout.css'
import { IcUsers, IcMail, IcPhone, IcShield, IcTicket, IcGift, IcChevron, IcCompass, IcCheck } from './checkout/icons'

const STEPS = [['participantes', 'Participantes'], ['revisao', 'Revisão'], ['pagamento', 'Pagamento']]
const initials = (name) => (name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase()).join('')

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
  const [step, setStep] = useState('cart') // cart | review | payment | done
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [voucherIdx, setVoucherIdx] = useState(null) // participante do modal de voucher
  const [vcode, setVcode] = useState('')
  const [vmsg, setVmsg] = useState(null) // { ok, text }
  const [vbusy, setVbusy] = useState(false)

  if (isLoading || !config) return <div className="ck"><div className="ck-wrap" style={{ paddingTop: 40 }}>Carregando…</div></div>

  const typeOf = (id) => config.ticketTypes.find((t) => t.id === id)
  // parseMoney devolve string decimal ("250.00"); somamos como número para
  // não cair em concatenação de string no reduce do carrinho.
  const rawPrice = (row) => Number(parseMoney(typeOf(row.ticketTypeId)?.effectivePrice ?? '0') ?? 0)
  const priceOf = (row) => (row.voucherCode ? 0 : rawPrice(row))
  const catLabel = (key) => config.categories.find((c) => c.key === key)?.label ?? ''

  const subtotal = cart.reduce((s, r) => s + rawPrice(r), 0)
  const discounts = cart.reduce((s, r) => s + (r.voucherCode ? rawPrice(r) : 0), 0)
  const total = subtotal - discounts

  const stepIndex = step === 'cart' ? 0 : step === 'review' ? 1 : 2

  const addToCart = (data) => {
    if (editIdx != null) { setCart((c) => c.map((r, i) => (i === editIdx ? { ...r, ...data } : r))); setEditIdx(null) }
    else setCart((c) => [...c, data])
    setAdding(false)
  }
  const remove = (i) => setCart((c) => c.filter((_, j) => j !== i))

  const openVoucher = (i) => { setVoucherIdx(i); setVcode(''); setVmsg(null) }
  const closeVoucher = () => { setVoucherIdx(null); setVcode(''); setVmsg(null) }

  const submitVoucher = async () => {
    const code = vcode.trim()
    if (!code) return
    setVbusy(true); setVmsg(null)
    try {
      const row = cart[voucherIdx]
      const res = await apiPost('/public/vouchers/validate', { event_slug: slug, code, ticket_type_id: row.ticketTypeId })
      if (res.valid) {
        setCart((c) => c.map((r, j) => (j === voucherIdx ? { ...r, voucherCode: code } : r)))
        setVmsg({ ok: true, text: res.message })
        setTimeout(closeVoucher, 900)
      } else {
        setVmsg({ ok: false, text: res.message })
      }
    } catch {
      setVmsg({ ok: false, text: 'Não foi possível validar o voucher. Tente novamente.' })
    } finally { setVbusy(false) }
  }

  const finalize = async () => {
    setError(null); setBusy(true)
    try {
      const res = await apiPost('/public/orders', {
        event_slug: slug,
        buyer: { name: buyer.name, email: buyer.email },
        items: cart.map((r) => ({
          ticket_type_id: r.ticketTypeId, participant_name: r.participantName, participant_email: r.participantEmail,
          whatsapp: r.whatsapp || null, category_key: r.categoryKey, fields: r.fields, voucher_code: r.voucherCode || null,
        })),
      })
      setResult(res)
      setStep(res.payment.required ? 'payment' : 'done')
    } catch (e) {
      setError(e.response?.data?.message || 'Não foi possível finalizar. Verifique os dados.')
    } finally { setBusy(false) }
  }

  return (
    <div className="ck">
      <header className="ck-header">
        <div className="ck-watermark"><IcCompass style={{ color: '#fff' }} /></div>
        <div className="ck-header-inner">
          <div className="ck-header-top">
            <span className="ck-seal"><img src="/logo.png" alt="CMI · GLMEES" /></span>
            <div>
              <div className="ck-title">{config.event.name}</div>
              <div className="ck-subtitle">Complete sua inscrição adicionando um ou mais participantes ao carrinho.</div>
            </div>
          </div>
          <div className="ck-steps">
            {STEPS.map(([key, label], i) => (
              <div key={key} style={{ display: 'flex', alignItems: 'center' }}>
                <div className={`ck-step ${i === stepIndex ? 'active' : ''} ${i < stepIndex ? 'done' : ''}`}>
                  <span className="ck-step-num">{i < stepIndex ? <IcCheck width={15} height={15} /> : i + 1}</span>
                  <span className="ck-step-label">{label}</span>
                </div>
                {i < STEPS.length - 1 && <span className="ck-step-line" />}
              </div>
            ))}
          </div>
        </div>
      </header>

      <div className="ck-wrap">
        {error && <div className="ck-card" style={{ borderColor: '#F2D2D2', background: '#FEF2F2', color: '#B91C1C', marginTop: 20 }}>{error}</div>}

        {(step === 'cart' || step === 'review') && (
          <div className="ck-grid">
            <div>
              {step === 'cart' && adding && (
                <ParticipanteForm config={config} initial={editIdx != null ? cart[editIdx] : null}
                  onSubmit={addToCart} onCancel={() => { setAdding(false); setEditIdx(null) }} />
              )}

              <div className="ck-card">
                <div className="ck-card-head">
                  <span className="ico"><IcUsers /></span>
                  <span className="ck-card-title">{cart.length ? 'Participantes inscritos' : 'Participantes adicionados'}</span>
                </div>
                {cart.length === 0 && (
                  <div className="ck-empty">
                    <div className="ck-empty-ico"><IcUsers width={26} height={26} /></div>
                    <div style={{ fontWeight: 600 }}>Nenhum participante adicionado ainda</div>
                    <div style={{ fontSize: '.88rem' }}>{adding ? 'Preencha o formulário acima e adicione ao carrinho.' : 'Clique abaixo para inscrever os irmãos.'}</div>
                  </div>
                )}
                {cart.length > 0 && (
                  <>
                    <p className="ck-card-sub">Confira os participantes adicionados ao carrinho.</p>
                    {cart.map((r, i) => (
                      <div className="ck-part" key={i}>
                        <div className="ck-avatar">{initials(r.participantName)}</div>
                        <div className="ck-part-body">
                          <div className="ck-part-name">{r.participantName}
                            <span className="ck-badge">{catLabel(r.categoryKey)}</span>
                            {r.voucherCode && <span className="ck-badge ck-badge-green">Gratuito por voucher</span>}
                          </div>
                          <div className="ck-part-meta">
                            {r.fields?.cargo && r.fields.cargo.trim() && <span>Cargo: {r.fields.cargo}</span>}
                            {(r.fields?.loja || r.fields?.potencia) && <span>{r.fields.loja || r.fields.potencia}</span>}
                            {r.participantEmail && <span><IcMail width={15} height={15} /> {r.participantEmail}</span>}
                            {r.whatsapp && <span><IcPhone width={15} height={15} /> {r.whatsapp}</span>}
                          </div>
                          <div className="ck-part-actions">
                            {step === 'cart' && <button className="ck-btn ck-btn-ghost ck-btn-sm" onClick={() => { setEditIdx(i); setAdding(true) }}>Editar</button>}
                            {step === 'cart' && (r.voucherCode
                              ? <button className="ck-btn ck-btn-light ck-btn-sm" onClick={() => setCart((c) => c.map((x, j) => j === i ? { ...x, voucherCode: null } : x))}>Remover voucher</button>
                              : <button className="ck-btn ck-btn-primary ck-btn-sm" onClick={() => openVoucher(i)}><IcGift width={16} height={16} /> Aplicar voucher</button>)}
                            <button className="ck-btn ck-btn-danger ck-btn-sm" onClick={() => remove(i)}>Remover</button>
                          </div>
                        </div>
                        <div className="ck-part-right">
                          <div className={`ck-part-price ${r.voucherCode ? 'free' : ''}`}>{formatMoney(priceOf(r))}</div>
                        </div>
                      </div>
                    ))}
                  </>
                )}
                {step === 'cart' && !adding && (
                  <div className="ck-add" onClick={() => { setEditIdx(null); setAdding(true) }}>
                    <span className="ck-add-plus">+</span>
                    <span><span className="ck-add-tt d-block">{cart.length ? 'Adicionar outro irmão' : 'Adicionar participante'}</span>
                      <span className="ck-add-sub">Inclua {cart.length ? 'mais participantes' : 'os participantes'} na sua inscrição</span></span>
                    <span className="ck-add-chev"><IcChevron /></span>
                  </div>
                )}
              </div>

              <div className="ck-card">
                <div className="ck-secure">
                  <span className="ico"><IcShield /></span>
                  <div><div className="tt">Inscrição segura</div>
                    <div className="sub">Seus dados estão protegidos e sua inscrição só é confirmada após a finalização.</div></div>
                </div>
              </div>
            </div>

            {/* Resumo lateral */}
            <div className="ck-summary">
              <div className="ck-card">
                <div className="ck-card-head"><span className="ico"><IcTicket /></span><span className="ck-card-title">Resumo da inscrição</span></div>
                <p className="ck-card-sub">Veja o resumo da sua inscrição.</p>
                <div className="ck-sum-row"><span className="muted">Inscrições</span><span>{cart.length} {cart.length === 1 ? 'inscrito' : 'inscritos'}</span></div>
                <div className="ck-sum-row"><span className="muted">Subtotal</span><span>{formatMoney(subtotal)}</span></div>
                <div className="ck-sum-row"><span className="muted">Descontos por voucher</span><span className="ck-sum-green">− {formatMoney(discounts)}</span></div>
                <div className="ck-sum-total"><span className="lbl">Total a pagar</span><span className="val">{formatMoney(total)}</span></div>

                {step === 'cart' ? (
                  <button className="ck-btn ck-btn-primary ck-btn-block" style={{ marginTop: 16 }} disabled={cart.length === 0} onClick={() => setStep('review')}>
                    {cart.length === 0 ? 'Adicione um participante' : 'Revisar inscrição'}
                  </button>
                ) : (
                  <div style={{ marginTop: 14 }}>
                    <label className="ck-label">Nome do comprador</label>
                    <input className="ck-input" style={{ marginBottom: 10 }} value={buyer.name} onChange={(e) => setBuyer({ ...buyer, name: e.target.value })} placeholder="Seu nome" />
                    <label className="ck-label">E-mail do comprador</label>
                    <input type="email" className="ck-input" value={buyer.email} onChange={(e) => setBuyer({ ...buyer, email: e.target.value })} placeholder="voce@email.com" />
                    <button className="ck-btn ck-btn-primary ck-btn-block" style={{ marginTop: 14 }} disabled={busy || !buyer.name || !buyer.email} onClick={finalize}>
                      {total > 0 ? 'Finalizar e pagar' : 'Confirmar inscrição gratuita'}
                    </button>
                    <button className="ck-btn ck-btn-light ck-btn-block" style={{ marginTop: 8 }} onClick={() => setStep('cart')}>Voltar ao carrinho</button>
                  </div>
                )}
              </div>

              <div className="ck-card">
                <div className="ck-voucher-note">
                  <span className="ico"><IcGift width={18} height={18} /></span>
                  <div><div className="tt">Voucher de gratuidade</div>
                    <div className="sub">A gratuidade é aplicada individualmente por inscrição, conforme o participante selecionado.</div></div>
                </div>
              </div>
            </div>
          </div>
        )}

        {step === 'payment' && result && <PagamentoStep slug={slug} order={result.order} onPaid={() => setStep('done')} />}

        {step === 'done' && (
          <div className="ck-card ck-center" style={{ marginTop: 26, padding: '48px 24px' }}>
            <div className="ck-empty-ico" style={{ background: '#E7F7EE', color: '#16A34A', width: 68, height: 68 }}><IcCheck width={32} height={32} /></div>
            <h2 style={{ fontWeight: 800 }}>Inscrição confirmada!</h2>
            <p className="ck-card-sub">Enviamos os ingressos e o acesso por e-mail. Cada participante recebe o seu; o comprador acessa todos.</p>
            <p>Pedido: <strong>{result?.order?.code}</strong></p>
          </div>
        )}
      </div>

      {voucherIdx != null && (
        <div className="ck-modal-bg" onMouseDown={(e) => e.target === e.currentTarget && closeVoucher()}>
          <div className="ck-modal">
            <div className="ck-modal-head">
              <span className="ico"><IcGift width={18} height={18} /></span>
              <span className="tt">Aplicar voucher de gratuidade</span>
              <button className="close" onClick={closeVoucher}>×</button>
            </div>
            <div className="ck-modal-body">
              <p className="text-secondary" style={{ marginTop: 0 }}>
                Participante: <strong>{cart[voucherIdx]?.participantName}</strong>
              </p>
              <label className="ck-label">Código do voucher</label>
              <input className="ck-input" autoFocus placeholder="Ex.: CTY-ABC123"
                value={vcode} onChange={(e) => { setVcode(e.target.value.toUpperCase()); setVmsg(null) }}
                onKeyDown={(e) => e.key === 'Enter' && submitVoucher()} />
              {vmsg && <div className={`ck-msg ${vmsg.ok ? 'ck-msg-ok' : 'ck-msg-err'}`}>{vmsg.text}</div>}
            </div>
            <div className="ck-modal-foot">
              <button className="ck-btn ck-btn-light" onClick={closeVoucher}>Cancelar</button>
              <button className="ck-btn ck-btn-primary" disabled={vbusy || !vcode.trim()} onClick={submitVoucher}>
                {vbusy ? 'Validando…' : 'Aplicar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function PagamentoStep({ slug, order, onPaid }) {
  const [token, setToken] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const pay = async () => {
    setError(null); setBusy(true)
    try {
      await apiPost(`/public/orders/${order.code}/checkout/card`, { token: token || 'tok_ok_4242', installments: 1 })
      onPaid()
    } catch (e) { setError(e.response?.data?.message || 'Pagamento não aprovado.') } finally { setBusy(false) }
  }
  return (
    <div className="ck-card ck-center" style={{ marginTop: 26 }}>
      <div className="ck-card-head" style={{ justifyContent: 'center' }}><span className="ico"><IcTicket /></span><span className="ck-card-title">Pagamento</span></div>
      <p className="ck-card-sub">Pedido {order.code} — total {formatMoney(order.totalAmount)}</p>
      {error && <div style={{ color: '#B91C1C', marginBottom: 10 }}>{error}</div>}
      <label className="ck-label" style={{ textAlign: 'left' }}>Token do cartão</label>
      <input className="ck-input" style={{ marginBottom: 12 }} placeholder="tok_..." value={token} onChange={(e) => setToken(e.target.value)} />
      <button className="ck-btn ck-btn-primary ck-btn-block" disabled={busy} onClick={pay}>Pagar agora</button>
    </div>
  )
}
