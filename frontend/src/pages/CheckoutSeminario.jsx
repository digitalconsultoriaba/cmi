import { useState, useEffect } from 'react'
import { useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney, parseMoney } from '../lib/money'
import { loadCheckout, saveCheckout, clearCheckout } from '../lib/checkoutStore'
import Loading from '../components/Loading'
import ParticipanteForm from './checkout/ParticipanteForm'
import './checkout/checkout.css'
import { IcUsers, IcMail, IcPhone, IcShield, IcTicket, IcGift, IcChevron, IcCompass, IcCheck } from './checkout/icons'
import { maskCpfCnpj, maskCep, PhoneField, countryByCode } from './checkout/fields'

const EMPTY_BUYER = {
  name: '', email: '', cpfCnpj: '', phone: '', phoneCountry: 'BR',
  postalCode: '', address: '', addressNumber: '', complement: '', province: '',
}

const STEPS = [['participantes', 'Participantes'], ['revisao', 'Revisão'], ['pagamento', 'Pagamento']]
const initials = (name) => (name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase()).join('')

export default function CheckoutSeminario() {
  const { slug } = useParams()
  const [sp] = useSearchParams()
  // Retorno do checkout hospedado (ASAAS): ?order=CODE&paid=1 (ou cancel/expired).
  const returnedCode = sp.get('order')
  const returnedState = sp.get('paid') ? 'confirming' : (sp.get('cancel') || sp.get('expired')) ? 'payment_failed' : null

  const { data: config, isLoading } = useQuery({
    queryKey: ['checkout-config', slug],
    queryFn: () => apiGet(`/public/events/${slug}/checkout-config`),
  })

  // Retenção do carrinho: restaura do navegador (exceto quando é retorno do ASAAS).
  const [cart, setCart] = useState(() => (returnedCode ? [] : loadCheckout(slug)?.cart ?? []))
  // Se restaurou participantes, começa mostrando a lista (não o formulário).
  const [adding, setAdding] = useState(() => !(!returnedCode && loadCheckout(slug)?.cart?.length))
  const [editIdx, setEditIdx] = useState(null)
  const [buyer, setBuyer] = useState(() => (returnedCode ? EMPTY_BUYER : { ...EMPTY_BUYER, ...(loadCheckout(slug)?.buyer ?? {}) }))
  const [cepBusy, setCepBusy] = useState(false)
  // cart | review | payment | confirming | payment_failed | done
  const [step, setStep] = useState(returnedCode && returnedState ? returnedState : 'cart')
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [voucherIdx, setVoucherIdx] = useState(null) // participante do modal de voucher
  const [vcode, setVcode] = useState('')
  const [vmsg, setVmsg] = useState(null) // { ok, text }
  const [vbusy, setVbusy] = useState(false)

  // Sincroniza o carrinho no navegador. Ao CONCLUIR o pagamento (step 'done'),
  // limpa SEMPRE — inclusive no retorno do ASAAS — para o próximo acesso não vir
  // com os participantes/dados do pedido anterior pré-preenchidos.
  // (Depois dos useState: usa `step`, que precisa estar declarado antes — TDZ.)
  useEffect(() => {
    if (step === 'done') { clearCheckout(slug); return }
    if (returnedCode) return // retorno do ASAAS: não re-salva o carrinho
    saveCheckout(slug, { cart, buyer })
  }, [cart, buyer, step, slug, returnedCode])

  // Retorno pago do ASAAS (?paid=1): compra concluída → limpa o carrinho salvo.
  useEffect(() => {
    if (returnedCode && sp.get('paid')) clearCheckout(slug)
  }, [returnedCode, slug])

  if (isLoading || !config) return <Loading />

  const typeOf = (id) => config.ticketTypes.find((t) => t.id === id)
  // parseMoney devolve string decimal ("250.00"); somamos como número para
  // não cair em concatenação de string no reduce do carrinho.
  const rawPrice = (row) => Number(parseMoney(typeOf(row.ticketTypeId)?.effectivePrice ?? '0') ?? 0)
  const priceOf = (row) => (row.voucherCode ? 0 : rawPrice(row))
  const catLabel = (key) => config.categories.find((c) => c.key === key)?.label ?? ''

  const subtotal = cart.reduce((s, r) => s + rawPrice(r), 0)
  const discounts = cart.reduce((s, r) => s + (r.voucherCode ? rawPrice(r) : 0), 0)
  const total = subtotal - discounts

  // 'done' = 3 para que os três passos (inclusive Pagamento) apareçam com check.
  const stepIndex = step === 'cart' ? 0 : step === 'review' ? 1 : step === 'done' ? 3 : 2

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
    // Pedido já criado (voltou do pagamento sem alterar dados): reaproveita e
    // segue para o pagamento — nunca cria um pedido duplicado.
    if (result) { setStep(result.payment.required ? 'payment' : 'done'); return }
    setError(null); setBusy(true)
    try {
      const res = await apiPost('/public/orders', {
        event_slug: slug,
        buyer: { name: buyer.name, email: buyer.email, document: buyer.cpfCnpj.replace(/\D/g, '') },
        items: cart.map((r) => ({
          ticket_type_id: r.ticketTypeId, participant_name: r.participantName, participant_email: r.participantEmail,
          whatsapp: r.whatsapp ? `+${countryByCode(r.whatsappCountry).dial} ${r.whatsapp}` : null,
          category_key: r.categoryKey, fields: r.fields, voucher_code: r.voucherCode || null,
        })),
      })
      setResult(res)
      setStep(res.payment.required ? 'payment' : 'done')
    } catch (e) {
      setError(e.response?.data?.message || 'Não foi possível finalizar. Verifique os dados.')
    } finally { setBusy(false) }
  }

  const setBuyerField = (k, v) => { setBuyer((b) => ({ ...b, [k]: v })); setResult(null) }

  // Busca endereço pelo CEP (ViaCEP, público) — preenche logradouro e bairro.
  const lookupCep = async (cep) => {
    const digits = (cep || '').replace(/\D/g, '')
    if (digits.length !== 8) return
    setCepBusy(true)
    try {
      const r = await fetch(`https://viacep.com.br/ws/${digits}/json/`)
      const d = await r.json()
      if (!d.erro) setBuyer((b) => ({ ...b, address: d.logradouro || b.address, province: d.bairro || b.province }))
    } catch { /* silencioso: comprador preenche manualmente */ } finally { setCepBusy(false) }
  }

  const digitsLen = (v) => (v || '').replace(/\D/g, '').length
  // CPF é sempre exigido (consta no comprovante/registro do pedido, inclusive na
  // inscrição gratuita). Os demais dados de cobrança só no pedido pago (cartão via ASAAS).
  const cpfOk = digitsLen(buyer.cpfCnpj) >= 11
  const billingOk = cpfOk && (total <= 0 || (
    digitsLen(buyer.phone) >= 10 &&
    digitsLen(buyer.postalCode) === 8 && !!buyer.address && !!buyer.addressNumber && !!buyer.province
  ))
  // Telefone: nacional para BR; DDI + nacional para os demais países.
  const phoneDigits = (buyer.phone || '').replace(/\D/g, '')
  const phoneNumber = buyer.phoneCountry === 'BR' ? phoneDigits : countryByCode(buyer.phoneCountry).dial + phoneDigits
  const customerData = total > 0 ? {
    name: buyer.name, email: buyer.email, cpfCnpj: buyer.cpfCnpj, phoneNumber,
    postalCode: buyer.postalCode, address: buyer.address, addressNumber: buyer.addressNumber,
    ...(buyer.complement ? { complement: buyer.complement } : {}), province: buyer.province,
  } : null

  return (
    <div className="ck">
      <header className="ck-header">
        <div className="ck-watermark"><IcCompass style={{ color: '#fff' }} /></div>
        <div className="ck-header-inner">
          <div style={{ textAlign: 'right', marginBottom: 8 }}>
            <a href="/entrar" style={{ color: '#fff', fontSize: '.85rem', textDecoration: 'underline', opacity: 0.92 }}>
              Já comprou? Acompanhe seus pedidos pelo painel →
            </a>
          </div>
          <div className="ck-header-top">
            <a href="/" className="ck-seal" aria-label="Voltar para a página inicial"><img src="/logo.png" alt="CMI · GLMEES" /></a>
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
                            {r.whatsapp && <span><IcPhone width={15} height={15} /> +{countryByCode(r.whatsappCountry).dial} {r.whatsapp}</span>}
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
                    <input className="ck-input" style={{ marginBottom: 10 }} value={buyer.name} onChange={(e) => setBuyerField('name', e.target.value)} placeholder="Seu nome" />
                    <label className="ck-label">E-mail do comprador</label>
                    <input type="email" className="ck-input" style={{ marginBottom: 10 }} value={buyer.email} onChange={(e) => setBuyerField('email', e.target.value)} placeholder="voce@email.com" />

                    {/* CPF/CNPJ sempre visível — consta no comprovante/registro, mesmo na inscrição gratuita. */}
                    <label className="ck-label">CPF/CNPJ</label>
                    <input className="ck-input" style={{ marginBottom: total > 0 ? 10 : 0 }} value={buyer.cpfCnpj} onChange={(e) => setBuyerField('cpfCnpj', maskCpfCnpj(e.target.value))} placeholder="000.000.000-00" inputMode="numeric" />

                    {total > 0 && (
                      <>
                        <label className="ck-label">Celular</label>
                        <PhoneField country={buyer.phoneCountry} number={buyer.phone}
                          onCountry={(c) => setBuyerField('phoneCountry', c)} onNumber={(n) => setBuyerField('phone', n)} />
                        <label className="ck-label" style={{ marginTop: 10 }}>CEP{cepBusy && <span className="ck-hint"> buscando…</span>}</label>
                        <input className="ck-input" style={{ marginBottom: 10 }} value={buyer.postalCode} onChange={(e) => setBuyerField('postalCode', maskCep(e.target.value))} onBlur={(e) => lookupCep(e.target.value)} placeholder="00000-000" inputMode="numeric" />
                        <label className="ck-label">Endereço</label>
                        <input className="ck-input" style={{ marginBottom: 10 }} value={buyer.address} onChange={(e) => setBuyerField('address', e.target.value)} placeholder="Rua/Avenida" />
                        <label className="ck-label">Número</label>
                        <input className="ck-input" style={{ marginBottom: 10 }} value={buyer.addressNumber} onChange={(e) => setBuyerField('addressNumber', e.target.value)} placeholder="123" />
                        <div className="ck-field-row">
                          <div>
                            <label className="ck-label">Bairro</label>
                            <input className="ck-input" value={buyer.province} onChange={(e) => setBuyerField('province', e.target.value)} placeholder="Bairro" />
                          </div>
                          <div>
                            <label className="ck-label">Complemento <span className="ck-hint">(opcional)</span></label>
                            <input className="ck-input" value={buyer.complement} onChange={(e) => setBuyerField('complement', e.target.value)} placeholder="Apto, bloco…" />
                          </div>
                        </div>
                      </>
                    )}

                    <button className="ck-btn ck-btn-primary ck-btn-block" style={{ marginTop: 14 }} disabled={busy || !buyer.name || !buyer.email || !billingOk} onClick={finalize}>
                      {total > 0 ? 'Finalizar e pagar' : 'Confirmar inscrição gratuita'}
                    </button>
                    <button className="ck-btn ck-btn-light ck-btn-block" style={{ marginTop: 8 }} onClick={() => { setStep('cart'); setResult(null) }}>Voltar ao carrinho</button>
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

        {step === 'payment' && result && <PagamentoStep order={result.order} customerData={customerData} onBack={() => setStep('review')} onPaid={() => setStep('done')} />}

        {step === 'confirming' && <ConfirmacaoStep code={returnedCode || result?.order?.code} onPaid={() => setStep('done')} />}

        {step === 'payment_failed' && (
          <div className="ck-card ck-center" style={{ marginTop: 26, padding: '48px 24px' }}>
            <div className="ck-empty-ico" style={{ background: '#FEF2F2', color: '#B91C1C', width: 68, height: 68, fontSize: 30, fontWeight: 800 }}>!</div>
            <h2 style={{ fontWeight: 800 }}>Pagamento não concluído</h2>
            <p className="ck-card-sub">Seu pagamento foi cancelado ou não foi finalizado. Se a reserva ainda estiver ativa, você pode tentar novamente.</p>
            {returnedCode && <p>Pedido: <strong>{returnedCode}</strong></p>}
            <a className="ck-btn ck-btn-primary" href={`/checkout/${slug}`} style={{ marginTop: 8 }}>Recomeçar inscrição</a>
          </div>
        )}

        {step === 'done' && (
          <div className="ck-card ck-center" style={{ marginTop: 26, padding: '48px 24px' }}>
            <div className="ck-empty-ico" style={{ background: '#E7F7EE', color: '#16A34A', width: 68, height: 68 }}><IcCheck width={32} height={32} /></div>
            <h2 style={{ fontWeight: 800 }}>Inscrição confirmada!</h2>
            <p className="ck-card-sub">Enviamos os ingressos e o acesso por e-mail. Cada participante recebe o seu; o comprador acessa todos.</p>
            <p>Pedido: <strong>{result?.order?.code || returnedCode}</strong></p>
            <div className="ck-actions">
              {(result?.order?.code || returnedCode) && (
                <a className="ck-btn ck-btn-primary" href={`/api/public/orders/${result?.order?.code || returnedCode}/receipt`}>Baixar comprovante</a>
              )}
              <a className="ck-btn ck-btn-light" href="/entrar">Acompanhar meus pedidos</a>
            </div>
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

function PagamentoStep({ order, customerData, onBack, onPaid }) {
  const [method, setMethod] = useState('pix')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [pix, setPix] = useState(null) // cobrança PIX gerada (QR + copia-e-cola)
  const [copied, setCopied] = useState(false)

  const payCard = async () => {
    setError(null); setBusy(true)
    try {
      // Cria o checkout hospedado (ASAAS) e redireciona ao ambiente do provedor.
      // O parcelamento é escolhido lá (com os juros). Aqui só habilitamos a
      // oferta até o máximo — o backend limita ao teto configurado.
      // customerData pré-preenche o cadastro do comprador na página do ASAAS.
      const { redirectUrl } = await apiPost(`/public/orders/${order.code}/checkout/card`, {
        installments: 12,
        ...(customerData ? { customerData } : {}),
      })
      if (redirectUrl) { window.location.href = redirectUrl; return }
      setError('Não foi possível iniciar o pagamento. Tente novamente.')
    } catch (e) {
      setError(e.response?.data?.message || 'Não foi possível iniciar o pagamento.')
    } finally { setBusy(false) }
  }

  const genPix = async () => {
    setError(null); setBusy(true)
    try {
      // Cria a cobrança PIX (via microsserviço) e mostra o QR/copia-e-cola.
      // A baixa chega pelo polling do status (payment-status reconsulta).
      setPix(await apiPost(`/public/orders/${order.code}/checkout/pix`, {}))
    } catch (e) {
      setError(e.response?.data?.message || 'Não foi possível gerar o PIX.')
    } finally { setBusy(false) }
  }

  // Enquanto o QR PIX está na tela, consulta o status até confirmar (~3,5s).
  const { data: statusData } = useQuery({
    queryKey: ['guest-pay-status', order.code],
    queryFn: () => apiGet(`/public/orders/${order.code}/payment-status`),
    enabled: !!pix,
    refetchInterval: (query) => (query.state.data?.status === 'paid' ? false : 3500),
  })
  const paidByPix = statusData?.status === 'paid'
  useEffect(() => { if (paidByPix) onPaid() }, [paidByPix])

  const copyPix = () => {
    navigator.clipboard?.writeText(pix.pixQrCode)
    setCopied(true); setTimeout(() => setCopied(false), 1500)
  }

  return (
    <div className="ck-card" style={{ marginTop: 26, maxWidth: 560, marginInline: 'auto' }}>
      <div className="ck-card-head" style={{ justifyContent: 'center' }}><span className="ico"><IcTicket /></span><span className="ck-card-title">Pagamento</span></div>
      <p className="ck-card-sub" style={{ textAlign: 'center' }}>Pedido {order.code} — total {formatMoney(order.totalAmount)}</p>

      <div className="ck-pay-methods">
        <button type="button" className={`ck-pay-method ${method === 'pix' ? 'active' : ''}`} onClick={() => setMethod('pix')}>
          PIX
        </button>
        <button type="button" className={`ck-pay-method ${method === 'card' ? 'active' : ''}`} disabled={!!pix} onClick={() => setMethod('card')}>
          Cartão de crédito/débito
        </button>
      </div>

      {error && <div style={{ color: '#B91C1C', margin: '12px 0' }}>{error}</div>}

      {method === 'card' && (
        <div style={{ marginTop: 16 }}>
          <button className="ck-btn ck-btn-primary ck-btn-block" disabled={busy || Number(order.totalAmount) < 5} onClick={payCard}>
            {busy ? 'Redirecionando…' : 'Pagar com cartão'}
          </button>
          {Number(order.totalAmount) < 5 ? (
            <p className="ck-card-sub" style={{ textAlign: 'center', marginTop: 10, fontSize: '.82rem', color: '#B91C1C' }}>
              O cartão exige valor mínimo de <b>R$ 5,00</b>. Para este valor, use o <b>PIX</b>.
            </p>
          ) : (
            <p className="ck-card-sub" style={{ textAlign: 'center', marginTop: 10, fontSize: '.82rem' }}>
              Você será direcionado ao ambiente seguro do ASAAS para concluir o pagamento. A opção de parcelar (à vista ou em até 12×, com os juros) é escolhida lá.
            </p>
          )}
        </div>
      )}

      {method === 'pix' && (
        <div style={{ marginTop: 16 }}>
          {!pix ? (
            <>
              <button className="ck-btn ck-btn-primary ck-btn-block" disabled={busy} onClick={genPix}>
                {busy ? 'Gerando…' : 'Gerar QR code Pix'}
              </button>
              <p className="ck-card-sub" style={{ textAlign: 'center', marginTop: 10, fontSize: '.82rem' }}>
                Você paga na hora pelo app do seu banco. A confirmação aparece aqui automaticamente.
              </p>
            </>
          ) : (
            <div className="ck-center">
              {pix.pixQrCodeSvg && (
                <div style={{ width: 220, margin: '0 auto' }} dangerouslySetInnerHTML={{ __html: pix.pixQrCodeSvg }} />
              )}
              <p className="ck-card-sub" style={{ marginTop: 8 }}>Escaneie o QR ou use o Pix copia e cola:</p>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center', justifyContent: 'center', marginBottom: 12 }}>
                <code style={{ maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: '.78rem' }}>{pix.pixQrCode}</code>
                <button className="ck-btn ck-btn-light ck-btn-sm" onClick={copyPix}>{copied ? 'Copiado!' : 'Copiar'}</button>
              </div>
              <div className={`ck-msg ${paidByPix ? 'ck-msg-ok' : 'ck-msg-err'}`} style={{ background: paidByPix ? '#E7F7EE' : '#EEF3FB', color: paidByPix ? '#15803D' : '#1F3F8B' }}>
                {paidByPix ? 'Pagamento confirmado!' : 'Aguardando o pagamento… deixe esta página aberta.'}
              </div>
            </div>
          )}
        </div>
      )}

      {!pix && <button className="ck-btn ck-btn-light ck-btn-block" style={{ marginTop: 8 }} onClick={onBack}>Voltar</button>}
    </div>
  )
}

/** Retorno do ASAAS: aguarda a confirmação (webhook) fazendo polling do status. */
function ConfirmacaoStep({ code, onPaid }) {
  const { data } = useQuery({
    queryKey: ['guest-pay-status', code],
    queryFn: () => apiGet(`/public/orders/${code}/payment-status`),
    enabled: !!code,
    refetchInterval: (query) => (query.state.data?.status === 'paid' ? false : 3000),
  })
  const paid = data?.status === 'paid'

  // Pago → vai para a tela final 'done' (unifica a confirmação e marca o
  // passo Pagamento como concluído no stepper).
  useEffect(() => { if (paid) onPaid?.() }, [paid])

  return (
    <div className="ck-card ck-center" style={{ marginTop: 26, padding: '48px 24px' }}>
      <div className="ck-empty-ico" style={{ background: paid ? '#E7F7EE' : '#EEF3FB', color: paid ? '#16A34A' : '#1F3F8B', width: 68, height: 68 }}>
        {paid ? <IcCheck width={32} height={32} /> : <IcTicket width={30} height={30} />}
      </div>
      {paid ? (
        <>
          <h2 style={{ fontWeight: 800 }}>Inscrição confirmada!</h2>
          <p className="ck-card-sub">Enviamos os ingressos e o acesso por e-mail. Cada participante recebe o seu; o comprador acessa todos.</p>
        </>
      ) : (
        <>
          <h2 style={{ fontWeight: 800 }}>Confirmando seu pagamento…</h2>
          <p className="ck-card-sub">Assim que o pagamento for aprovado, seus ingressos são liberados automaticamente. Você pode aguardar nesta página.</p>
        </>
      )}
      {code && <p>Pedido: <strong>{code}</strong></p>}
      {paid && code && (
        <div className="ck-actions">
          <a className="ck-btn ck-btn-primary" href={`/api/public/orders/${code}/receipt`}>Baixar comprovante</a>
          <a className="ck-btn ck-btn-light" href="/entrar">Acompanhar meus pedidos</a>
        </div>
      )}
    </div>
  )
}
