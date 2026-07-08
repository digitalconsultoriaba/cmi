import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '../../../lib/api'
import { ApiErrorAlert, Modal, useApiAction } from '../../components'
import { useEventoUI } from '../EventoLayout'

const money = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const dt = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—')

// Badges sólidos (cores fortes)
const STATUS_BADGE = {
  paid: 'bg-success text-white', confirmed: 'bg-success text-white',
  courtesy: 'bg-purple text-white', used: 'bg-primary text-white',
  pending: 'bg-warning text-dark', partially_paid: 'bg-orange text-white',
  reserved: 'bg-warning text-dark', awaiting_payment: 'bg-warning text-dark',
  cancelled: 'bg-danger text-white', transferred: 'bg-secondary text-white',
  refunded: 'bg-danger text-white', expired: 'bg-secondary text-white',
}

const TABS = ['Dados', 'Compras', 'Ingressos', 'Mensagens', 'Histórico']

/** Ficha do cliente (comprador) — abas de dados, compra, ingresso, chat. */
export default function ClienteFicha({ userId, onClose }) {
  const { eventId } = useParams()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [tab, setTab] = useState('Dados')
  const [message, setMessage] = useState('')
  const [note, setNote] = useState('')
  const [showHistoryModal, setShowHistoryModal] = useState(false)

  // Esconde as abas do evento enquanto a ficha do cliente está aberta
  const ui = useEventoUI()
  useEffect(() => {
    ui?.setHideChrome(true)
    return () => ui?.setHideChrome(false)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const base = `/admin/events/${eventId}/customers/${userId}`

  const { data } = useQuery({
    queryKey: ['admin', 'event', eventId, 'customer', userId],
    queryFn: () => apiGet(base),
  })

  const { data: thread } = useQuery({
    queryKey: ['admin', 'event', eventId, 'customer', userId, 'messages'],
    queryFn: () => apiGet(`${base}/messages`),
    enabled: tab === 'Mensagens',
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId, 'customer', userId] })

  const enviar = () => run(() => apiPost(`${base}/messages`, { message }), {
    onSuccess: () => {
      setMessage('')
      queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId, 'customer', userId, 'messages'] })
    },
  })

  const adicionarNota = () => run(() => apiPost(`${base}/history`, { note }), {
    onSuccess: () => { setNote(''); setShowHistoryModal(false); invalidate() },
  })

  const cancelarIngresso = (code) => {
    const reason = window.prompt('Motivo do cancelamento do ingresso:')
    if (!reason) return
    run(() => apiPost(`/admin/tickets/${code}/cancel`, { reason }), {
      onSuccess: () => { invalidate(); queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId] }) },
    })
  }
  const cancelarPedido = (code) => {
    const reason = window.prompt('Motivo do cancelamento do pedido inteiro:')
    if (!reason) return
    run(() => apiPost(`/admin/orders/${code}/cancel`, { reason }), {
      onSuccess: () => { invalidate(); queryClient.invalidateQueries({ queryKey: ['admin', 'event', eventId] }) },
    })
  }

  if (!data) return <p className="text-secondary">Carregando…</p>

  const { customer, stats, orders, tickets, history } = data

  return (
    <>
      <div className="card mb-3">
        <div className="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <button className="btn btn-sm mb-2" onClick={onClose}>← Inscritos</button>
            <h2 className="mb-0">{customer.name}</h2>
            <div className="text-secondary">
              {customer.email}
              {customer.phone && <> · {customer.phone}</>}
              {' · '}{stats.ordersCount} pedido(s) · {stats.ticketsCount} ingresso(s) ·
              {' '}{money(stats.totalPaid)} pago
            </div>
          </div>
        </div>
      </div>

      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <ul className="nav nav-tabs mb-3">
        {TABS.map((t) => (
          <li className="nav-item" key={t}>
            <button className={`nav-link ${tab === t ? 'active' : ''}`} onClick={() => setTab(t)}>{t}</button>
          </li>
        ))}
      </ul>

      {tab === 'Dados' && (
        <div className="card"><div className="card-body">
          <dl className="row mb-0">
            <dt className="col-3">Nome</dt><dd className="col-9">{customer.name}</dd>
            <dt className="col-3">E-mail</dt><dd className="col-9">{customer.email}</dd>
            <dt className="col-3">Telefone</dt><dd className="col-9">{customer.phone ?? '—'}</dd>
            <dt className="col-3">Documento</dt><dd className="col-9">{customer.document ?? '—'}</dd>
          </dl>
        </div></div>
      )}

      {tab === 'Compras' && (
        <div className="card"><div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr><th>Pedido</th><th>Data</th><th className="text-end">Valor</th><th>Forma</th><th>Situação</th><th /></tr></thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.code}>
                  <td><code>{o.code}</code></td>
                  <td className="small">{dt(o.createdAt)}</td>
                  <td className="text-end">{money(o.total)}</td>
                  <td className="text-capitalize">{o.method ?? '—'}</td>
                  <td><span className={`badge ${STATUS_BADGE[o.status] ?? 'bg-secondary-lt'}`}>{o.statusLabel}</span></td>
                  <td className="text-end">
                    {o.canCancel && (
                      <button className="btn btn-sm btn-outline-danger" disabled={busy}
                        onClick={() => cancelarPedido(o.code)}>Cancelar pedido</button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      )}

      {tab === 'Ingressos' && (
        <div className="card"><div className="card-table table-responsive">
          <table className="table table-vcenter">
            <thead><tr><th>Ingresso</th><th>Participante</th><th>Tipo</th><th className="text-end">Valor</th><th>Camisa</th><th>Situação</th><th>Cortesia / Entrada</th><th /></tr></thead>
            <tbody>
              {tickets.map((t) => (
                <tr key={t.code}>
                  <td><code>{t.code}</code></td>
                  <td>{t.participantName}{t.companionName && <span className="text-secondary small"> + {t.companionName}</span>}</td>
                  <td>
                    {t.ticketTypeName}
                    {t.orderTicketsCount > 1 && (
                      <div className="mt-1">
                        <span className="badge bg-azure text-white">Coletivo · {t.orderTicketsCount} ingressos</span>
                      </div>
                    )}
                  </td>
                  <td className="text-end">
                    {money(t.unitPrice)}
                    {t.orderTicketsCount > 1 && (
                      <div className="small text-secondary">Pedido {money(t.orderTotal)}</div>
                    )}
                  </td>
                  <td className="small">{t.shirt ?? '—'}</td>
                  <td><span className={`badge ${STATUS_BADGE[t.status] ?? 'bg-secondary text-white'}`}>{t.statusLabel}</span></td>
                  <td className="small">
                    {t.isCourtesy && (
                      <div className="text-purple">
                        <strong>Cortesia</strong> · concedida por {t.courtesyGivenBy}
                        {t.courtesyCode && <> ({t.courtesyCode})</>}
                      </div>
                    )}
                    {t.usedAt
                      ? <div className="text-primary">✓ Entrou em {dt(t.usedAt)}{t.validatedBy && <> · {t.validatedBy}</>}</div>
                      : <div className="text-secondary">Ainda não utilizado</div>}
                  </td>
                  <td className="text-end">
                    <span className="btn-list justify-content-end">
                      {t.printable && t.status !== 'used' && (
                        <a className="btn btn-sm btn-success" href={`/api/admin/tickets/${t.code}/receipt`} target="_blank" rel="noopener">
                          Baixar ingresso
                        </a>
                      )}
                      {t.status === 'used' && (
                        <span className="text-secondary small align-self-center">Já utilizado</span>
                      )}
                      {t.canCancel && (
                        <button className="btn btn-sm btn-outline-danger" disabled={busy}
                          onClick={() => cancelarIngresso(t.code)}>Cancelar</button>
                      )}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      )}

      {tab === 'Mensagens' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Conversa com {customer.name}</h3>
          </div>
          <div className="card-body" style={{ maxHeight: 440, overflowY: 'auto', background: '#f6f8fb' }}>
            {(thread?.messages ?? []).length === 0 && (
              <div className="text-center text-secondary py-4">Nenhuma mensagem ainda. Envie a primeira.</div>
            )}
            {(thread?.messages ?? []).map((m, i) => {
              const mine = !m.fromAttendee
              const who = mine ? (m.author ?? 'Organização') : customer.name
              const initials = who.split(' ').slice(0, 2).map((s) => s[0]).join('').toUpperCase()
              return (
                <div key={i} className={`d-flex align-items-end mb-3 ${mine ? 'flex-row-reverse' : ''}`}>
                  <span className={`avatar avatar-sm ${mine ? 'ms-2' : 'me-2'}`}
                    style={{ background: mine ? 'var(--brand-blue)' : '#dbe3ef', color: mine ? '#fff' : '#334' }}>
                    {initials}
                  </span>
                  <div style={{ maxWidth: '72%' }}>
                    <div className={`p-2 px-3 rounded-3 ${mine ? 'text-white' : 'bg-white border'}`}
                      style={mine ? { background: 'var(--brand-blue)' } : {}}>
                      {m.body}
                    </div>
                    <div className={`small text-secondary mt-1 ${mine ? 'text-end' : ''}`}>
                      {who} · {dt(m.createdAt)}
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
          <div className="card-footer">
            <form className="d-flex gap-2" onSubmit={(e) => { e.preventDefault(); if (message.trim()) enviar() }}>
              <input className="form-control" placeholder="Escreva uma mensagem para o cliente…"
                value={message} onChange={(e) => setMessage(e.target.value)} />
              <button className="btn btn-primary" disabled={busy || !message.trim()}>Enviar</button>
            </form>
            <small className="text-secondary">O cliente recebe e responde na área "Suporte" da conta dele.</small>
          </div>
        </div>
      )}

      {tab === 'Histórico' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Histórico</h3>
            <div className="card-actions">
              <button className="btn btn-sm btn-primary" onClick={() => setShowHistoryModal(true)}>
                Adicionar anotação
              </button>
            </div>
          </div>
          <div className="card-table table-responsive">
            <table className="table table-vcenter">
              <thead><tr><th>Quando</th><th>Ação</th><th>Descrição</th><th>Autor</th></tr></thead>
              <tbody>
                {history.map((h, i) => (
                  <tr key={i}>
                    <td className="small text-nowrap text-secondary">{dt(h.createdAt)}</td>
                    <td><span className="badge bg-primary text-white">{h.action}</span></td>
                    <td>{h.description}</td>
                    <td>{h.causer ?? <span className="badge bg-secondary text-white">sistema</span>}</td>
                  </tr>
                ))}
                {history.length === 0 && <tr><td colSpan={4} className="text-secondary">Sem histórico.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {showHistoryModal && (
        <Modal title="Adicionar anotação" size="md" onClose={() => setShowHistoryModal(false)}
          footer={
            <>
              <button className="btn" onClick={() => setShowHistoryModal(false)}>Cancelar</button>
              <button className="btn btn-primary" disabled={busy || !note.trim()} onClick={adicionarNota}>Salvar</button>
            </>
          }>
          <label className="form-label">Observação</label>
          <textarea className="form-control" rows={4} autoFocus placeholder="Descreva a anotação…"
            value={note} onChange={(e) => setNote(e.target.value)} />
          <div className="form-hint mt-1">Registra automaticamente o usuário, a data e a hora.</div>
        </Modal>
      )}
    </>
  )
}
