import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

export default function Depoimentos(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <LocalizedInput label="Rótulo da seção" value={d.testiLabel} onChange={(v) => patch('testiLabel', v)} languages={langs} />
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="depoimento"
        newItem={() => ({ text: { pt: '' }, name: '', role: { pt: '' }, photo: null })}
        title={(p) => p.name || (p.text?.pt || '').slice(0, 40)}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <LocalizedInput label="Texto" value={d.text} onChange={(v) => patch('text', v)} languages={langs} textarea rows={4} />
            <label className="form-label mb-1">Nome</label>
            <input className="form-control mb-2" value={d.name ?? ''} onChange={(e) => patch('name', e.target.value)} />
            <LocalizedInput label="Cargo" value={d.role} onChange={(v) => patch('role', v)} languages={langs} />
            <MediaUpload label="Foto (opcional)" value={d.photo} onChange={(p) => patch('photo', p)} eventId={eventId} />
          </>
        )}
      />
    </SecaoDinamica>
  )
}
