import { useState } from 'react'
import { Modal, useApiAction, ApiErrorAlert } from '../../../components'
import { apiPost, apiPut, apiDelete, apiPatch } from '../../../../lib/api'

/**
 * Editor de lista dinâmica de itens de uma seção do Site: adicionar, editar
 * (modal), remover e reordenar (setas). Suporta um nível de filhos via `child`.
 *
 * Props:
 *  - eventId, sectionId, items (árvore [{id,payload,children}]), onReload
 *  - languages: idiomas ativos
 *  - title(payload): rótulo da linha
 *  - renderFields(draft, patch, ctx): campos do formulário do item
 *  - newItem(): payload inicial
 *  - singular: nome do item (ex.: "palestrante")
 *  - child: { singular, title, renderFields, newItem } para filhos (opcional)
 */
export default function ListaOrdenavel({
  eventId, sectionId, items = [], onReload, languages, title, renderFields, newItem, singular = 'item', child,
}) {
  const base = `/admin/events/${eventId}/site/sections/${sectionId}/items`
  const { run, busy, error, setError } = useApiAction()
  const [editing, setEditing] = useState(null) // { parentId, item|null }
  const [openChild, setOpenChild] = useState(null) // id do pai expandido

  const save = (draft, parentId, id) => {
    const req = id
      ? apiPut(`${base}/${id}`, { payload: draft })
      : apiPost(base, { payload: draft, parentItemId: parentId ?? null })
    run(() => req, { onSuccess: () => { setEditing(null); onReload() } })
  }
  const remove = (id) => {
    if (!window.confirm('Remover este item?')) return
    run(() => apiDelete(`${base}/${id}`), { onSuccess: onReload })
  }
  const move = (list, parentId, index, dir) => {
    const next = [...list]
    const j = index + dir
    if (j < 0 || j >= next.length) return
    ;[next[index], next[j]] = [next[j], next[index]]
    run(() => apiPatch(`${base}/reorder`, { parentItemId: parentId ?? null, order: next.map((i) => i.id) }), { onSuccess: onReload })
  }

  const cfg = editing?.child ? child : { renderFields, newItem, singular, title }

  return (
    <div>
      <ApiErrorAlert error={error} onClose={() => setError(null)} />
      <div className="list-group mb-2">
        {items.length === 0 && <div className="text-secondary small py-2">Nenhum item ainda.</div>}
        {items.map((it, i) => (
          <div className="list-group-item" key={it.id}>
            <div className="d-flex align-items-center justify-content-between gap-2">
              <span className="text-truncate">{title(it.payload) || <em className="text-secondary">sem título</em>}</span>
              <span className="btn-list flex-nowrap">
                <button className="btn btn-sm" disabled={busy || i === 0} onClick={() => move(items, null, i, -1)}>↑</button>
                <button className="btn btn-sm" disabled={busy || i === items.length - 1} onClick={() => move(items, null, i, 1)}>↓</button>
                {child && (
                  <button className="btn btn-sm btn-outline-primary" onClick={() => setOpenChild(openChild === it.id ? null : it.id)}>
                    {child.singular}s ({it.children?.length || 0})
                  </button>
                )}
                <button className="btn btn-sm" onClick={() => setEditing({ item: it, parentId: null })}>Editar</button>
                <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => remove(it.id)}>Excluir</button>
              </span>
            </div>

            {child && openChild === it.id && (
              <div className="mt-2 ps-3 border-start">
                <div className="list-group mb-2">
                  {(it.children || []).length === 0 && <div className="text-secondary small py-1">Nenhum {child.singular}.</div>}
                  {(it.children || []).map((c, ci) => (
                    <div className="list-group-item py-1" key={c.id}>
                      <div className="d-flex align-items-center justify-content-between gap-2">
                        <span className="text-truncate small">{child.title(c.payload) || <em>—</em>}</span>
                        <span className="btn-list flex-nowrap">
                          <button className="btn btn-sm" disabled={busy || ci === 0} onClick={() => move(it.children, it.id, ci, -1)}>↑</button>
                          <button className="btn btn-sm" disabled={busy || ci === it.children.length - 1} onClick={() => move(it.children, it.id, ci, 1)}>↓</button>
                          <button className="btn btn-sm" onClick={() => setEditing({ item: c, parentId: it.id, child: true })}>Editar</button>
                          <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => remove(c.id)}>×</button>
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
                <button className="btn btn-sm btn-outline-secondary"
                  onClick={() => setEditing({ item: null, parentId: it.id, child: true })}>+ {child.singular}</button>
              </div>
            )}
          </div>
        ))}
      </div>
      <button className="btn btn-sm btn-primary" onClick={() => setEditing({ item: null, parentId: null })}>+ Novo {singular}</button>

      {editing && (
        <ItemForm
          title={`${editing.item ? 'Editar' : 'Novo'} ${cfg.singular || singular}`}
          languages={languages}
          initial={editing.item?.payload ?? cfg.newItem()}
          renderFields={cfg.renderFields}
          busy={busy}
          onClose={() => setEditing(null)}
          onSave={(draft) => save(draft, editing.parentId, editing.item?.id)}
        />
      )}
    </div>
  )
}

function ItemForm({ title, initial, renderFields, languages, onSave, onClose, busy }) {
  const [draft, setDraft] = useState(initial)
  const patch = (k, v) => setDraft((d) => ({ ...d, [k]: v }))

  return (
    <Modal title={title} size="lg" onClose={onClose}
      footer={<>
        <button className="btn" onClick={onClose}>Cancelar</button>
        <button className="btn btn-primary" disabled={busy} onClick={() => onSave(draft)}>Salvar</button>
      </>}>
      {renderFields(draft, patch, { languages })}
    </Modal>
  )
}
