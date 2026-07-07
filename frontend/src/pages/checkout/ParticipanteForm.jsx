import { useState } from 'react'
import { formatMoney } from '../../lib/money'
import { IcUserPlus, IcBuilding, IcGlobe } from './icons'

/** Campo dinâmico da categoria, no estilo do checkout. */
function CampoDinamico({ field, value, onChange, affiliations }) {
  const set = (v) => onChange(field.key, v)

  if (field.type === 'affiliation') {
    return (
      <div className="ck-field">
        <label className="ck-label">{field.label}{field.required && ' *'}</label>
        <input className="ck-input" list={`aff-${field.key}`} placeholder="Busque sua loja — nome, número ou cidade"
          value={value ?? ''} onChange={(e) => set(e.target.value)} />
        <datalist id={`aff-${field.key}`}>{affiliations.map((a) => <option key={a.id} value={a.name} />)}</datalist>
      </div>
    )
  }

  if (field.type === 'conditional') {
    const has = value != null && value !== ''
    return (
      <div className="ck-field">
        <label className="ck-label">{field.config?.question || field.label}</label>
        <div className="ck-toggle">
          <button type="button" className={has ? 'on' : ''} onClick={() => set(value && value.trim() ? value : ' ')}>Sim</button>
          <button type="button" className={!has ? 'on' : ''} onClick={() => set('')}>Não</button>
        </div>
        {has && (
          <input className="ck-input mt-2" style={{ marginTop: 10 }} placeholder={field.label}
            value={value.trim() === '' ? '' : value} onChange={(e) => set(e.target.value || ' ')} />
        )}
      </div>
    )
  }

  return (
    <div className="ck-field">
      <label className="ck-label">{field.label}{field.required && ' *'}</label>
      <input className="ck-input" placeholder={field.label} value={value ?? ''} onChange={(e) => set(e.target.value)} />
    </div>
  )
}

function vincMeta(category) {
  const hasAff = (category.fields ?? []).some((f) => f.type === 'affiliation')
  return { Icon: hasAff ? IcBuilding : IcGlobe, sub: hasAff ? 'Loja cadastrada' : 'Potência externa' }
}

/** Card "Adicionar participante" (referência tela1). */
export default function ParticipanteForm({ config, initial, onSubmit, onCancel }) {
  const { categories, ticketTypes, affiliations } = config
  const [categoryKey, setCategoryKey] = useState(initial?.categoryKey ?? categories[0]?.key ?? '')
  const [ticketTypeId, setTicketTypeId] = useState(initial?.ticketTypeId ?? ticketTypes.find((t) => t.purchasable)?.id ?? ticketTypes[0]?.id)
  const [name, setName] = useState(initial?.participantName ?? '')
  const [email, setEmail] = useState(initial?.participantEmail ?? '')
  const [whatsapp, setWhatsapp] = useState(initial?.whatsapp ?? '')
  const [fields, setFields] = useState(initial?.fields ?? {})

  const category = categories.find((c) => c.key === categoryKey)
  const setField = (k, v) => setFields((f) => ({ ...f, [k]: v }))
  const clear = () => { setName(''); setEmail(''); setWhatsapp(''); setFields({}) }

  const submit = () => {
    if (!name.trim()) return alert('Informe o nome do participante.')
    if (!whatsapp.trim()) return alert('Informe o WhatsApp do participante.')
    if (!email.trim()) return alert('Informe o e-mail do participante.')
    for (const f of category?.fields ?? []) {
      if (f.required && !String(fields[f.key] ?? '').trim()) return alert(`Preencha "${f.label}".`)
    }
    onSubmit({ categoryKey, ticketTypeId, participantName: name.trim(), participantEmail: email.trim() || null, whatsapp: whatsapp.trim() || null, fields })
    clear()
  }

  return (
    <div className="ck-card">
      <div className="ck-card-head">
        <span className="ico"><IcUserPlus /></span>
        <span className="ck-card-title">{initial ? 'Editar participante' : 'Adicionar participante'}</span>
      </div>
      <p className="ck-card-sub">Escolha o tipo de vínculo e informe os dados necessários para gerar a inscrição.</p>

      <label className="ck-label">Tipo de vínculo</label>
      <div className="ck-vinc">
        {categories.map((c) => {
          const { Icon, sub } = vincMeta(c)
          const on = c.key === categoryKey
          return (
            <div key={c.key} className={`ck-vinc-opt ${on ? 'on' : ''}`} onClick={() => { setCategoryKey(c.key); setFields({}) }}>
              <span className="ck-vinc-ico"><Icon /></span>
              <span><span className="ck-vinc-tt d-block">{c.label}</span><span className="ck-vinc-sub">{sub}</span></span>
              <span className="ck-vinc-dot" />
            </div>
          )
        })}
      </div>

      {ticketTypes.length > 1 && (
        <div className="ck-field">
          <label className="ck-label">Tipo de ingresso</label>
          <select className="ck-select" value={ticketTypeId} onChange={(e) => setTicketTypeId(Number(e.target.value))}>
            {ticketTypes.filter((t) => t.purchasable).map((t) => <option key={t.id} value={t.id}>{t.name} — {formatMoney(t.effectivePrice)}</option>)}
          </select>
        </div>
      )}

      <div className="ck-field"><label className="ck-label">Nome do Irmão *</label>
        <input className="ck-input" placeholder="Digite o nome completo" value={name} onChange={(e) => setName(e.target.value)} /></div>

      <div className="ck-field"><label className="ck-label">WhatsApp *</label>
        <input className="ck-input" placeholder="(00) 00000-0000" value={whatsapp} onChange={(e) => setWhatsapp(e.target.value)} /></div>

      <div className="ck-field"><label className="ck-label">E-mail *</label>
        <input type="email" className="ck-input" placeholder="exemplo@email.com" value={email} onChange={(e) => setEmail(e.target.value)} /></div>

      {(category?.fields ?? []).map((f) => (
        <CampoDinamico key={f.key} field={f} value={fields[f.key]} onChange={setField} affiliations={affiliations} />
      ))}

      <div style={{ display: 'flex', gap: 10, marginTop: 6 }}>
        <button className="ck-btn ck-btn-primary" onClick={submit}><IcUserPlus width={18} height={18} /> {initial ? 'Salvar' : 'Adicionar participante'}</button>
        <button className="ck-btn ck-btn-light" onClick={clear}>Limpar dados</button>
        {initial && onCancel && <button className="ck-btn ck-btn-ghost" onClick={onCancel}>Cancelar</button>}
      </div>
    </div>
  )
}
