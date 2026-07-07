import { useState } from 'react'

/** Campo dinâmico conforme o tipo definido na categoria. */
function CampoDinamico({ field, value, onChange, affiliations }) {
  const set = (v) => onChange(field.key, v)

  if (field.type === 'affiliation') {
    return (
      <div className="mb-2">
        <label className="form-label">{field.label}{field.required && ' *'}</label>
        <input className="form-control" list={`aff-${field.key}`} value={value ?? ''} onChange={(e) => set(e.target.value)} />
        <datalist id={`aff-${field.key}`}>
          {affiliations.map((a) => <option key={a.id} value={a.name} />)}
        </datalist>
      </div>
    )
  }

  if (field.type === 'conditional') {
    const has = value != null && value !== ''
    return (
      <div className="mb-2">
        <label className="form-label d-block">{field.config?.question || field.label}</label>
        <div className="btn-group btn-group-sm mb-1">
          <button type="button" className={`btn ${has ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => set(value || ' ')}>Sim</button>
          <button type="button" className={`btn ${!has ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => set('')}>Não</button>
        </div>
        {has && (
          <input className="form-control" placeholder={field.label} value={value.trim() === '' ? '' : value}
            onChange={(e) => set(e.target.value || ' ')} />
        )}
      </div>
    )
  }

  return (
    <div className="mb-2">
      <label className="form-label">{field.label}{field.required && ' *'}</label>
      <input className="form-control" value={value ?? ''} onChange={(e) => set(e.target.value)} />
    </div>
  )
}

/** Formulário de um participante: categoria → tipo → campos. */
export default function ParticipanteForm({ config, initial, onSubmit, onCancel }) {
  const { categories, ticketTypes, affiliations } = config
  const [categoryKey, setCategoryKey] = useState(initial?.categoryKey ?? categories[0]?.key ?? '')
  const [ticketTypeId, setTicketTypeId] = useState(initial?.ticketTypeId ?? ticketTypes.find((t) => t.purchasable)?.id ?? ticketTypes[0]?.id)
  const [name, setName] = useState(initial?.participantName ?? '')
  const [email, setEmail] = useState(initial?.participantEmail ?? '')
  const [fields, setFields] = useState(initial?.fields ?? {})

  const category = categories.find((c) => c.key === categoryKey)
  const setField = (k, v) => setFields((f) => ({ ...f, [k]: v }))

  const submit = () => {
    if (!name.trim()) return alert('Informe o nome do participante.')
    for (const f of category?.fields ?? []) {
      if (f.required && !String(fields[f.key] ?? '').trim()) return alert(`Preencha "${f.label}".`)
    }
    onSubmit({ categoryKey, ticketTypeId, participantName: name.trim(), participantEmail: email.trim() || null, fields })
  }

  return (
    <div className="border rounded p-3 mb-3" style={{ background: '#fbfcfe' }}>
      <div className="mb-2">
        <label className="form-label">Quem será inscrito?</label>
        <div className="btn-group btn-group-sm d-block">
          {categories.map((c) => (
            <button key={c.key} type="button" className={`btn ${c.key === categoryKey ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => { setCategoryKey(c.key); setFields({}) }}>{c.label}</button>
          ))}
        </div>
      </div>

      {ticketTypes.length > 1 && (
        <div className="mb-2">
          <label className="form-label">Tipo de ingresso</label>
          <select className="form-select" value={ticketTypeId} onChange={(e) => setTicketTypeId(Number(e.target.value))}>
            {ticketTypes.filter((t) => t.purchasable).map((t) => <option key={t.id} value={t.id}>{t.name} — R$ {t.effectivePrice}</option>)}
          </select>
        </div>
      )}

      <div className="row">
        <div className="col-md-6 mb-2"><label className="form-label">Nome do irmão *</label>
          <input className="form-control" value={name} onChange={(e) => setName(e.target.value)} /></div>
        <div className="col-md-6 mb-2"><label className="form-label">E-mail</label>
          <input type="email" className="form-control" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
      </div>

      {(category?.fields ?? []).map((f) => (
        <CampoDinamico key={f.key} field={f} value={fields[f.key]} onChange={setField} affiliations={affiliations} />
      ))}

      <div className="btn-list mt-2">
        <button className="btn btn-primary" onClick={submit}>Adicionar ao carrinho</button>
        {onCancel && <button className="btn" onClick={onCancel}>Cancelar</button>}
      </div>
    </div>
  )
}
