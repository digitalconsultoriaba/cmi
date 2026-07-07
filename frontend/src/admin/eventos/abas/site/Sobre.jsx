import SecaoSimples from './SecaoSimples'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

export default function Sobre(props) {
  const { eventId } = props
  return (
    <SecaoSimples {...props}>
      {(d, patch, langs) => {
        const gallery = Array.isArray(d.gallery) ? d.gallery : []
        return (
          <>
            <LocalizedInput label="Rótulo da seção" value={d.aboutLabel} onChange={(v) => patch('aboutLabel', v)} languages={langs} />
            <LocalizedInput label="Título" value={d.aboutTitle} onChange={(v) => patch('aboutTitle', v)} languages={langs} />
            <LocalizedInput label="Texto" value={d.aboutText} onChange={(v) => patch('aboutText', v)} languages={langs} textarea rows={5} />
            <div className="row">
              <div className="col-md-6"><LocalizedInput label="Botão — texto" value={d.aboutBtn} onChange={(v) => patch('aboutBtn', v)} languages={langs} /></div>
              <div className="col-md-6">
                <label className="form-label mb-1">Botão — link</label>
                <input className="form-control" value={d.aboutHref ?? ''} onChange={(e) => patch('aboutHref', e.target.value)} />
              </div>
            </div>
            <label className="form-label mt-2">Galeria (1ª foto = destaque)</label>
            <div className="d-flex flex-wrap gap-2">
              {gallery.map((p, i) => (
                <div key={i} className="border rounded p-1">
                  <MediaUpload value={p} onChange={(np) => patch('gallery', np ? gallery.map((x, j) => j === i ? np : x) : gallery.filter((_, j) => j !== i))} eventId={eventId} small />
                </div>
              ))}
              <MediaUpload label="" value={null} onChange={(np) => np && patch('gallery', [...gallery, np])} eventId={eventId} small />
            </div>
          </>
        )
      }}
    </SecaoSimples>
  )
}
