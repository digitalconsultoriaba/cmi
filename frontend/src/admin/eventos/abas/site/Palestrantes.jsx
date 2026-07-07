import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

export default function Palestrantes(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <>
          <LocalizedInput label="Rótulo da seção" value={d.speakersLabel} onChange={(v) => patch('speakersLabel', v)} languages={langs} />
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Botão — texto" value={d.speakersBtn} onChange={(v) => patch('speakersBtn', v)} languages={langs} /></div>
            <div className="col-md-6">
              <label className="form-label mb-1">Botão — link</label>
              <input className="form-control" value={d.speakersHref ?? ''} onChange={(e) => patch('speakersHref', e.target.value)} />
            </div>
          </div>
        </>
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="palestrante"
        newItem={() => ({ name: '', role: { pt: '' }, org: '', talk: { pt: '' }, day: '', time: '', photo: null })}
        title={(p) => p.name || ''}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <div className="row">
              <div className="col-md-6">
                <label className="form-label mb-1">Nome</label>
                <input className="form-control mb-2" value={d.name ?? ''} onChange={(e) => patch('name', e.target.value)} />
              </div>
              <div className="col-md-6">
                <label className="form-label mb-1">Organização</label>
                <input className="form-control mb-2" value={d.org ?? ''} onChange={(e) => patch('org', e.target.value)} />
              </div>
            </div>
            <LocalizedInput label="Cargo" value={d.role} onChange={(v) => patch('role', v)} languages={langs} />
            <LocalizedInput label="Palestra" value={d.talk} onChange={(v) => patch('talk', v)} languages={langs} textarea />
            <div className="row">
              <div className="col-md-6">
                <label className="form-label mb-1">Dia</label>
                <input className="form-control mb-2" value={d.day ?? ''} onChange={(e) => patch('day', e.target.value)} />
              </div>
              <div className="col-md-6">
                <label className="form-label mb-1">Horário</label>
                <input className="form-control mb-2" value={d.time ?? ''} onChange={(e) => patch('time', e.target.value)} />
              </div>
            </div>
            <MediaUpload label="Foto (opcional)" value={d.photo} onChange={(p) => patch('photo', p)} eventId={eventId} />
          </>
        )}
      />
    </SecaoDinamica>
  )
}
