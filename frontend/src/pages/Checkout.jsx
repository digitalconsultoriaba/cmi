import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api'
import { formatMoney } from '../lib/money'
import { parseApiError } from '../lib/forms'
import { useCart } from '../cart/CartProvider'

function ShirtSelect({ label, models, value, onChange }) {
  return (
    <div className="col-md-4">
      <label className="form-label">{label}</label>
      <select
        className="form-select"
        value={value ? `${value.modelId}:${value.sizeId}` : ''}
        onChange={(e) => {
          const [modelId, sizeId] = e.target.value.split(':').map(Number)
          onChange(e.target.value ? { modelId, sizeId } : null)
        }}
      >
        <option value="">Sem camisa</option>
        {models.flatMap((model) => model.sizes.filter((s) => !s.soldOut).map((size) => (
          <option key={`${model.id}:${size.id}`} value={`${model.id}:${size.id}`}>
            {model.label} — {size.label}
          </option>
        )))}
      </select>
    </div>
  )
}

export default function Checkout() {
  const { cart, clear } = useCart()
  const navigate = useNavigate()
  const [participants, setParticipants] = useState({})
  const [voucher, setVoucher] = useState('')
  const [error, setError] = useState(null)
  const [pending, setPending] = useState(false)

  const { data: event } = useQuery({
    queryKey: ['public', 'event', cart?.eventSlug],
    queryFn: () => apiGet(`/public/events/${cart.eventSlug}`),
    enabled: !!cart?.eventSlug,
  })

  // Expande quantidades em linhas de participante
  const rows = useMemo(() => {
    if (!cart || !event?.ticketTypes) return []
    const list = []
    for (const [typeId, quantity] of Object.entries(cart.quantities)) {
      const type = event.ticketTypes.find((t) => t.id === Number(typeId))
      if (!type) continue
      for (let i = 0; i < quantity; i++) list.push({ key: `${typeId}-${i}`, type })
    }
    return list
  }, [cart, event])

  if (!cart && !voucher) {
    return (
      <main className="container-xl py-5">
        <h1>Checkout</h1>
        <p>Seu carrinho está vazio.</p>
        <p>Tem um voucher de cortesia? Informe abaixo para resgatar.</p>
        <VoucherOnly />
      </main>
    )
  }

  if (!event) return <p style={{ padding: '2rem' }}>Carregando…</p>

  const set = (key, field, value) => setParticipants((current) => ({
    ...current,
    [key]: { ...(current[key] ?? {}), [field]: value },
  }))

  const total = rows.reduce((sum, row) => sum + Number(row.type.effectivePrice), 0)

  const submit = async () => {
    setError(null)
    setPending(true)
    try {
      const items = rows.map((row) => {
        const data = participants[row.key] ?? {}
        return {
          ticket_type_id: row.type.id,
          participant_name: data.name ?? '',
          participant_email: data.email || null,
          shirt_model_id: data.shirt?.modelId ?? null,
          shirt_size_id: data.shirt?.sizeId ?? null,
          companion_name: data.companionName || null,
          companion_shirt_model_id: data.companionShirt?.modelId ?? null,
          companion_shirt_size_id: data.companionShirt?.sizeId ?? null,
        }
      })

      await apiPost('/orders', {
        event_slug: cart.eventSlug,
        items,
        voucher_code: voucher || null,
      })

      clear()
      navigate('/minha-conta/pedidos', { replace: true, state: { created: true } })
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setPending(false)
    }
  }

  return (
    <main className="container-xl py-4" style={{ maxWidth: 860 }}>
      <h1>Checkout — {event.name}</h1>
      <p><Link to={`/evento/${cart.eventSlug}`}>← Voltar ao evento</Link></p>

      {error && (
        <div className="alert alert-danger">
          {error.message}
          {error.status === 422 && <div className="small mt-1">Revise os campos destacados abaixo.</div>}
        </div>
      )}

      {rows.map((row, index) => {
        const data = participants[row.key] ?? {}
        const fieldError = (name) => error?.fields?.[`items.${index}.${name}`]?.[0]

        return (
          <div className="card mb-3" key={row.key}>
            <div className="card-header">
              <strong>{row.type.name}</strong> — {formatMoney(row.type.effectivePrice)}
            </div>
            <div className="card-body row g-3">
              <div className="col-md-4">
                <label className="form-label">Nome do participante *</label>
                <input className="form-control" value={data.name ?? ''}
                  onChange={(e) => set(row.key, 'name', e.target.value)} />
                {fieldError('participant_name') && <div className="text-danger small">{fieldError('participant_name')}</div>}
              </div>
              <div className="col-md-4">
                <label className="form-label">E-mail (para o ingresso)</label>
                <input type="email" className="form-control" value={data.email ?? ''}
                  onChange={(e) => set(row.key, 'email', e.target.value)} />
              </div>
              {event.allowShirtChoice && (
                <ShirtSelect label={`Camisa${event.requiresShirt ? ' *' : ''}`}
                  models={event.shirtModels}
                  value={data.shirt} onChange={(v) => set(row.key, 'shirt', v)} />
              )}
              {fieldError('shirt_size_id') && <div className="text-danger small">{fieldError('shirt_size_id')}</div>}

              {row.type.isCouple && (
                <>
                  <div className="col-md-4">
                    <label className="form-label">Nome do acompanhante *</label>
                    <input className="form-control" value={data.companionName ?? ''}
                      onChange={(e) => set(row.key, 'companionName', e.target.value)} />
                    {fieldError('companion_name') && <div className="text-danger small">{fieldError('companion_name')}</div>}
                  </div>
                  {event.allowShirtChoice && (
                    <ShirtSelect label="Camisa do acompanhante"
                      models={event.shirtModels}
                      value={data.companionShirt} onChange={(v) => set(row.key, 'companionShirt', v)} />
                  )}
                </>
              )}
            </div>
          </div>
        )
      })}

      {event.allowCourtesy && (
        <div className="card mb-3">
          <div className="card-body row g-2 align-items-end">
            <div className="col-md-6">
              <label className="form-label">Voucher de cortesia (opcional)</label>
              <input className="form-control" placeholder="CTY-..." value={voucher}
                onChange={(e) => setVoucher(e.target.value.toUpperCase())} />
            </div>
          </div>
        </div>
      )}

      <div className="d-flex justify-content-between align-items-center">
        <div className="fs-2">Total: <strong>{formatMoney(String(total))}</strong></div>
        <button className="btn btn-primary btn-lg" onClick={submit} disabled={pending || (rows.length === 0 && !voucher)}>
          {pending ? 'Confirmando…' : 'Confirmar pedido'}
        </button>
      </div>
      <p className="text-secondary mt-2">
        Sua reserva fica garantida pelo prazo indicado no pedido. O pagamento
        on-line estará disponível em breve.
      </p>
    </main>
  )
}

function VoucherOnly() {
  const navigate = useNavigate()
  const [slug, setSlug] = useState('seminario-internacional-2026')
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [error, setError] = useState(null)

  const redeem = async () => {
    setError(null)
    try {
      await apiPost('/orders', {
        event_slug: slug,
        items: [],
        voucher_code: code,
        courtesy_participants: name ? [{ participant_name: name }] : [],
      })
      navigate('/minha-conta/ingressos', { replace: true })
    } catch (err) {
      setError(parseApiError(err))
    }
  }

  return (
    <div style={{ maxWidth: 480 }}>
      {error && <div className="alert alert-danger">{error.message}</div>}
      <label className="form-label">Código do voucher</label>
      <input className="form-control mb-2" placeholder="CTY-..." value={code}
        onChange={(e) => setCode(e.target.value.toUpperCase())} />
      <label className="form-label">Nome do participante</label>
      <input className="form-control mb-3" value={name} onChange={(e) => setName(e.target.value)} />
      <button className="btn btn-primary" onClick={redeem} disabled={!code}>Resgatar</button>
    </div>
  )
}
