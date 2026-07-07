import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

export default function Patrocinadores(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <LocalizedInput label="Rótulo da seção" value={d.sponsorsLabel} onChange={(v) => patch('sponsorsLabel', v)} languages={langs} />
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="grupo"
        newItem={() => ({ title: { pt: '' } })}
        title={(p) => p.title?.pt || ''}
        renderFields={(d, patch, { languages: langs }) => (
          <LocalizedInput label="Título do grupo" value={d.title} onChange={(v) => patch('title', v)} languages={langs} />
        )}
        child={{
          singular: 'logo',
          title: (p) => p.name || '',
          newItem: () => ({ name: '', href: '', src: null }),
          renderFields: (d, patch) => (
            <>
              <label className="form-label mb-1">Nome</label>
              <input className="form-control mb-2" value={d.name ?? ''} onChange={(e) => patch('name', e.target.value)} />
              <label className="form-label mb-1">Link</label>
              <input className="form-control mb-2" value={d.href ?? ''} onChange={(e) => patch('href', e.target.value)} />
              <MediaUpload label="Logo" value={d.src} onChange={(p) => patch('src', p)} eventId={eventId} />
            </>
          ),
        }}
      />
    </SecaoDinamica>
  )
}
