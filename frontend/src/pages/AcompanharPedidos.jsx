import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiPost } from '../lib/api'
import { formatMoney } from '../lib/money'
import { maskCpfCnpj } from './checkout/fields'
import './checkout/checkout.css'
import { IcTicket, IcCompass } from './checkout/icons'

const STATUS = {
  pending: { label: 'Aguardando pagamento', cls: '' },
  paid: { label: 'Pago', cls: 'ck-badge-green' },
  partially_paid: { label: 'Parcialmente pago', cls: '' },
  cancelled: { label: 'Cancelado', cls: '' },
  expired: { label: 'Expirado', cls: '' },
  refunded: { label: 'Estornado', cls: '' },
}

export default function AcompanharPedidos() {
  const navigate = useNavigate()
  const [doc, setDoc] = useState('')
  const [orders, setOrders] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  // Volta ao checkout de onde veio; se abriu direto, cai no evento padrão.
  const backToCheckout = () => {
    if (window.history.length > 1) navigate(-1)
    else navigate('/checkout/seminario-internacional-2026')
  }

  const search = async (e) => {
    e?.preventDefault()
    setError(null); setBusy(true)
    try {
      setOrders(await apiPost('/public/orders/track', { document: doc }))
    } catch {
      setError('Não foi possível consultar agora. Tente novamente em instantes.')
    } finally { setBusy(false) }
  }

  return (
    <div className="ck">
      <header className="ck-header">
        <div className="ck-watermark"><IcCompass style={{ color: '#fff' }} /></div>
        <div className="ck-header-inner">
          <div style={{ textAlign: 'right', marginBottom: 8 }}>
            <button type="button" onClick={backToCheckout}
              style={{ background: 'none', border: 'none', color: '#fff', fontSize: '.85rem', textDecoration: 'underline', opacity: 0.92, cursor: 'pointer', padding: 0 }}>
              ← Voltar para o checkout
            </button>
          </div>
          <div className="ck-header-top">
            <span className="ck-seal"><img src="/logo.png" alt="CMI · GLMEES" /></span>
            <div>
              <div className="ck-title">Acompanhar pedidos</div>
              <div className="ck-subtitle">Informe seu CPF/CNPJ para ver seus pedidos e baixar o comprovante.</div>
            </div>
          </div>
        </div>
      </header>

      <div className="ck-wrap" style={{ maxWidth: 720 }}>
        <form className="ck-card" onSubmit={search} style={{ marginTop: 24 }}>
          <label className="ck-label">CPF/CNPJ</label>
          <input className="ck-input" value={doc} onChange={(e) => setDoc(maskCpfCnpj(e.target.value))}
            placeholder="000.000.000-00" inputMode="numeric" />
          <button className="ck-btn ck-btn-primary ck-btn-block" style={{ marginTop: 12 }}
            disabled={busy || doc.replace(/\D/g, '').length < 11}>
            {busy ? 'Buscando…' : 'Buscar pedidos'}
          </button>
          {error && <div style={{ color: '#B91C1C', marginTop: 10 }}>{error}</div>}
        </form>

        {orders !== null && orders.length === 0 && (
          <div className="ck-card ck-center" style={{ color: '#6B7A90' }}>
            Nenhum pedido encontrado para este CPF/CNPJ.
          </div>
        )}

        {orders?.map((o) => {
          const st = STATUS[o.status] ?? { label: o.status, cls: '' }
          return (
            <div className="ck-card" key={o.code}>
              <div className="ck-card-head">
                <span className="ico"><IcTicket /></span>
                <span className="ck-card-title">{o.event?.name}</span>
                <span className={`ck-badge ${st.cls}`} style={{ marginLeft: 'auto' }}>{st.label}</span>
              </div>
              <div className="ck-sum-row"><span className="muted">Pedido</span><span><strong>{o.code}</strong></span></div>
              <div className="ck-sum-row"><span className="muted">Total</span><span>{formatMoney(o.totalAmount)}</span></div>
              {o.tickets?.length > 0 && (
                <div className="ck-sum-row"><span className="muted">Participantes</span>
                  <span>{o.tickets.map((t) => t.participantName).join(', ')}</span></div>
              )}
              {o.status === 'paid' && (
                <a className="ck-btn ck-btn-primary ck-btn-block" style={{ marginTop: 12 }}
                  href={`/api/public/orders/${o.code}/receipt`}>Baixar comprovante</a>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
