import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAdminEvent } from '../AdminLayout'
import { Card, ApiErrorAlert, StatusBadge, useApiAction } from '../components'
import { apiGet, apiPost, apiPut, apiDelete, apiPatch } from '../../lib/api'
import Loading from '../../components/Loading'

const TYPE_LABELS = {
  hero: 'Capa', text: 'Texto', schedule: 'Programação',
  speakers: 'Palestrantes', faq: 'Perguntas frequentes', location: 'Local', cta: 'Chamada',
}

// Campo principal editável por tipo (edição rica item-a-item pode evoluir depois)
const MAIN_FIELD = {
  hero: ['title', 'Título da capa'],
  text: ['body', 'Texto'],
  location: ['address', 'Endereço'],
  cta: ['label', 'Texto do botão'],
  schedule: ['items', 'Itens (um por linha: dia | descrição)'],
  speakers: ['items', 'Palestrantes (um por linha)'],
  faq: ['items', 'Perguntas (um por linha: pergunta | resposta)'],
}

function payloadFromDraft(type, draft) {
  const [field] = MAIN_FIELD[type]

  if (field !== 'items') {
    return { [field]: draft }
  }

  const items = draft.split('\n').map((line) => line.trim()).filter(Boolean).map((line) => {
    const [a, b] = line.split('|').map((part) => part.trim())
    if (type === 'schedule') return { day: a, description: b ?? '' }
    if (type === 'faq') return { q: a, a: b ?? '' }
    return { name: a }
  })

  return { items }
}

function draftFromPayload(type, payload) {
  const [field] = MAIN_FIELD[type]

  if (field !== 'items') return payload?.[field] ?? ''

  return (payload?.items ?? []).map((item) => {
    if (type === 'schedule') return `${item.day ?? ''} | ${item.description ?? ''}`
    if (type === 'faq') return `${item.q ?? ''} | ${item.a ?? ''}`
    return item.name ?? ''
  }).join('\n')
}

export default function Landing() {
  const { data: event } = useAdminEvent()
  const queryClient = useQueryClient()
  const { run, error, setError, busy } = useApiAction()
  const [newType, setNewType] = useState('hero')
  const [drafts, setDrafts] = useState({})

  const eventId = event?.id
  const { data: blocks = [] } = useQuery({
    queryKey: ['admin', eventId, 'landing-blocks'],
    queryFn: () => apiGet(`/admin/events/${eventId}/landing-blocks`),
    enabled: !!eventId,
  })

  if (!event) return <Loading fullscreen={false} />

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', eventId, 'landing-blocks'] })

  const addBlock = () => run(() => apiPost(`/admin/events/${eventId}/landing-blocks`, {
    type: newType,
    payload: payloadFromDraft(newType, drafts.new ?? ''),
  }), { onSuccess: () => { refresh(); setDrafts({ ...drafts, new: '' }) } })

  const saveBlock = (block) => run(() => apiPut(`/admin/events/${eventId}/landing-blocks/${block.id}`, {
    type: block.type,
    payload: payloadFromDraft(block.type, drafts[block.id] ?? draftFromPayload(block.type, block.payload)),
    is_active: block.isActive,
  }), { onSuccess: refresh })

  const toggleBlock = (block) => run(() => apiPut(`/admin/events/${eventId}/landing-blocks/${block.id}`, {
    type: block.type,
    payload: block.payload,
    is_active: !block.isActive,
  }), { onSuccess: refresh })

  const removeBlock = (block) => run(
    () => apiDelete(`/admin/events/${eventId}/landing-blocks/${block.id}`),
    { onSuccess: refresh }
  )

  const move = (index, delta) => {
    const ids = blocks.map((b) => b.id)
    const [moved] = ids.splice(index, 1)
    ids.splice(index + delta, 0, moved)
    return run(() => apiPatch(`/admin/events/${eventId}/landing-blocks/reorder`, { ids }), { onSuccess: refresh })
  }

  return (
    <>
      <h2>Landing — página pública</h2>
      <p className="text-secondary">A renderização pública chega na spec 004; aqui você monta os blocos.</p>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />

      <Card title="Adicionar bloco">
        <div className="row g-2">
          <div className="col-md-3">
            <select className="form-select" value={newType} onChange={(e) => setNewType(e.target.value)}>
              {Object.entries(TYPE_LABELS).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </div>
          <div className="col-md-7">
            <textarea className="form-control" rows={2} placeholder={MAIN_FIELD[newType][1]}
              value={drafts.new ?? ''} onChange={(e) => setDrafts({ ...drafts, new: e.target.value })} />
          </div>
          <div className="col-md-2">
            <button className="btn btn-primary" onClick={addBlock} disabled={busy}>Adicionar</button>
          </div>
        </div>
      </Card>

      {blocks.map((block, index) => (
        <Card key={block.id}
          title={`${index + 1}. ${TYPE_LABELS[block.type] ?? block.type}`}
          actions={
            <span className="btn-list">
              <button className="btn btn-sm" disabled={index === 0 || busy} onClick={() => move(index, -1)}>↑</button>
              <button className="btn btn-sm" disabled={index === blocks.length - 1 || busy} onClick={() => move(index, 1)}>↓</button>
              <button className="btn btn-sm" onClick={() => toggleBlock(block)}>
                {block.isActive ? 'Ocultar' : 'Exibir'}
              </button>
              <button className="btn btn-sm btn-outline-danger" onClick={() => removeBlock(block)}>Excluir</button>
            </span>
          }>
          <StatusBadge ok={block.isActive} okLabel="visível" badLabel="oculto" />
          <textarea className="form-control mt-2" rows={3}
            placeholder={MAIN_FIELD[block.type][1]}
            value={drafts[block.id] ?? draftFromPayload(block.type, block.payload)}
            onChange={(e) => setDrafts({ ...drafts, [block.id]: e.target.value })} />
          <button className="btn btn-sm btn-success mt-2" onClick={() => saveBlock(block)} disabled={busy}>
            Salvar bloco
          </button>
        </Card>
      ))}
    </>
  )
}
