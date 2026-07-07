import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAdminEvent } from '../../AdminLayout'
import { Card, Modal, useApiAction, ApiErrorAlert } from '../../components'
import { apiGet, apiPost, apiPut, apiDelete } from '../../../lib/api'

const FIELD_TYPES = [
  ['text', 'Texto'], ['affiliation', 'Afiliação (lista)'], ['country', 'País'], ['city', 'Cidade'], ['conditional', 'Condicional (possui X?)'],
]

/** Configuração do checkout (spec 014): categorias de participante + campos + afiliações. */
export default function Inscricoes() {
  const { data: event } = useAdminEvent()
  const eventId = event?.id
  const { run, busy, error, setError } = useApiAction()

  const categories = useQuery({ queryKey: ['pcats', eventId], queryFn: () => apiGet(`/admin/events/${eventId}/participant-categories`), enabled: !!eventId })
  const affiliations = useQuery({ queryKey: ['affs', eventId], queryFn: () => apiGet(`/admin/events/${eventId}/affiliations`), enabled: !!eventId })

  const [catForm, setCatForm] = useState(null)
  const [fieldForm, setFieldForm] = useState(null) // { categoryId, field|null }

  if (!eventId) return null
  const base = `/admin/events/${eventId}`
  const reload = () => { categories.refetch(); affiliations.refetch() }

  return (
    <div className="row g-3">
      <div className="col-lg-8">
        <Card title="Categorias de participante"
          actions={<button className="btn btn-sm btn-primary" onClick={() => setCatForm({ key: '', label: '' })}>Nova categoria</button>}>
          <ApiErrorAlert error={error} onClose={() => setError(null)} />
          {(categories.data ?? []).map((cat) => (
            <div className="border rounded p-2 mb-2" key={cat.id}>
              <div className="d-flex justify-content-between align-items-center">
                <strong>{cat.label} <span className="text-secondary small">({cat.key})</span></strong>
                <span className="btn-list">
                  <button className="btn btn-sm" onClick={() => setFieldForm({ categoryId: cat.id, field: null })}>+ Campo</button>
                  <button className="btn btn-sm btn-outline-danger" disabled={busy}
                    onClick={() => window.confirm('Excluir categoria?') && run(() => apiDelete(`${base}/participant-categories/${cat.id}`), { onSuccess: reload })}>Excluir</button>
                </span>
              </div>
              <ul className="mb-0 mt-1 small">
                {cat.fields.map((f) => (
                  <li key={f.id}>
                    {f.label} <span className="text-secondary">· {f.type}{f.required ? ' · obrigatório' : ''}</span>
                    {' '}<button className="btn btn-sm btn-link p-0" onClick={() => setFieldForm({ categoryId: cat.id, field: f })}>editar</button>
                    {' '}<button className="btn btn-sm btn-link text-danger p-0"
                      onClick={() => run(() => apiDelete(`${base}/participant-categories/${cat.id}/fields/${f.id}`), { onSuccess: reload })}>remover</button>
                  </li>
                ))}
                {cat.fields.length === 0 && <li className="text-secondary">Sem campos.</li>}
              </ul>
            </div>
          ))}
          {(categories.data ?? []).length === 0 && <p className="text-secondary">Nenhuma categoria configurada.</p>}
        </Card>
      </div>

      <div className="col-lg-4">
        <AfiliacoesCard base={base} items={affiliations.data ?? []} reload={reload} run={run} busy={busy} />
      </div>

      {catForm && (
        <CategoriaModal base={base} initial={catForm} onClose={() => setCatForm(null)} onSaved={() => { setCatForm(null); reload() }} run={run} busy={busy} />
      )}
      {fieldForm && (
        <CampoModal base={base} ctx={fieldForm} onClose={() => setFieldForm(null)} onSaved={() => { setFieldForm(null); reload() }} run={run} busy={busy} />
      )}
    </div>
  )
}

