import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiGet } from '../lib/api'
import { formatMoney } from '../lib/money'
import { useCart } from '../cart/CartProvider'

function Bloco({ block }) {
  const p = block.payload ?? {}

  switch (block.type) {
    case 'hero':
      return (
        <header className="py-5 text-center bg-dark text-white">
          <h1 className="display-5">{p.title}</h1>
          {p.subtitle && <p className="fs-3">{p.subtitle}</p>}
        </header>
      )
    case 'text':
      return <section className="container-xl py-4"><p className="fs-3">{p.body}</p></section>
    case 'schedule':
      return (
        <section className="container-xl py-4">
          <h2>Programação</h2>
          <ul>{(p.items ?? []).map((item, i) => (
            <li key={i}><strong>{item.day}</strong>{item.description && ` — ${item.description}`}</li>
          ))}</ul>
        </section>
      )
    case 'speakers':
      return (
        <section className="container-xl py-4">
          <h2>Palestrantes</h2>
          <ul>{(p.items ?? []).map((item, i) => <li key={i}>{item.name}</li>)}</ul>
        </section>
      )
    case 'faq':
      return (
        <section className="container-xl py-4">
          <h2>Perguntas frequentes</h2>
          {(p.items ?? []).map((item, i) => (
            <details key={i}><summary>{item.q}</summary><p>{item.a}</p></details>
          ))}
        </section>
      )
    case 'location':
      return (
        <section className="container-xl py-4">
          <h2>Local</h2>
          <p>{p.address}</p>
        </section>
      )
    case 'cta':
      return (
        <section className="container-xl py-4 text-center">
          <a href="#ingressos" className="btn btn-primary btn-lg">{p.label}</a>
        </section>
      )
    default:
      return null
  }
}

const SALES_STATE_LABEL = {
  soon: 'Inscrições em breve',
  closed: 'Inscrições encerradas',
  soldOut: 'Ingressos esgotados',
}

export default function EventoPublico() {
  const { slug } = useParams()
  const navigate = useNavigate()
  const { cart, setQuantity, totalItems } = useCart()

  const { data: event, isLoading, isError } = useQuery({
    queryKey: ['public', 'event', slug],
    queryFn: () => apiGet(`/public/events/${slug}`),
    retry: false,
  })

  if (isLoading) return <p style={{ padding: '2rem' }}>Carregando…</p>
  if (isError) return <p style={{ padding: '2rem' }}>Evento não encontrado.</p>

  if (event.cancelled) {
    return (
      <main className="container-xl py-5 text-center">
        <h1>{event.name}</h1>
        <p className="fs-3">Este evento foi cancelado.</p>
        {event.cancelReason && <p className="text-secondary">{event.cancelReason}</p>}
      </main>
    )
  }

  const quantities = cart?.eventSlug === slug ? cart.quantities : {}

  return (
    <main>
      {event.bannerUrl && (
        <img src={event.bannerUrl} alt={`Banner de ${event.name}`} className="w-100" style={{ maxHeight: 320, objectFit: 'cover' }} />
      )}

      {event.blocks.map((block, index) => <Bloco key={index} block={block} />)}

      <section id="ingressos" className="container-xl py-4">
        <h2>Ingressos</h2>

        {event.salesState !== 'open' ? (
          <div className="alert alert-warning">
            {SALES_STATE_LABEL[event.salesState]}
            {event.salesState === 'soon' && event.salesStartAt && (
              <> — abertura em {new Date(event.salesStartAt).toLocaleDateString('pt-BR')}</>
            )}
          </div>
        ) : (
          <>
            <table className="table" style={{ maxWidth: 640 }}>
              <tbody>
                {event.ticketTypes.filter((t) => !t.isCourtesy).map((type) => (
                  <tr key={type.id}>
                    <td>
                      <strong>{type.name}</strong>
                      {type.isCouple && <span className="badge bg-blue-lt ms-1">casal</span>}
                      {type.currentLotName && <div className="text-secondary small">{type.currentLotName}</div>}
                    </td>
                    <td>{formatMoney(type.effectivePrice)}</td>
                    <td style={{ width: 140 }}>
                      {type.soldOut ? (
                        <span className="badge bg-orange-lt">esgotado</span>
                      ) : (
                        <input
                          type="number" min={0} max={20}
                          className="form-control form-control-sm"
                          value={quantities[type.id] ?? 0}
                          onChange={(e) => setQuantity(slug, type.id, Number(e.target.value))}
                        />
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <button
              className="btn btn-primary btn-lg"
              disabled={totalItems === 0}
              onClick={() => navigate(`/checkout/${slug}`)}
            >
              Continuar ({totalItems} ingresso{totalItems === 1 ? '' : 's'})
            </button>
            {event.allowCourtesy && (
              <p className="text-secondary mt-2">
                Tem um voucher de cortesia? Informe no checkout — ou <Link to={`/checkout/${slug}`}>resgate agora</Link>.
              </p>
            )}
          </>
        )}
      </section>
    </main>
  )
}
