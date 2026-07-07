import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput from './LocalizedInput'

const ICONS = ['people', 'globe', 'mic', 'temple']

export default function Estatisticas({ eventId, section, languages, reload }) {
  return (
    <div>
      <p className="text-secondary">Contadores que animam de 0 até o valor. Adicione, edite e reordene os cards.</p>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="estatística"
        newItem={() => ({ icon: 'people', value: 0, title: { pt: '' }, subtitle: { pt: '' } })}
        title={(p) => `${p.value ?? ''} — ${p.title?.pt || ''}`}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <div className="row">
              <div className="col-md-6">
                <label className="form-label mb-1">Ícone</label>
                <select className="form-select mb-2" value={d.icon} onChange={(e) => patch('icon', e.target.value)}>
                  {ICONS.map((i) => <option key={i} value={i}>{i}</option>)}
                </select>
              </div>
              <div className="col-md-6">
                <label className="form-label mb-1">Valor</label>
                <input type="number" className="form-control mb-2" value={d.value ?? 0} onChange={(e) => patch('value', Number(e.target.value))} />
              </div>
            </div>
            <LocalizedInput label="Título" value={d.title} onChange={(v) => patch('title', v)} languages={langs} />
            <LocalizedInput label="Subtítulo" value={d.subtitle} onChange={(v) => patch('subtitle', v)} languages={langs} />
          </>
        )}
      />
    </div>
  )
}