function CategoriaModal({ base, initial, onClose, onSaved, run, busy }) {
  const [f, setF] = useState(initial)
  const save = () => run(() => apiPost(`${base}/participant-categories`, { key: f.key, label: f.label }), { onSuccess: onSaved })
  return (
    <Modal title="Nova categoria" size="md" onClose={onClose}
      footer={<><button className="btn" onClick={onClose}>Cancelar</button><button className="btn btn-primary" disabled={busy || !f.label.trim() || !f.key.trim()} onClick={save}>Salvar</button></>}>
      <label className="form-label">Chave (ex.: glmees)</label>
      <input className="form-control mb-2" value={f.key} onChange={(e) => setF({ ...f, key: e.target.value })} />
      <label className="form-label">Rótulo (ex.: Irmão da GLMEES)</label>
      <input className="form-control" value={f.label} onChange={(e) => setF({ ...f, label: e.target.value })} />
    </Modal>
  )
}

function CampoModal({ base, ctx, onClose, onSaved, run, busy }) {
  const [f, setF] = useState(ctx.field ?? { key: '', label: '', type: 'text', required: false, config: {} })
  const isEdit = !!ctx.field
  const save = () => {
    const payload = { key: f.key, label: f.label, type: f.type, required: !!f.required, config: f.type === 'conditional' ? { question: f.config?.question || `Possui ${f.label}?` } : null }
    const req = isEdit
      ? apiPut(`${base}/participant-categories/${ctx.categoryId}/fields/${ctx.field.id}`, payload)
      : apiPost(`${base}/participant-categories/${ctx.categoryId}/fields`, payload)
    run(() => req, { onSuccess: onSaved })
  }
  return (
    <Modal title={isEdit ? 'Editar campo' : 'Novo campo'} size="md" onClose={onClose}
      footer={<><button className="btn" onClick={onClose}>Cancelar</button><button className="btn btn-primary" disabled={busy || !f.label.trim() || !f.key.trim()} onClick={save}>Salvar</button></>}>
      <div className="row g-2">
        <div className="col-6"><label className="form-label">Chave</label><input className="form-control" value={f.key} onChange={(e) => setF({ ...f, key: e.target.value })} /></div>
        <div className="col-6"><label className="form-label">Rótulo</label><input className="form-control" value={f.label} onChange={(e) => setF({ ...f, label: e.target.value })} /></div>
        <div className="col-6"><label className="form-label">Tipo</label>
          <select className="form-select" value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })}>
            {FIELD_TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select></div>
        <div className="col-6 d-flex align-items-end"><label className="form-check"><input type="checkbox" className="form-check-input" checked={!!f.required} onChange={(e) => setF({ ...f, required: e.target.checked })} /><span className="form-check-label">Obrigatório</span></label></div>
        {f.type === 'conditional' && (
          <div className="col-12"><label className="form-label">Pergunta</label>
            <input className="form-control" value={f.config?.question ?? ''} onChange={(e) => setF({ ...f, config: { question: e.target.value } })} /></div>
        )}
      </div>
    </Modal>
  )
}

function AfiliacoesCard({ base, items, reload, run, busy }) {
  const [name, setName] = useState('')
  const [bulk, setBulk] = useState('')
  return (
    <Card title="Afiliações (lojas)">
      <div className="input-group input-group-sm mb-2">
        <input className="form-control" placeholder="Nome da loja" value={name} onChange={(e) => setName(e.target.value)} />
        <button className="btn btn-primary" disabled={busy || !name.trim()} onClick={() => run(() => apiPost(`${base}/affiliations`, { name }), { onSuccess: () => { setName(''); reload() } })}>Add</button>
      </div>
      <ul className="small mb-2" style={{ maxHeight: 180, overflow: 'auto' }}>
        {items.map((a) => (
          <li key={a.id}>{a.name} <button className="btn btn-sm btn-link text-danger p-0" onClick={() => run(() => apiDelete(`${base}/affiliations/${a.id}`), { onSuccess: reload })}>×</button></li>
        ))}
        {items.length === 0 && <li className="text-secondary">Nenhuma.</li>}
      </ul>
      <label className="form-label small">Importar (uma por linha)</label>
      <textarea className="form-control form-control-sm mb-1" rows={3} value={bulk} onChange={(e) => setBulk(e.target.value)} />
      <button className="btn btn-sm btn-outline-secondary" disabled={busy || !bulk.trim()} onClick={() => run(() => apiPost(`${base}/affiliations/import`, { names: bulk }), { onSuccess: () => { setBulk(''); reload() } })}>Importar</button>
    </Card>
  )
}
