import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput, { LocalizedList } from './LocalizedInput'

const ICONS = ['connect', 'learn', 'transform']

export default function Pilares(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <LocalizedInput label="Rótulo da seção" value={d.pillarsLabel} onChange={(v) => patch('pillarsLabel', v)} languages={langs} />
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="pilar"
        newItem={() => ({ icon: 'connect', title: { pt: '' }, items: { pt: [] } })}
        title={(p) => p.title?.pt || ''}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <label className="form-label mb-1">Ícone</label>
            <select className="form-select mb-2" value={d.icon} onChange={(e) => patch('icon', e.target.value)}>
              {ICONS.map((i) => <option key={i} value={i}>{i}</option>)}
            </select>
            <LocalizedInput label="Título" value={d.title} onChange={(v) => patch('title', v)} languages={langs} />
            <LocalizedList label="Itens" value={d.items} onChange={(v) => patch('items', v)} languages={langs} />
          </>
        )}
      />
    </SecaoDinamica>
  )
}
