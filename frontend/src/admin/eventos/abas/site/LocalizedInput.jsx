import { useState } from 'react'

const FLAGS = { pt: 'PT', en: 'EN', es: 'ES' }

/**
 * Campo de texto traduzível: edita um mapa { pt, en, es }. Mostra abas dos
 * idiomas ativos; o valor de cada idioma é editado isoladamente. PT é a base.
 */
export default function LocalizedInput({ label, value, onChange, languages = ['pt'], textarea, rows = 3, placeholder }) {
  const [lang, setLang] = useState(languages[0] || 'pt')
  const map = value && typeof value === 'object' && !Array.isArray(value) ? value : { pt: value ?? '' }
  const active = languages.includes(lang) ? lang : languages[0] || 'pt'

  const set = (v) => onChange({ ...map, [active]: v })

  return (
    <div className="mb-2">
      {label && <label className="form-label mb-1">{label}</label>}
      {languages.length > 1 && (
        <div className="btn-group btn-group-sm mb-1 d-block">
          {languages.map((l) => (
            <button key={l} type="button"
              className={`btn btn-sm ${l === active ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => setLang(l)}>
              {FLAGS[l] || l.toUpperCase()}
              {l !== 'pt' && !map[l] && <span className="ms-1 text-warning" title="Sem tradução">•</span>}
            </button>
          ))}
        </div>
      )}
      {textarea ? (
        <textarea className="form-control" rows={rows} placeholder={placeholder}
          value={map[active] ?? ''} onChange={(e) => set(e.target.value)} />
      ) : (
        <input className="form-control" placeholder={placeholder}
          value={map[active] ?? ''} onChange={(e) => set(e.target.value)} />
      )}
    </div>
  )
}

/** Lista de strings traduzível (ex.: itens de pilar, parágrafos legais). */
export function LocalizedList({ label, value, onChange, languages = ['pt'] }) {
  const [lang, setLang] = useState(languages[0] || 'pt')
  const map = value && typeof value === 'object' && !Array.isArray(value) ? value : { pt: value ?? [] }
  const active = languages.includes(lang) ? lang : languages[0] || 'pt'
  const list = Array.isArray(map[active]) ? map[active] : []

  const setList = (arr) => onChange({ ...map, [active]: arr })

  return (
    <div className="mb-2">
      {label && <label className="form-label mb-1">{label}</label>}
      {languages.length > 1 && (
        <div className="btn-group btn-group-sm mb-1 d-block">
          {languages.map((l) => (
            <button key={l} type="button"
              className={`btn btn-sm ${l === active ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => setLang(l)}>{FLAGS[l] || l.toUpperCase()}</button>
          ))}
        </div>
      )}
      {list.map((item, i) => (
        <div className="input-group input-group-sm mb-1" key={i}>
          <input className="form-control" value={item}
            onChange={(e) => setList(list.map((x, j) => (j === i ? e.target.value : x)))} />
          <button className="btn btn-outline-danger" type="button"
            onClick={() => setList(list.filter((_, j) => j !== i))}>×</button>
        </div>
      ))}
      <button className="btn btn-sm btn-outline-secondary" type="button"
        onClick={() => setList([...list, ''])}>+ item</button>
    </div>
  )
}
